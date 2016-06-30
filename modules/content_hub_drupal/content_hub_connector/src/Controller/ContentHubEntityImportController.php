<?php
/**
 * @file
 * Import Entity Controller.
 */

namespace Drupal\content_hub_connector\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\content_hub_connector\EntityManager as EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Logger\LoggerChannelFactory;
use Acquia\ContentHubClient\Entity as ChEntity;
use Drupal\content_hub_connector\ContentHubImportedEntities;

class ContentHubEntityImportController extends ControllerBase {

  const VALID_UUID = '[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}';

  protected $format = 'content_hub_cdf';

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

  protected $entity_manager;

  protected $serializer;

  /**
   * The Content Hub Imported Entities.
   *
   * @var \Drupal\content_hub_connector\ContentHubImportedEntities
   */
  protected $contentHubimportedEntities;

  public function __construct(Connection $database, LoggerChannelFactory $logger_factory, EntityManager $entity_manager, SerializerInterface $serializer, ContentHubImportedEntities $ch_imported_entities) {
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
    $this->entity_manager = $entity_manager;
    $this->serializer = $serializer;
    $this->contentHubimportedEntities = $ch_imported_entities;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('content_hub_connector.entity_manager'),
      $container->get('serializer'),
      $container->get('content_hub_connector.content_hub_imported_entities')
    );
  }

  /**
   * Validates the UUID.
   *
   * @param string $uuid
   *   A UUID String.
   *
   * @return bool
   *   TRUE if the string given is a UUID, FALSE otherwise.
   */
  static public function isUuid($uuid) {
    return (bool) preg_match('/^' . self::VALID_UUID . '$/', $uuid);
  }

  public function saveDrupalEntity($uuid) {

    // Checking that the parameter given is a UUID.
    if (!self::isUuid($uuid)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    $ch_entity = $this->entity_manager->loadRemoteEntity($uuid);
    $origin = $ch_entity->getOrigin();
    $site_origin = $this->contentHubimportedEntities->getSiteOrigin();

    // Checking that the entity origin is different than this site origin.
    if ($origin == $site_origin) {
      $args = array(
        '%type' => $ch_entity->getType(),
        '%uuid' => $ch_entity->getUuid(),
        '%origin' => $origin,
      );
      $message = new FormattableMarkup('Cannot save %type entity with uuid=%uuid. It has the same origin as this site: %origin', $args);
      $this->loggerFactory->get('content_hub_connector')->debug($message);
      $result = false;
      return new JsonResponse($result);
    }

    // Import the entity.
    $entity_type = $ch_entity->getType();
    $class = \Drupal::entityTypeManager()->getDefinition($entity_type)->getClass();

    try {
      $entity = $this->serializer->deserialize($ch_entity->json(), $class, $this->format);
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
      $entity->__content_hub_synchronized = TRUE;

      // Save the entity.
      $entity->save();

      // @TODO: Fix the auto_update flag be saved according to a value.
      $auto_update = \Drupal\content_hub_connector\ContentHubImportedEntities::AUTO_UPDATE_ENABLED;

      // Save this entity in the tracking for importing entities.
      $this->contentHubimportedEntities->setImportedEntity($entity->getEntityTypeId(), $entity->id(), $entity->uuid(), $auto_update, $origin);

      $args = array(
        '%type' => $entity->getEntityTypeId(),
        '%uuid' => $entity->uuid(),
        '%auto_update' => $auto_update,
      );

      if ($this->contentHubimportedEntities->save()) {
        $message = new FormattableMarkup('Saving %type entity with uuid=%uuid. Tracking imported entity with auto_update = %auto_update', $args);
        $this->loggerFactory->get('content_hub_connector')->debug($message);
      }
      else {
        $message = new FormattableMarkup('Saving %type entity with uuid=%uuid, but not tracking this entity in content_hub_imported_entities table because it could not be saved.', $args);
        $this->loggerFactory->get('content_hub_connector')->warning($message);
      }

    }
    catch (\Exception $e) {
      $transaction->rollback();
      $this->loggerFactory->get('content_hub_connector')->error($e->getMessage());
      throw $e;
    }

    $serialized_entity = $this->serializer->normalize($entity, 'json');
    return new JsonResponse($serialized_entity);

  }

}