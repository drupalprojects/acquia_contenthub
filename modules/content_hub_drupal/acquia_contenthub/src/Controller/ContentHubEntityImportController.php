<?php
/**
 * @file
 * Import Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\EntityManager as EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\acquia_contenthub\ContentHubImportedEntities;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Component\Uuid\Uuid;
use Drupal\acquia_contenthub\ContentHubEntityDependency;

/**
 * Controller for Content Hub Imported Entities.
 */
class ContentHubEntityImportController extends ControllerBase {

  protected $format = 'acquia_contenthub_cdf';

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The Content Hub Entity Manager.
   *
   * @var \Drupal\acquia_contenthub\EntityManager
   */
  protected $entityManager;

  /**
   * The Serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The Content Hub Imported Entities.
   *
   * @var \Drupal\acquia_contenthub\ContentHubImportedEntities
   */
  protected $contentHubImportedEntities;

  /**
   * Public Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The Logger Factory.
   * @param \Drupal\acquia_contenthub\EntityManager $entity_manager
   *   The Acquia Content Hub Entity Manager.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The Serializer.
   * @param \Drupal\acquia_contenthub\ContentHubImportedEntities $acquia_contenthub_imported_entities
   *   The Content Hub Imported Entities Service.
   */
  public function __construct(Connection $database, LoggerChannelFactory $logger_factory, EntityManager $entity_manager, SerializerInterface $serializer, ContentHubImportedEntities $acquia_contenthub_imported_entities) {
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
    $this->entityManager = $entity_manager;
    $this->serializer = $serializer;
    $this->contentHubImportedEntities = $acquia_contenthub_imported_entities;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('acquia_contenthub.entity_manager'),
      $container->get('serializer'),
      $container->get('acquia_contenthub.acquia_contenthub_imported_entities')
    );
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
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON Response.
   */
  public function saveDrupalEntity($uuid, $include_dependencies = TRUE, $author = NULL) {
    // Checking that the parameter given is a UUID.
    if (!Uuid::isValid($uuid)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }
    if ($contenthub_entity = $this->entityManager->loadRemoteEntity($uuid)) {

      $origin = $contenthub_entity->getRawEntity()->getOrigin();
      $site_origin = $this->contentHubImportedEntities->getSiteOrigin();

      // Checking that the entity origin is different than this site's origin.
      if ($origin == $site_origin) {
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
        $dependencies = $this->entityManager->getAllRemoteDependencies($contenthub_entity, $dependencies, TRUE);
      }

      // Obtaining the Status of the parent entity, if it is a node.
      // if ($attribute = $contenthub_entity->getRawEntity()
      // ->getAttribute('status')) {
      // $status = $attribute->getValue();
      // }
      // Assigning author to this entity and dependencies.
      // $contenthub_entity->setAuthor($author);
      foreach ($dependencies as $uuid => $dependency) {
        // $dependencies[$uuid]->setAuthor($author);
        // Only change the Node status of dependent entities if they are nodes,
        // if the status flag is set and if they haven't been imported before.
        $entity_type = $dependency->getEntityType();
        if (isset($status) && ($entity_type == 'node')) {
          if ($this->contentHubImportedEntities->loadByUuid($uuid) === FALSE) {
            // $dependencies[$uuid]->setStatus($status);
          }
        }
      }

      // Save this entity and all its dependencies.
      return $this->saveDrupalEntityDependencies($contenthub_entity, $dependencies);
    }
    else {
      // If the Entity is not found in Content Hub then return a 404 Not Found.
      $message = t('Entity with UUID = @uuid not found.', array(
        '@uuid' => $uuid,
      ));
      return $this->jsonErrorResponseMessage($message, FALSE, 404);
    }

  }

