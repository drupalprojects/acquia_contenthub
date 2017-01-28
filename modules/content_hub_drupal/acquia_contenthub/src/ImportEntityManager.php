<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\ImportEntityManager.
 */

namespace Drupal\acquia_contenthub;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;

/**
 * Provides a service for managing imported entities' actions.
 */
class ImportEntityManager {

  private $format = 'acquia_contenthub_cdf';

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  private $loggerFactory;

  /**
   * The Serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  private $serializer;

  /**
   * Entity Repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  private $clientManager;

  /**
   * The Content Hub Entities Tracking Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   */
  private $contentHubEntitiesTracking;

  /**
   * Diff module's entity comparison service.
   *
   * @var Drupal\diff\DiffEntityComparison
   */
  private $diffEntityComparison;

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('serializer'),
      $container->get('entity.repository'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_entities_tracking'),
      $container->get('diff.entity_comparison')
    );
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The Logger Factory.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The Serializer.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The Entity Repository.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubEntitiesTracking $entities_tracking
   *   The Content Hub Entities Tracking Service.
   * @param \Drupal\diff\DiffEntityComparison $entity_comparison
   *   The Diff module's Entity Comparison service.
   */
  public function __construct(Connection $database, LoggerChannelFactory $logger_factory, SerializerInterface $serializer, EntityRepositoryInterface $entity_repository, ClientManagerInterface $client_manager, ContentHubEntitiesTracking $entities_tracking, DiffEntityComparison $entity_comparison) {
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
    $this->serializer = $serializer;
    $this->entityRepository = $entity_repository;
    $this->clientManager = $client_manager;
    $this->contentHubEntitiesTracking = $entities_tracking;
    $this->diffEntityComparison = $entity_comparison;
  }

  /**
   * Act on the entity's presave action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being saved.
   */
  public function entityPresave(EntityInterface $entity) {
    // If the entity is "pending sync" or already "has local change", skip.
    $imported_entity = $this->contentHubEntitiesTracking->loadImportedByDrupalEntity($entity->getEntityTypeId(), $entity->id());
    if (!$imported_entity || $imported_entity->isPendingSync() || $imported_entity->hasLocalChange()) {
      return;
    }

    // Otherwise check if the entity has introduced any local changes.
    $field_comparisons = $this->diffEntityComparison->compareRevisions($entity->original, $entity);

    $has_local_change = FALSE;
    foreach ($field_comparisons as $field_comparison) {
      if ($field_comparison['#data']['#left'] !== $field_comparison['#data']['#right']) {
        $has_local_change = TRUE;
        break;
      }
    }

    // Don't do anything if there is no local change.
    if (!$has_local_change) {
      return;
    }

    // Otherwise, set and store the imported entity as having local changes.
    $imported_entity->setLocalChange();
    $imported_entity->save();
  }

  /**
   * Loads the Remote Content Hub Entity.
   *
   * @param string $uuid
   *   The Remote Entity UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntityDependency|bool
   *   The Content Hub Entity Dependency if found, FALSE otherwise.
   */
  public function loadRemoteEntity($uuid) {
    $entity = $this->clientManager->createRequest('readEntity', [$uuid]);
    if (!$entity) {
      return FALSE;
    }
    return new ContentHubEntityDependency($entity);
  }

  /**
   * Obtains all dependencies for the current Content Hub Entity.
   *
   * It collects dependencies on all levels, flattening out the dependency array
   * to avoid looping circular dependencies.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $content_hub_entity
   *   The Content Hub Entity.
   * @param array $dependencies
   *   An array of \Drupal\acquia_contenthub\ContentHubEntityDependency.
   * @param bool|TRUE $use_chain
   *   If the dependencies should be unique to the dependency chain or not.
   *
   * @return array
   *   An array of \Drupal\acquia_contenthub\ContentHubEntityDependency.
   */
  private function getAllRemoteDependencies(ContentHubEntityDependency $content_hub_entity, &$dependencies, $use_chain = TRUE) {
    // Obtaining dependencies of this entity.
    $dep_dependencies = $this->getRemoteDependencies($content_hub_entity, $use_chain);

    /** @var \Drupal\acquia_contenthub\ContentHubEntityDependency $content_hub_dependency */
    foreach ($dep_dependencies as $uuid => $content_hub_dependency) {
      if (isset($dependencies[$uuid])) {
        continue;
      }

      // Also check if this dependency has been previously imported and has the
      // same modified timestamp. If the 'modified' timestamp matches then we
      // know we are trying to import an entity that has no change at all, then
      // it does not need to be imported again.
      if ($imported_entity = $this->contentHubEntitiesTracking->loadImportedByUuid($uuid)) {
        if ($imported_entity->getModified() === $content_hub_dependency->getRawEntity()->getModified()) {
          continue;
        }
      }

      $dependencies[$uuid] = $content_hub_dependency;
      $this->getAllRemoteDependencies($content_hub_dependency, $dependencies, $use_chain);
    }
    return array_reverse($dependencies, TRUE);
  }

