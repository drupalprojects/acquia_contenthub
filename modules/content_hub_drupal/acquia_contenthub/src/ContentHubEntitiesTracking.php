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
class ContentHubEntitiesTracking {

  const TABLE                    = 'acquia_contenthub_entities_tracking';

  // Import Status Values.
  const AUTO_UPDATE_ENABLED      = 'AUTO_UPDATE_ENABLED';
  const AUTO_UPDATE_DISABLED     = 'AUTO_UPDATE_DISABLED';
  const AUTO_UPDATE_LOCAL_CHANGE = 'LOCAL_CHANGE';

  // Export Status Values.
  const INITIATED                = 'INITIATED';
  const EXPORTED                 = 'EXPORTED';
  const CONFIRMED                = 'CONFIRMED';

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
   * The Tracking Entity Record.
   *
   * @var object
   */
  protected $trackingEntity;

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
   * Resets the Tracking Entity Information.
   */
  protected function reset() {
    $this->trackingEntity = NULL;
  }

  /**
   * Explicitly sets the Tracking Entity.
   *
   * @param string $entity_type
   *   The Entity Type.
   * @param int $entity_id
   *   The Entity ID.
   * @param string $entity_uuid
   *   The Entity UUID.
   * @param string $status_export
   *   The Export Status.
   * @param string $status_import
   *   The Import Status.
   * @param string $modified
   *   The CDF's modified timestamp.
   * @param string $origin
   *   The origin UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   *   This same object.
   */
  public function setTrackingEntity($entity_type, $entity_id, $entity_uuid, $status_export, $status_import, $modified, $origin) {
    $this->trackingEntity = (object) [
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      'entity_uuid' => $entity_uuid,
      'status_export' => $status_export,
      'status_import' => $status_import,
      'modified' => $modified,
      'origin' => $origin,
    ];
    return $this;
  }

  /**
   * Helper function to set the Exported Tracking Entity.
   *
   * @param string $entity_type
   *   The Entity Type.
   * @param int $entity_id
   *   The Entity ID.
   * @param string $entity_uuid
   *   The Entity UUID.
   * @param string $status_export
   *   The Export Status.
   * @param string $modified
   *   The CDF's modified timestamp.
   * @param string $origin
   *   The origin UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   *   This same object.
   */
  public function setExportedEntity($entity_type, $entity_id, $entity_uuid, $status_export, $modified, $origin) {
    return $this->setTrackingEntity($entity_type, $entity_id, $entity_uuid, $status_export, '', $modified, $origin);
  }

  /**
   * Helper function to set the Imported Tracking Entity.
   *
   * @param string $entity_type
   *   The Entity Type.
   * @param int $entity_id
   *   The Entity ID.
   * @param string $entity_uuid
   *   The Entity UUID.
   * @param string $status_import
   *   The Import Status.
   * @param string $modified
   *   The CDF's modified timestamp.
   * @param string $origin
   *   The origin UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   *   This same object.
   */
  public function setImportedEntity($entity_type, $entity_id, $entity_uuid, $status_import, $modified, $origin) {
    return $this->setTrackingEntity($entity_type, $entity_id, $entity_uuid, '', $status_import, $modified, $origin);
  }

  /**
   * Returns the Imported Entity object.
   *
   * @return object
   *   The Imported Entity object.
   */
  public function getTrackingEntity() {
    return $this->trackingEntity;
  }

  /**
   * Returns the Entity ID.
   *
   * @return int
   *   The Entity ID.
   */
  public function getEntityId() {
    return isset($this->getTrackingEntity()->entity_id) ? $this->getTrackingEntity()->entity_id : NULL;
  }

  /**
   * Returns the Entity Type.
   *
   * @return string
   *   The Entity Type.
   */
  public function getEntityType() {
    return isset($this->getTrackingEntity()->entity_type) ? $this->getTrackingEntity()->entity_type : NULL;
  }

  /**
   * Returns the Entity's UUID.
   *
   * @return string
   *   The Entity's UUID.
   */
  public function getUuid() {
    return isset($this->getTrackingEntity()->entity_uuid) ? $this->getTrackingEntity()->entity_uuid : NULL;
  }

