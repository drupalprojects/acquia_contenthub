<?php
/**
 * @file
 * Import Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\EntityManager as EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\acquia_contenthub\ContentHubImportedEntities;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Component\Uuid\Uuid;

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
   * @param string $uuid
   *   The Entity's UUID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the UUID of the entity does not exist in Content Hub.
   * @throws \Exception
   *   When the entity cannot be saved.
   */
  public function saveDrupalEntity($uuid) {

    // Checking that the parameter given is a UUID.
    if (!Uuid::isValid($uuid)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    $contenthub_entity = $this->entityManager->loadRemoteEntity($uuid);
    $origin = $contenthub_entity->getOrigin();
    $site_origin = $this->contentHubImportedEntities->getSiteOrigin();

    // Checking that the entity origin is different than this site origin.
    if ($origin == $site_origin) {
      $args = array(
        '%type' => $contenthub_entity->getType(),
        '%uuid' => $contenthub_entity->getUuid(),
        '%origin' => $origin,
      );
      $message = new FormattableMarkup('Cannot save %type entity with uuid=%uuid. It has the same origin as this site: %origin', $args);
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
      $result = FALSE;
      return new JsonResponse($result);
    }

    // Import the entity.
    $entity_type = $contenthub_entity->getType();
    $class = \Drupal::entityTypeManager()->getDefinition($entity_type)->getClass();

    try {
      $entity = $this->serializer->deserialize($contenthub_entity->json(), $class, $this->format);
    }
    catch (UnexpectedValueException $e) {
      $error['error'] = $e->getMessage();
      $content = $this->serializer->serialize($error, 'json');
      return new Response($content, 400, array('Content-Type' => 'json'));
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

}