  /**
   * Obtains First-level remote dependencies for the current Content Hub Entity.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $content_hub_entity
   *   The Content Hub Entity.
   * @param bool|TRUE $use_chain
   *   If the dependencies should be unique to the dependency chain or not.
   *
   * @return array
   *   An array of \Drupal\acquia_contenthub\ContentHubEntityDependency.
   */
  private function getRemoteDependencies(ContentHubEntityDependency $content_hub_entity, $use_chain = TRUE) {
    $dependencies = array();
    $uuids = $content_hub_entity->getRemoteDependencies();

    foreach ($uuids as $uuid) {
      $content_hub_dependent_entity = $this->loadRemoteEntity($uuid);
      if ($content_hub_dependent_entity === FALSE) {
        continue;
      }
      // If this dependency is already tracked in the dependency chain
      // then we don't need to consider it a dependency unless we're not using
      // the chain.
      if ($content_hub_entity->isInDependencyChain($content_hub_dependent_entity) && $use_chain) {
        $content_hub_dependent_entity->setParent($content_hub_entity);
        continue;
      }
      $content_hub_dependent_entity->setParent($content_hub_entity);
      $dependencies[$uuid] = $content_hub_dependent_entity;
    }
    return $dependencies;
  }

  /**
   * Saves a Content Hub Entity into a Drupal Entity, given its UUID.
   *
   * This method accepts a parameter if we want to save all its dependencies.
   * Note that dependencies could be of 2 different types:
   *   - pre-dependency or Entity Independent:
   *       Has to be created before the host-entity and referenced from it.
   *   - post-dependency or Entity Dependent:
   *       Has to be created after the host-entity and referenced from it.
   * This is a recursive method, and will also create dependencies of the
   * dependencies.
   *
   * @param string $uuid
   *   The UUID of the Entity to save.
   * @param bool $include_dependencies
   *   TRUE if we want to save all its dependencies, FALSE otherwise.
   * @param string $author
   *   The UUID of the author (user) that will own the entity.
   * @param int $status
   *   The publishing status of the entity (Applies to nodes).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON Response.
   */
  public function importRemoteEntity($uuid, $include_dependencies = TRUE, $author = NULL, $status = 0) {
    // Checking that the parameter given is a UUID.
    if (!Uuid::isValid($uuid)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    // If the Entity is not found in Content Hub then return a 404 Not Found.
    $contenthub_entity = $this->loadRemoteEntity($uuid);
    if (!$contenthub_entity) {
      $message = t('Entity with UUID = @uuid not found.', array(
        '@uuid' => $uuid,
      ));
      return $this->jsonErrorResponseMessage($message, FALSE, 404);
    }

    $origin = $contenthub_entity->getRawEntity()->getOrigin();
    $site_origin = $this->contentHubEntitiesTracking->getSiteOrigin();

    // Checking that the entity origin is different than this site's origin.
    if ($origin === $site_origin) {
      $args = array(
        '@type' => $contenthub_entity->getRawEntity()->getType(),
        '@uuid' => $contenthub_entity->getRawEntity()->getUuid(),
        '@origin' => $origin,
      );
      $message = new FormattableMarkup('Cannot save "@type" entity with uuid="@uuid". It has the same origin as this site: "@origin"', $args);
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
      $result = FALSE;
      return $this->jsonErrorResponseMessage($message, $result, 403);
    }

    // Collect and flat out all dependencies.
    $dependencies = array();
    if ($include_dependencies) {
      $dependencies = $this->getAllRemoteDependencies($contenthub_entity, $dependencies, TRUE);
    }

    // Obtaining the Status of the parent entity, if it is a node and
    // setting the publishing status of that entity.
    $contenthub_entity->setStatus($status);

    // Assigning author to this entity and dependencies.
    $contenthub_entity->setAuthor($author);

    foreach ($dependencies as $uuid => $dependency) {
      $dependencies[$uuid]->setAuthor($author);
      // Only change the Node status of dependent entities if they are nodes,
      // if the status flag is set and if they haven't been imported before.
      $entity_type = $dependency->getEntityType();
      if (isset($status) && $entity_type === 'node' && !$this->contentHubEntitiesTracking->loadImportedByUuid($uuid)) {
        $dependencies[$uuid]->setStatus($status);
      }
    }

    // Save this entity and all its dependencies.
    return $this->importRemoteEntityDependencies($contenthub_entity, $dependencies);
  }

  /**
   * Saves the current Drupal Entity and all its dependencies.
   *
   * This method is not to be used alone but to be used from
   * importRemoteEntity() method, which is why it is private.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $contenthub_entity
   *   The Content Hub Entity.
   * @param array $dependencies
   *   An array of ContentHubEntityDependency objects.
   *
   * @return bool|null
   *   The Drupal entity being created.
   */
  private function importRemoteEntityDependencies(ContentHubEntityDependency $contenthub_entity, &$dependencies) {
    // Un-managed assets are also pre-dependencies for an entity and they would
    // need to be saved before we can create the current entity.
    $this->saveUnManagedAssets($contenthub_entity);

    // Create pre-dependencies.
    foreach ($contenthub_entity->getDependencyChain() as $uuid) {
      $content_hub_entity_dependency = isset($dependencies[$uuid]) ? $dependencies[$uuid] : FALSE;
      if ($content_hub_entity_dependency && !isset($content_hub_entity_dependency->__processed) && $content_hub_entity_dependency->getRelationship() == ContentHubEntityDependency::RELATIONSHIP_INDEPENDENT) {
        $dependencies[$uuid]->__processed = TRUE;
        $this->importRemoteEntityDependencies($content_hub_entity_dependency, $dependencies);
      }
    }

    // Now that we have created all its pre-dependencies, create the current
    // Drupal entity.
    $host_entity = $contenthub_entity->isEntityDependent() ? $this->getHostEntity($contenthub_entity, $dependencies) : FALSE;
    $entity = $this->importRemoteEntityNoDependencies($contenthub_entity, $host_entity);

    // Create post-dependencies.
    foreach ($contenthub_entity->getDependencyChain() as $uuid) {
      $content_hub_entity_dependency = isset($dependencies[$uuid]) ? $dependencies[$uuid] : FALSE;
      if ($content_hub_entity_dependency && !isset($content_hub_entity_dependency->__processed) && $content_hub_entity_dependency->getRelationship() == ContentHubEntityDependency::RELATIONSHIP_DEPENDENT) {
        $dependencies[$uuid]->__processed = TRUE;
        $content_hub_entity_dependency->importRemoteEntityDependencies($content_hub_entity_dependency, $dependencies);
      }
    }
    return $entity;
  }

  /**
   * Saves Unmanaged Assets.
   */
  private function saveUnManagedAssets($contenthub_entity) {
    // @TODO: Implement this function to save unmanaged files.
  }

  /**
   * Obtains the host entity for a post-dependency.
   */
  private function getHostEntity($contenthub_entity, $dependencies) {
    // @TODO: Implement obtaining the Host Entity.
    return FALSE;
  }

  /**
   * Saves an Entity without taking care of dependencies. Not to be used alone.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $contenthub_entity
   *   The Content Hub Entity.
   * @param object $host_entity
   *   The Host Entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The Response.
   *
   * @throws \Exception
   *   Throws exception in certain cases.
   */
  private function importRemoteEntityNoDependencies(ContentHubEntityDependency $contenthub_entity, $host_entity) {
    // Import the entity.
    $entity_type = $contenthub_entity->getRawEntity()->getType();
    $class = \Drupal::entityTypeManager()->getDefinition($entity_type)->getClass();

    // Check if this dependency has originated in this site or not.
    $site_origin = $this->contentHubEntitiesTracking->getSiteOrigin();
    if ($contenthub_entity->getRawEntity()->getOrigin() == $site_origin) {
      $args = array(
        '@type' => $contenthub_entity->getRawEntity()->getType(),
        '@uuid' => $contenthub_entity->getRawEntity()->getUuid(),
        '@origin' => $contenthub_entity->getRawEntity()->getOrigin(),
      );
      $message = new FormattableMarkup('Cannot save "@type" entity with uuid="@uuid". It has the same origin as this site: "@origin"', $args);
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
      return $this->jsonErrorResponseMessage($message, FALSE, 400);
    }

    try {
      $entity = $this->serializer->deserialize($contenthub_entity->getRawEntity()->json(), $class, $this->format);
    }
    catch (\UnexpectedValueException $e) {
      $error = $e->getMessage();
      return $this->jsonErrorResponseMessage($error, FALSE, 400);
    }

    // Finally Save the Entity.
    $transaction = $this->database->startTransaction();
    try {
      // Add synchronization flag.
      $entity->__contenthub_synchronized = TRUE;
      // Save the entity.
      $entity->save();
      // Remove synchronization flag.
      unset($entity->__contenthub_synchronized);

      // Save this entity in the tracking for importing entities.
      $cdf = (array) $contenthub_entity->getRawEntity();
      $this->trackImportedEntity($cdf);

    }
    catch (\Exception $e) {
      $transaction->rollback();
      $this->loggerFactory->get('acquia_contenthub')->error($e->getMessage());
      throw $e;
    }

    $serialized_entity = $this->serializer->normalize($entity, 'json');
    return new JsonResponse($serialized_entity);
  }

  /**
   * Provides a JSON Response Message.
   *
   * @param string $message
   *   The message to print.
   * @param bool $status
   *   The status message.
   * @param int $status_code
   *   The HTTP Status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON Response.
   */
  private function jsonErrorResponseMessage($message, $status, $status_code = 400) {
    // If the Entity is not found in Content Hub then return a 404 Not Found.
    $json = array(
      'status' => $status,
      'message' => $message,
    );
    return new JsonResponse($json, $status_code);
  }

  /**
   * Save this entity in the Tracking table.
   *
   * @param array $cdf
   *   The entity that has to be tracked as imported entity.
   */
  private function trackImportedEntity($cdf) {
    if ($imported_entity = $this->contentHubEntitiesTracking->loadImportedByUuid($cdf['uuid'])) {
      $imported_entity->setAutoUpdate();
      $imported_entity->setModified($cdf['modified']);
    }
    else {
      $entity = $this->entityRepository->loadEntityByUuid($cdf['type'], $cdf['uuid']);
      $this->contentHubEntitiesTracking->setImportedEntity(
        $cdf['type'],
        $entity->id(),
        $cdf['uuid'],
        $cdf['modified'],
        $cdf['origin']
      );
    }
    // Now save the entity.
    if ($this->contentHubEntitiesTracking->save()) {
      $args = array(
        '%type' => $cdf['type'],
        '%uuid' => $cdf['uuid'],
      );
      $message = new FormattableMarkup('Saving %type entity with uuid=%uuid. Tracking imported entity with auto updates.', $args);
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
    }
  }

  /**
   * Act on the entity's update action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being updated.
   */
  public function entityUpdate(EntityInterface $entity) {
    $imported_entity = $this->contentHubEntitiesTracking->loadImportedByDrupalEntity($entity->getEntityTypeId(), $entity->id());
    // Do nothing, if:
    // 1) Not an imported entity.
    // 2) Is not during sync or pending sync.
    if (!$imported_entity || isset($entity->__contenthub_synchronized) || !$imported_entity->isPendingSync()) {
      return;
    }

    // Otherwise, re-import the entity.
    $this->importRemoteEntity($imported_entity->getUuid(), $entity);
  }

}
