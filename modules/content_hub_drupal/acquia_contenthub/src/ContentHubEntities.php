<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub/ContentHubEntities.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Core\Entity\EntityInterface;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\acquia_contenthub\EntityManager as EntityManager;

/**
 * Prepares the entities for bulk upload.
 */
class ContentHubEntities {
  const BULK_UPLOAD_INSERT = 'BULK_UPLOAD_INSERT';
  const BULK_UPLOAD_UPDATE = 'BULK_UPLOAD_UPDATE';

  /**
   * The operational mode: 'insert' or 'update'.
   *
   * @var string $mode
   */
  protected $mode;

  /**
   * The ContentHubCache Storage.
   *
   * The information about the entities for this set is stored in the cache.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCache $storage
   *   The Content Hub Cache record storing this information.
   */
  protected $storage;

  /**
   * The submission UUID.
   *
   * @var string $uuid
   *   A UUID string.
   */
  protected $uuid;

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * The Content Hub Entity Manager.
   *
   * @var \Drupal\acquia_contenthub\EntityManager
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.entity_manager')
    );
  }

  /**
   * Public constructor.
   *
   * @param string $action
   *   Defines the operational mode.
   * @param string|void $origin
   *   Defines the site origin's UUID.
   */
  public function __construct($action = 'UPDATE', $uuid = NULL, $origin = NULL, ClientManagerInterface $client_manager, EntityManager $entity_manager) {
    // Assigning the mode.
    $this->setMode($action);

    // Generate the UUID for this upload set.
    if (isset($uuid)) {
      $this->setUuid($uuid);
    }
    else {
      $this->uuid = uuid_generate();
    }

    // Reading entities stored in drupal variable.
    if (!$cache = ContentHubCache::load($this->getMode(), $this->getUuid())) {
      $json = json_encode(array(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
      $cache = new ContentHubCache($this->getMode(), $this->getUuid(), $json);
    }
    $this->storage = $cache;

    $this->clientManager = $client_manager;
    $this->entityManager = $entity_manager;
  }

  /**
   * Loads a ContentHubEntities Object from an existing UUID, FALSE otherwise.
   *
   * @param string $uuid
   *   The UUID of set.
   *
   * @return bool|\Drupal\acquia_contenthub\ContentHubEntities
   *   The ContentHubEntities object, if found, FALSE otherwise.
   */
  static public function load($uuid) {
    $contenthub_entities = new ContentHubEntities('UPDATE', $uuid);
    if (count($contenthub_entities->getEntities()) <= 0) {
      $contenthub_entities = new ContentHubEntities('INSERT', $uuid);
      if (count($contenthub_entities->getEntities()) <= 0) {
        return FALSE;
      }
    }
    return $contenthub_entities;
  }

  /**
   * Sets the mode.
   *
   * @param string $action
   *   Could be either 'INSERT' or 'UPDATE'.
   */
  public function setMode($action) {
    switch ($action) {
      case 'INSERT':
        $this->mode = self::BULK_UPLOAD_INSERT;
        break;

      case 'UPDATE':
      default:
        $this->mode = self::BULK_UPLOAD_UPDATE;
        break;
    }
  }

  /**
   * Returns the Mode of Operation.
   *
   * @return string
   *   either BULK_
   */
  public function getMode() {
    return $this->mode;
  }

  /**
   * Obtains the Submission UUID.
   *
   * @return string
   *   The Submission's UUID.
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * Sets the Submission UUID.
   *
   * @param string $uuid
   *   The UUID of this submission.
   */
  protected function setUuid($uuid) {
    $this->uuid = $uuid;
  }

  /**
   * Returns the local Resource URL.
   *
   * This is an absolute URL, which base_url can be overwritten with the
   * variable 'acquia_contenthub_rewrite_localdomain', which is especially
   * useful in cases where the Content Hub module is installed in a Drupal site
   * that is running locally (not from the public internet).
   *
   * @return string|bool
   *   The absolute resource URL, if it can be generated, FALSE otherwise.
   */
  public function getResourceUrl(EntityInterface $entity) {
    // Check if there are link templates defined for the entity type and
    // use the path from the route instead of the default.
    $entity_type = $entity->getEntityType();
    $entity_type_id = $entity->getEntityTypeId();
    $path = '/content-hub/bulk_upload/' . $entity->uuid();

//    $route_name = 'acquia_contenthub.entity.' . $entity_type_id . '.GET.acquia_contenthub_cdf';
//    $url_options = array(
//      'entity_type' => $entity_type_id,
//      $entity_type_id => $entity->id(),
//      '_format' => 'acquia_contenthub_cdf',
//    );
//
//    $url = Url::fromRoute($route_name, $url_options);
//    $path = $url->toString();

    // Get the content hub config settings.
    $rewrite_localdomain = $this->configFactory
      ->get('acquia_contenthub.admin_settings')
      ->get('rewrite_domain');

    if ($rewrite_localdomain) {
      $url = Url::fromUri($rewrite_localdomain . $path);
    }
    else {
      $url = Url::fromUri($this->baseRoot . $path);
    }
    return $url->toUriString();
  }

  /**
   * Gets the Json file from the UUID.
   *
   * @param string $uuid
   *   The submission's UUID.
   *
   * @return string
   *   The JSON string that will be submitted to the Content Hub.
   */
  static public function getJson($uuid) {
    if (FALSE !== $contenthub_entities = self::load($uuid)) {
      return $contenthub_entities->json();
    }
    else {
      return '{ "entities" : []}';
    }
  }

  /**
   * Returns the entities.
   *
   * @return array
   *   An array of UUIDs to be submitted.
   */
  public function getEntities() {
    $json = $this->storage->getJson();
    $entities = json_decode($json, TRUE);
    return $entities;
  }

  /**
   * Saves the Entities that will be queued for upload.
   *
   * @param array $entities
   *   The entities array.
   */
  protected function saveEntities(array $entities) {
    $json = json_encode($entities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $this->storage->setJson($json);
    $this->storage->save();
  }

  /**
   * Returns the number of entities queued for a specific mode.
   *
   * @return int
   *   Counts the number of entities queued for submission.
   */
  protected function count() {
    $entities = $this->getEntities();
    return count($entities);
  }

  /**
   * Adds an Entity and its dependencies to the bulk upload pool.
   *
   * It also performs the check whether the dependencies can be added to the
   * bulk upload pool (if they have been selected in the Entity Configuration
   * Page).
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntity $contenthub_entity
   *   A Content Hub Entity object.
   * @param bool|TRUE $include_dependencies
   *   TRUE if we want to add all its dependencies, FALSE otherwise.
   *   Defaults to TRUE.
   *
   * @return array
   *   The dependencies that could NOT be added to the bulk upload pool.
   */
  public function addEntity(ContentHubEntity $contenthub_entity, $include_dependencies = TRUE) {
    $entities = $this->getEntities();
    $failed_entities = array();

    // Only add entities that haven't been added yet.
    if (!in_array($contenthub_entity->getUuid(), array_keys($entities))) {

      // Should we add dependencies?
      if ($include_dependencies) {
        $dependencies = array();
        $dependencies = $contenthub_entity->getAllLocalDependencies($dependencies, TRUE);

        // At this point we will NOT care if it is a pre/post dependency. We
        // will just upload ALL of them into the same bulk upload request.
        foreach ($dependencies as $uuid => $dependent_entity) {
          // Check whether we are allowed to add this entity.
          if ($this->entityManager->isEligibleEntity($dependent_entity->getDrupalEntity(), $dependent_entity->getDrupalEntityType()) !== FALSE) {

            // Also, check if this dependency has been previously added.
            if (!in_array($dependent_entity->getUuid(), array_keys($entities))) {
              $this->saveEntity($dependent_entity);
            }
          }
          else {
            // Collect all the dependencies that can NOT be uploaded because
            // they do not comply with what has been selected in the Entity
            // Configuration Page or they did NOT originate from this site.
            // We are ALSO explicitly excluding 'user' entities.
            if ($dependent_entity->getDrupalEntityType() !== 'user') {
              $failed_entities[$uuid] = $dependent_entity;
            }
          }
        }
      }

      // Finally add this entity itself.
      // Note that we do NOT need to verify that this entity IS a Content Hub
      // Entity because we assume this has been previously been checked.
      $this->saveEntity($contenthub_entity);
    }

    return $failed_entities;
  }

  /**
   * Saves an Entity into the ContentHubCache.
   *
   * @param ContentHubEntity $contenthub_entity
   *   Adds the json representation of the entity to the Content Hub Cache.
   */
  public function saveEntity(ContentHubEntity $contenthub_entity) {
    // Touching the Modified flag.
    $contenthub_entity->touchModified();

    // Saving Entity in the ContentHubCache.
    $contenthub_cache = new \Drupal\acquia_contenthub\ContentHubCache(
      $contenthub_entity->getCdf()->getType(),
      $contenthub_entity->getUuid(),
      $contenthub_entity->getJson()
    );
    $contenthub_cache->save();

    // Save entity into the variable.
    $entities = $this->getEntities();
    $entities += array(
      $contenthub_entity->getUuid() => $contenthub_entity->getCdf()->getType(),
    );
    $this->saveEntities($entities);

  }

  /**
   * Submits all the entities to Content Hub.
   */
  public function send() {
    switch ($this->mode) {
      case self::BULK_UPLOAD_INSERT:
        return $this->createRemoteEntities();

      case self::BULK_UPLOAD_UPDATE:
        return $this->updateRemoteEntities();
    }
  }

  /**
   * Sends the entities for insert to Content Hub.
   */
  protected function createRemoteEntities() {
    if ($response = $this->createRequest('createEntities', array($this->getResourceUrl()))) {
      $response = $response->json();
    }
    return empty($response['success']) ? FALSE : TRUE;
  }

  /**
   * Sends the entities for update to Content Hub.
   */
  protected function updateRemoteEntities() {
    if ($response = $this->createRequest('updateEntities', array($this->getResourceUrl()))) {
      $response = $response->json();
    }
    return empty($response['success']) ? FALSE : TRUE;
  }

  /**
   * Generate the json for the different entities to send to Content Hub.
   *
   * @return string
   *   The JSON string to be submitted to the Content Hub.
   */
  protected function json() {
    $entities = $this->getEntities();
    $json_pieces = array();
    foreach ($entities as $uuid => $type) {
      // Try to load the entity from the Content Hub cache.
      if ($contenthub_cache = ContentHubCache::load($type, $uuid)) {
        $json_pieces[] = $contenthub_cache->getJson();
      }
      else {
        // Entity is not in the Content Hub cache, then convert from drupal.
        $contenthub_entity = new ContentHubEntity();
        $contenthub_entity->loadDrupalEntity($type, $uuid);
        $contenthub_entity->touchModified();
        $json_pieces[] = $contenthub_entity->getJson();
      }
    }

    $json = '{ "entities" : [' . implode(',', $json_pieces) . ']}';
    return $json;
  }
}