  /**
   * Saves the current Drupal Entity and all its dependencies.
   *
   * This method is not to be used alone but to be used from saveDrupalEntity()
   * method, which is why it is protected.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $contenthub_entity
   *   The Content Hub Entity.
   * @param array $dependencies
   *   An array of ContentHubEntityDependency objects.
   *
   * @return bool|null
   *   The Drupal entity being created.
   */
  protected function saveDrupalEntityDependencies(ContentHubEntityDependency $contenthub_entity, &$dependencies) {
    // Un-managed assets are also pre-dependencies for an entity and they would
    // need to be saved before we can create the current entity.
    $this->saveUnManagedAssets($contenthub_entity);

    // Create pre-dependencies.
    foreach ($contenthub_entity->getDependencyChain() as $uuid) {
      $content_hub_entity_dependency = isset($dependencies[$uuid]) ? $dependencies[$uuid] : FALSE;
      if ($content_hub_entity_dependency && !isset($content_hub_entity_dependency->__processed) && $content_hub_entity_dependency->getRelationship() == ContentHubEntityDependency::RELATIONSHIP_INDEPENDENT) {
        $dependencies[$uuid]->__processed = TRUE;
        $this->saveDrupalEntityDependencies($content_hub_entity_dependency, $dependencies);
      }
    }

    // Now that we have created all its pre-dependencies, create the current
    // Drupal entity.
    $host_entity = $contenthub_entity->isEntityDependent() ? $this->getHostEntity($contenthub_entity, $dependencies) : FALSE;
    $entity = $this->saveDrupalEntityNoDependencies($contenthub_entity, $host_entity);

    // Create post-dependencies.
    foreach ($contenthub_entity->getDependencyChain() as $uuid) {
      $content_hub_entity_dependency = isset($dependencies[$uuid]) ? $dependencies[$uuid] : FALSE;
      if ($content_hub_entity_dependency && !isset($content_hub_entity_dependency->__processed) && $content_hub_entity_dependency->getRelationship() == ContentHubEntityDependency::RELATIONSHIP_DEPENDENT) {
        $dependencies[$uuid]->__processed = TRUE;
        $content_hub_entity_dependency->saveDrupalEntityDependencies($content_hub_entity_dependency, $dependencies);
      }
    }
    return $entity;
  }

  /**
   * Saves Unmanaged Assets.
   */
  protected function saveUnManagedAssets($contenthub_entity) {
    // @TODO: Implement this function to save unmanaged files.
  }


  /**
   * Obtains the host entity for a post-dependency.
   */
  protected function getHostEntity($contenthub_entity, $dependencies) {
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
  protected function saveDrupalEntityNoDependencies(ContentHubEntityDependency $contenthub_entity, $host_entity) {
    // Import the entity.
    $entity_type = $contenthub_entity->getRawEntity()->getType();
    $class = \Drupal::entityTypeManager()->getDefinition($entity_type)->getClass();

    try {
      $entity = $this->serializer->deserialize($contenthub_entity->getRawEntity()->json(), $class, $this->format);
    }
    catch (UnexpectedValueException $e) {
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

      // @TODO: Fix the auto_update flag be saved according to a value.
      $auto_update = \Drupal\acquia_contenthub\ContentHubImportedEntities::AUTO_UPDATE_ENABLED;

      // Save this entity in the tracking for importing entities.
      $origin = $contenthub_entity->getRawEntity()->getOrigin();
      $this->contentHubImportedEntities->setImportedEntity($entity->getEntityTypeId(), $entity->id(), $entity->uuid(), $auto_update, $origin);

      $args = array(
        '%type' => $entity->getEntityTypeId(),
        '%uuid' => $entity->uuid(),
        '%auto_update' => $auto_update,
      );

      if ($this->contentHubImportedEntities->save()) {
        $message = new FormattableMarkup('Saving %type entity with uuid=%uuid. Tracking imported entity with auto_update = %auto_update', $args);
        $this->loggerFactory->get('acquia_contenthub')->debug($message);
      }
      else {
        $message = new FormattableMarkup('Saving %type entity with uuid=%uuid, but not tracking this entity in acquia_contenthub_imported_entities table because it could not be saved.', $args);
        $this->loggerFactory->get('acquia_contenthub')->warning($message);
      }

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
   * @param string $status
   *   The status message.
   * @param int $status_code
   *   The HTTP Status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON Response.
   */
  protected function jsonErrorResponseMessage($message, $status, $status_code = 400) {
    // If the Entity is not found in Content Hub then return a 404 Not Found.
    $json = array(
      'status' => $status,
      'message' => $message,
    );
    return new JsonResponse($json, $status_code);
  }

}