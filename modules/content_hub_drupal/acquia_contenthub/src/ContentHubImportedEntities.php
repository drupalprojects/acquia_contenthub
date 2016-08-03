<?php
/**
 * @file
 * Keeps track of all Content Hub Imported Entities.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Uuid\Uuid;

/**
 * Tracks in a table the list of all entities imported from Content Hub.
 */
class ContentHubImportedEntities {

  const TABLE                    = 'acquia_contenthub_imported_entities';
  const AUTO_UPDATE_ENABLED      = 'ENABLED';
  const AUTO_UPDATE_DISABLED     = 'DISABLED';
  const AUTO_UPDATE_LOCAL_CHANGE = 'LOCAL_CHANGE';

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The specific content hub keys.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $contentHubAdminConfig;

  /**
   * The Imported Entity Record.
   *
   * @var object
   */
  protected $importedEntity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory')
    );
  }

  /**
   * TableSortExampleController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->contentHubAdminConfig = $config_factory->get('acquia_contenthub.admin_settings');

    // Making sure we reset the imported entity so we can load it again.
    $this->reset();
  }

  /**
   * Resets the Imported Entity Information.
   */
  protected function reset() {
    $this->importedEntity = NULL;
  }

  /**
   * Explicitly sets the Imported Entity.
   *
   * @param string $entity_type
   *   The Entity Type.
   * @param int $entity_id
   *   The Entity ID.
   * @param string $entity_uuid
   *   The Entity UUID.
   * @param string $auto_update
   *   The auto_update flag.
   * @param string $origin
   *   The origin UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubImportedEntities
   *   This same object.
   */
  public function setImportedEntity($entity_type, $entity_id, $entity_uuid, $auto_update, $origin) {
    $this->importedEntity = (object) [
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      'uuid' => $entity_uuid,
      'auto_update' => $auto_update,
      'origin' => $origin,
    ];
    return $this;
  }

  /**
   * Returns the Imported Entity object.
   *
   * @return object
   *   The Imported Entity object.
   */
  public function getImportedEntity() {
    return $this->importedEntity;
  }

  /**
   * Returns the Entity ID.
   *
   * @return int
   *   The Entity ID.
   */
  public function getEntityId() {
    return isset($this->getImportedEntity()->entity_id) ? $this->getImportedEntity()->entity_id : NULL;
  }

  /**
   * Returns the Entity Type.
   *
   * @return string
   *   The Entity Type.
   */
  public function getEntityType() {
    return isset($this->getImportedEntity()->entity_type) ? $this->getImportedEntity()->entity_type : NULL;
  }

  /**
   * Returns the Entity's UUID.
   *
   * @return string
   *   The Entity's UUID.
   */
  public function getUuid() {
    return isset($this->getImportedEntity()->uuid) ? $this->getImportedEntity()->uuid : NULL;
  }

  /**
   * Returns the auto_update flag.
   *
   * @return string
   *   The autoUpdate flag.
   */
  public function getAutoUpdate() {
    return isset($this->getImportedEntity()->auto_update) ? $this->getImportedEntity()->auto_update : NULL;
  }

  /**
   * Sets the auto_update flag.
   *
   * @param string $auto_update
   *   Could be ENABLED, DISABLED or LOCAL_CHANGE.
   *
   * @return \Drupal\acquia_contenthub\ContentHubImportedEntities|bool
   *   This ContentHubImportedEntities object if succeeds, FALSE otherwise.
   */
  public function setAutoUpdate($auto_update) {
    $accepted_values = array(
      self::AUTO_UPDATE_ENABLED,
      self::AUTO_UPDATE_DISABLED,
      self::AUTO_UPDATE_LOCAL_CHANGE,
    );
    if (in_array($auto_update, $accepted_values)) {
      $this->getImportedEntity()->auto_update = $auto_update;
      return $this;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns the Origin.
   *
   * @return int|string
   *   The Origin.
   */
  public function getOrigin() {
    return isset($this->getImportedEntity()->origin) ? $this->getImportedEntity()->origin : NULL;
  }

  /**
   * Return this site's origin.
   *
   * @return array|mixed|null
   *   The UUID of this site's origin.
   */
  public function getSiteOrigin() {
    return $this->contentHubAdminConfig->get('origin');
  }

  /**
   * Saves a record of an imported entity.
   *
   * @return bool
   *   TRUE if saving is successful, FALSE otherwise.
   */
  public function save() {
    $site_origin = $this->contentHubAdminConfig->get('origin');
    $success = FALSE;
    $valid_input = Uuid::isValid($this->getUuid()) && Uuid::isValid($this->getOrigin()) && !empty($this->getEntityType()) && !empty($this->getEntityId());
    $valid_input = $valid_input &&  in_array($this->getAutoUpdate(), array(
      self::AUTO_UPDATE_ENABLED,
      self::AUTO_UPDATE_DISABLED,
      self::AUTO_UPDATE_LOCAL_CHANGE,
    ));
    if ($valid_input && $this->getOrigin() !== $site_origin) {
      $result = $this->database->merge(self::TABLE)
        ->key(array(
          'entity_id' => $this->getEntityId(),
          'entity_type' => $this->getEntityType(),
          'entity_uuid' => $this->getUuid(),
        ))
        ->fields(array(
          'auto_update' => $this->getAutoUpdate(),
          'origin' => $this->getOrigin(),
        ))
        ->execute();

      switch ($result) {
        case \Drupal\Core\Database\Query\Merge::STATUS_INSERT:
        case \Drupal\Core\Database\Query\Merge::STATUS_UPDATE:
          $success = TRUE;
          break;

        default:
          $success = FALSE;
          break;
      }
    }

    return $success;
  }

  /**
   * Loads a record using Drupal entity key information.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubImportedEntities|bool
   *   This ContentHubImportedEntities object if succeeds, FALSE otherwise.
   */
  public function loadByDrupalEntity($entity_type, $entity_id) {
    $this->reset();
    $result = $this->database->select(self::TABLE, 'ci')
      ->fields('ci')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->execute()
      ->fetchAssoc();

    if ($result) {
      $this->setImportedEntity($result['entity_type'], $result['entity_id'], $result['entity_uuid'], $result['auto_update'], $result['origin']);
      return $this;
    }

    return FALSE;
  }

  /**
   * Loads a record using an Entity's UUID.
   *
   * @param string $entity_uuid
   *   The entity's UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubImportedEntities|bool
   *   This ContentHubImportedEntities object if succeeds, FALSE otherwise.
   */
  public function loadByUuid($entity_uuid) {
    $this->reset();
    if (Uuid::isValid($entity_uuid)) {
      $result = $this->database->select(self::TABLE, 'ci')
        ->fields('ci')
        ->condition('entity_uuid', $entity_uuid)
        ->execute()
        ->fetchAssoc();

      if ($result) {
        $this->setImportedEntity($result['entity_type'], $result['entity_id'], $result['entity_uuid'], $result['auto_update'], $result['origin']);
        return $this;
      }

    }
    return FALSE;
  }

  /**
   * Obtains a list of all imported entities that match a certain origin.
   *
   * @param string $origin
   *   The origin UUID.
   *
   * @return array
   *   An array containing the list of imported entities from a certain origin.
   */
  public function getFromOrigin($origin) {
    if (Uuid::isValid($origin)) {
      return $this->database->select(self::TABLE, 'ci')
        ->fields('ci')
        ->condition('origin', $origin)
        ->execute()
        ->fetchAll();
    }
    return array();
  }

}