  /**
   * Returns the Export Status.
   *
   * @return string
   *   The Export Status.
   */
  public function getExportStatus() {
    return isset($this->getTrackingEntity()->status_export) ? $this->getTrackingEntity()->status_export : NULL;
  }

  /**
   * Returns the Import Status.
   *
   * @return string
   *   The Import Status.
   */
  public function getImportStatus() {
    return isset($this->getTrackingEntity()->status_import) ? $this->getTrackingEntity()->status_import : NULL;
  }

  /**
   * Returns the modified timestamp.
   *
   * @return string
   *   The modified timestamp.
   */
  public function getModified() {
    return isset($this->getTrackingEntity()->modified) ? $this->getTrackingEntity()->modified : NULL;
  }

  /**
   * Returns the Origin.
   *
   * @return int|string
   *   The Origin.
   */
  public function getOrigin() {
    return isset($this->getTrackingEntity()->origin) ? $this->getTrackingEntity()->origin : NULL;
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
   * Sets the Export Status.
   *
   * @param string $status_export
   *   Could be INITIATED, EXPORTED or CONFIRMED.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   This ContentHubEntitiesTracking object if succeeds, FALSE otherwise.
   */
  public function setExportStatus($status_export) {
    $accepted_values = array(
      self::INITIATED,
      self::EXPORTED,
      self::CONFIRMED,
    );
    if (in_array($status_export, $accepted_values)) {
      $this->getTrackingEntity()->status_export = $status_export;
      return $this;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Sets the Import Status.
   *
   * @param string $status_import
   *   Could be ENABLED, DISABLED or LOCAL_CHANGE.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   This ContentHubEntitiesTracking object if succeeds, FALSE otherwise.
   */
  public function setImportStatus($status_import) {
    $accepted_values = array(
      self::AUTO_UPDATE_ENABLED,
      self::AUTO_UPDATE_DISABLED,
      self::AUTO_UPDATE_LOCAL_CHANGE,
    );
    if (in_array($status_import, $accepted_values)) {
      $this->getTrackingEntity()->status_import = $status_import;
      return $this;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Sets the modified timestamp.
   *
   * @param string $modified
   *   Sets the modified timestamp.
   */
  public function setModified($modified) {
    $this->getTrackingEntity()->modified = $modified;
  }

  /**
   * Returns the tracking entity if it is an exported entity.
   *
   * @return ContentHubEntitiesTracking|bool
   *   This entity if it is an exported entity, FALSE otherwise.
   */
  protected function isExportedEntity() {
    // Try to set the export status to the same value it has. If it succeeds
    // then it has a valid export status.
    // Also, the import status has to be empty.
    if ($this->setExportStatus($this->getExportStatus()) && empty($this->getImportStatus())) {
      return $this;
    }
    return FALSE;
  }

  /**
   * Returns the tracking entity if it is an imported entity.
   *
   * @return ContentHubEntitiesTracking|bool
   *   This record if it is an imported entity, FALSE otherwise.
   */
  protected function isImportedEntity() {
    // Try to set the import status to the same value it has. If it succeeds
    // then it has a valid import status.
    // Also, the export status has to be empty.
    if ($this->setImportStatus($this->getImportStatus()) && empty($this->getExportStatus())) {
      return $this;
    }
    return FALSE;
  }

  /**
   * Saves a record of an imported entity.
   *
   * @return bool
   *   TRUE if saving is successful, FALSE otherwise.
   */
  public function save() {
    $site_origin = $this->contentHubAdminConfig->get('origin');
    $valid_input = Uuid::isValid($this->getUuid()) && Uuid::isValid($this->getOrigin()) && !empty($this->getEntityType()) && !empty($this->getEntityId());
    $valid_input_export = in_array($this->getExportStatus(), array(
      self::INITIATED,
      self::EXPORTED,
      self::CONFIRMED,
    ));
    $valid_input_import = in_array($this->getImportStatus(), array(
      self::AUTO_UPDATE_ENABLED,
      self::AUTO_UPDATE_DISABLED,
      self::AUTO_UPDATE_LOCAL_CHANGE,
    ));
    $valid_input = $valid_input && ($valid_input_export || $valid_input_import);

    // If we don't have a valid input, return FALSE.
    if (!$valid_input) {
      return FALSE;
    }

    // If we have a valid import status input but site origin is the same as the
    // entity origin then return FALSE.
    if ($valid_input_import && ($this->getOrigin() === $site_origin)) {
      return FALSE;
    }

    // If we have a valid status_import then status_export has to be empty
    // or the opposite.
    if (($valid_input_export && !empty($this->statusImport)) ||
      ($valid_input_import && !empty($this->statusExport))) {
      return FALSE;
    }

    // If we reached here then we have a valid input and can save safely.
    $result = $this->database->merge(self::TABLE)
      ->key(array(
        'entity_id' => $this->getEntityId(),
        'entity_type' => $this->getEntityType(),
        'entity_uuid' => $this->getUuid(),
      ))
      ->fields(array(
        'status_export' => $this->getExportStatus(),
        'status_import' => $this->getImportStatus(),
        'modified' => $this->getModified(),
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
    return $success;
  }

  /**
   * Deletes the entry for this particular entity.
   */
  public function delete() {
    if (!empty($this->getEntityType()) && !empty($this->getEntityId())) {
      return $this->database->delete(self::TABLE)
        ->condition('entity_type', $this->getEntityType())
        ->condition('entity_id', $this->getEntityId())
        ->execute();
    }
    elseif (Uuid::isValid($this->getUuid())) {
      return $this->database->delete(self::TABLE)
        ->condition('entity_uuid', $this->getUuid())
        ->execute();
    }
    return FALSE;
  }

  /**
   * Loads an Exported Entity tracking record by entity key information.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   The ContentHubEntitiesTracking object if it exists and is exported,
   *   FALSE otherwise.
   */
  public function loadExportedByDrupalEntity($entity_type, $entity_id) {
    if ($exported_entity = $this->loadByDrupalEntity($entity_type, $entity_id)) {
      return $exported_entity->isExportedEntity();
    }
    return FALSE;
  }

  /**
   * Loads an Imported Entity tracking record by entity key information.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   The ContentHubEntitiesTracking object if it exists and is imported,
   *   FALSE otherwise.
   */
  public function loadImportedByDrupalEntity($entity_type, $entity_id) {
    if ($imported_entity = $this->loadByDrupalEntity($entity_type, $entity_id)) {
      return $imported_entity->isImportedEntity();
    }
    return FALSE;
  }

  /**
   * Loads a record using Drupal entity key information.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   This ContentHubEntitiesTracking object if succeeds, FALSE otherwise.
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
      $this->setTrackingEntity($result['entity_type'], $result['entity_id'], $result['entity_uuid'], $result['status_export'], $result['status_import'], $result['modified'], $result['origin']);
      return $this;
    }

    return FALSE;
  }

  /**
   * Loads an Exported Entity tracking record by UUID.
   *
   * @param string $entity_uuid
   *   The entity uuid.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   The ContentHubEntitiesTracking object if it exists and is exported,
   *   FALSE otherwise.
   */
  public function loadExportedByUuid($entity_uuid) {
    if ($exported_entity = $this->loadByUuid($entity_uuid)) {
      return $exported_entity->isExportedEntity();
    }
    return FALSE;
  }

  /**
   * Loads an Imported Entity tracking record by UUID.
   *
   * @param string $entity_uuid
   *   The entity uuid.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   The ContentHubEntitiesTracking object if it exists and is imported,
   *   FALSE otherwise.
   */
  public function loadImportedByUuid($entity_uuid) {
    if ($imported_entity = $this->loadByUuid($entity_uuid)) {
      return $imported_entity->isImportedEntity();
    }
    return FALSE;
  }

  /**
   * Loads a record using an Entity's UUID.
   *
   * @param string $entity_uuid
   *   The entity's UUID.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking|bool
   *   This ContentHubEntitiesTracking object if succeeds, FALSE otherwise.
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
        $this->setTrackingEntity($result['entity_type'], $result['entity_id'], $result['entity_uuid'], $result['status_export'], $result['status_import'], $result['modified'], $result['origin']);
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
