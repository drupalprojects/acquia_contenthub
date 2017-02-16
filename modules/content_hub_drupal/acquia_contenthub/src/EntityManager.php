<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\EntityManager.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Utility\UrlHelper;

/**
 * Provides a service for managing entity actions for Content Hub.
 *
 * @TODO To be renamed to "ExportEntityManager".
 */
class EntityManager {

  /**
   * Base root.
   *
   * @var string
   */
  protected $baseRoot;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * The Content Hub Entities Tracking Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   */
  protected $contentHubEntitiesTracking;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfoManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_entities_tracking'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *    The config factory.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubEntitiesTracking $acquia_contenthub_entities_tracking
   *    The Content Hub Entities Tracking.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *    The Entity Type Manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info_manager
   *    The Entity Type Bundle Info Manager.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory, ClientManagerInterface $client_manager, ContentHubEntitiesTracking $acquia_contenthub_entities_tracking, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info_manager) {
    $this->baseRoot = isset($GLOBALS['base_root']) ? $GLOBALS['base_root'] : '';
    $this->loggerFactory = $logger_factory;
    $this->clientManager = $client_manager;
    $this->contentHubEntitiesTracking = $acquia_contenthub_entities_tracking;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfoManager = $entity_type_bundle_info_manager;
    // Get the content hub config settings.
    $this->config = $config_factory->get('acquia_contenthub.admin_settings');
  }

  /**
   * Executes an action in the Content Hub on a selected drupal entity.
   *
   * @param object $entity
   *   The Drupal Entity object.
   * @param string $action
   *   The action to perform on that entity: 'INSERT', 'UPDATE', 'DELETE'.
   */
  public function entityAction($entity, $action) {
    $type = $entity->getEntityTypeId();
    // Checking if the entity has already been synchronized so not to generate
    // an endless loop.
    if (isset($entity->__contenthub_synchronized)) {
      return;
    }

    // Comparing entity's origin with site's origin.
    $origin = $this->config->get('origin');
    if (isset($entity->__content_hub_origin) && $entity->__content_hub_origin !== $origin) {
      unset($entity->__content_hub_origin);
      return;
    }

    // Entity has not been sync'ed, then proceed with it.
    if (!$this->isEligibleEntity($entity)) {
      return;
    }

    // Handle node specifically.
    if ($type == 'node') {
      switch ($action) {
        case 'INSERT':
          if (!$entity->isPublished()) {
            // Do not push nodes that are unpublished to the Content Hub.
            return;
          }
          break;

        case 'UPDATE':
          if (!$entity->isPublished()) {
            // If a node is unpublished, then delete it from the Content Hub.
            $action = 'DELETE';
          }
          break;

        case 'DELETE':
          // Do nothing, proceed with deletion.
          break;
      }
    }

    // Handle entity delete specifically.
    if ($action === 'DELETE') {
      $this->entityActionSend($entity, $action);
      return;
    }

    // Collect all entities and make internal page request.
    $item = array(
      'uuid' => $entity->uuid(),
      'type' => $type,
      'action' => $action,
      'entity' => $entity,
    );
    $this->collectExportEntities($item);

    // Registering shutdown function to send entities to Acquia Content Hub.
    $acquia_contenthub_shutdown_function = 'acquia_contenthub_send_entities';
    $callbacks = drupal_register_shutdown_function();
    $callback_functions = array_column($callbacks, 'callback');
    if (!in_array($acquia_contenthub_shutdown_function, $callback_functions)) {
      drupal_register_shutdown_function($acquia_contenthub_shutdown_function);
    }
  }

  /**
   * Gathers all entities that will be exported.
   *
   * @param object|null $entity
   *   The Entity that will be exported.
   *
   * @return array
   *   The array of entities to export.
   */
  public function collectExportEntities($entity = NULL) {
    $entities = &drupal_static(__METHOD__);
    if (!isset($entities)) {
      $entities = array();
    }
    if (is_array($entity)) {
      $uuids = array_column($entities, 'uuid');
      if (!in_array($entity['uuid'], $uuids)) {
        $entities[$entity['uuid']] = $entity;
      }
    }
    return $entities;
  }

  /**
   * Tracks the number of entities that fail to bulk upload.
   *
   * @param string $num
   *   Number of failed entities added to the pool.
   *
   * @return string $total
   *   The total number of entities that failed to bulk upload.
   */
  public function entityFailures($num = NULL) {
    $total = &drupal_static(__METHOD__);
    if (!isset($total)) {
      $total = is_int($num) ? $num : 0;
    }
    else {
      $total = is_int($num) ? $total + $num : $total;
    }
    return $total;
  }

  /**
   * Sends the entities for update to Content Hub.
   *
   * @param string $resource_url
   *   The Resource Url.
   *
   * @return bool
   *   Returns the response.
   */
  public function updateRemoteEntities($resource_url) {
    if ($response = $this->clientManager->createRequest('updateEntities', array($resource_url))) {
      $response = json_decode($response->getBody(), TRUE);
    }
    return empty($response['success']) ? FALSE : TRUE;
  }

  /**
   * Sends the request to the Content Hub for a single entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Content Hub Entity.
   * @param string $action
   *   The action to execute for bulk upload: 'INSERT' or 'UPDATE'.
   */
  public function entityActionSend(EntityInterface $entity, $action) {
    /** @var \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager */
    try {
      $client = $this->clientManager->getConnection();
    }
    catch (ContentHubException $e) {
      $this->loggerFactory->get('acquia_contenthub')->error($e->getMessage());
      return;
    }

    $resource_url = $this->getResourceUrl($entity);
    if (!$resource_url) {
      $args = array(
        '%type' => $entity->getEntityTypeId(),
        '%uuid' => $entity->uuid(),
        '%id' => $entity->id(),
      );
      $message = new FormattableMarkup('Error trying to form a unique resource Url for %type with uuid %uuid and id %id', $args);
      $this->loggerFactory->get('acquia_contenthub')->error($message);
      return;
    }

    $response = NULL;
    $args = array(
      '%type' => $entity->getEntityTypeId(),
      '%uuid' => $entity->uuid(),
      '%id' => $entity->id(),
    );
    $message_string = 'Error trying to post the resource url for %type with uuid %uuid and id %id with a response from the API: %error';

    switch ($action) {
      case 'INSERT':
        try {
          $response = $client->createEntities($resource_url);
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
          $args['%error'] = $e->getMessage();
          $message = new FormattableMarkup($message_string, $args);
          $this->loggerFactory->get('acquia_contenthub')->error($message);
          return;
        }
        break;

      case 'UPDATE':
        try {
          $response = $client->updateEntity($resource_url, $entity->uuid());
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
          $args['%error'] = $e->getMessage();
          $message = new FormattableMarkup($message_string, $args);
          $this->loggerFactory->get('acquia_contenthub')->error($message);
          return;
        }
        break;

      case 'DELETE':
        try {
          $response = $client->deleteEntity($entity->uuid());
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
          $args['%error'] = $e->getMessage();
          $message = new FormattableMarkup($message_string, $args);
          $this->loggerFactory->get('acquia_contenthub')->error($message);
          return;
        }
        break;
    }
    // Make sure it is within the 2XX range. Expected response is a 202.
    if ($response->getStatusCode()[0] == '2' && $response->getStatusCode()[1] == '0') {
      $message = new FormattableMarkup($message_string, $args);
      $this->loggerFactory->get('acquia_contenthub')->error($message);
    }
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
  public function getResourceUrl(EntityInterface $entity, $include_references = 'true') {
    // Check if there are link templates defined for the entity type and
    // use the path from the route instead of the default.
    $entity_type_id = $entity->getEntityTypeId();

    $route_name = 'acquia_contenthub.entity.' . $entity_type_id . '.GET.acquia_contenthub_cdf';
    $url_options = array(
      'entity_type' => $entity_type_id,
      $entity_type_id => $entity->id(),
      '_format' => 'acquia_contenthub_cdf',
      'include_references' => $include_references,
    );

    return $this->getResourceUrlByRouteName($route_name, $url_options);
  }

  /**
   * Returns the route's resource URL.
   *
   * @param string $route_name
   *   Route name.
   * @param array $url_options
   *   Bulk-upload Url query params.
   *
   * @return string
   *   returns URL.
   */
  protected function getResourceUrlByRouteName($route_name, $url_options = array()) {
    $url = Url::fromRoute($route_name, $url_options);
    $path = $url->toString();

    // Get the content hub config settings.
    $rewrite_localdomain = $this->config->get('rewrite_domain');

    if (UrlHelper::isExternal($path)) {
      // If for some reason the $path is an external URL, do not further
      // prefix a domain, and do not overwrite the given domain.
      $full_path = $path;
    }
    elseif ($rewrite_localdomain) {
      $full_path = $rewrite_localdomain . $path;
    }
    else {
      $full_path = $this->baseRoot . $path;
    }
    $url = Url::fromUri($full_path);

    return $url->toUriString();
  }

  /**
   * Builds the bulk-upload url to make a single request.
   *
   * @param array $url_options
   *   Bulk-upload Url query params.
   *
   * @return string
   *   returns URL.
   */
  public function getBulkResourceUrl($url_options = array()) {
    $route_name = 'acquia_contenthub.acquia_contenthub_bulk_cdf';
    return $this->getResourceUrlByRouteName($route_name, $url_options);
  }

  /**
   * Checks whether the current entity should be transferred to Content Hub.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return bool
   *   True if it can be parsed, False if it not a suitable entity for sending
   *   to content hub.
   */
  public function isEligibleEntity(EntityInterface $entity) {
    // Currently Content Hub does not support configuration entities to be
    // exported. Only content entities can be exported to Content Hub.
    if ($entity instanceof \Drupal\Core\Config\Entity\ConfigEntityInterface) {
      return FALSE;
    }

    $entity_type_id = $entity->getEntityTypeId();
    /** @var \Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface $entity_type_config */
    $entity_type_config = $this->getContentHubEntityTypeConfigurationEntity($entity_type_id);

    $bundle_id = $entity->bundle();
    if (empty($entity_type_config) || empty($entity_type_config->isEnableIndex($bundle_id))) {
      return FALSE;
    }

    // If the entity has been imported before, then it didn't originate from
    // this site and shouldn't be exported.
    if ($this->contentHubEntitiesTracking->loadImportedByDrupalEntity($entity->getEntityTypeId(), $entity->id()) !== FALSE) {
      // Is this an entity that does not belong to this site? Has it been
      // previously imported from Content Hub?
      $uuid = $entity->uuid();
      // We cannot bulk upload this entity because it does not belong to this
      // site. Add it to the pool of failed entities.
      if (isset($uuid)) {
        $this->entityFailures(1);
        $args = array(
          '%type' => $entity->getEntityTypeId(),
          '%uuid' => $entity->uuid(),
        );

        // We can use this pool of failed entities to display a message to the
        // user about the entities that failed to export.
        // $message = new FormattableMarkup('Cannot export %type entity with
        // UUID = %uuid to Content Hub because it was previously imported
        // (did not originate from this site).', $args);
        // $this->loggerFactory->get('acquia_contenthub')->error($message);
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks whether the current dependency should be transferred to Content Hub.
   *
   * Dependencies have an additional check as to whether they should be trans-
   * ferred to Content Hub. If they have been previously exported then they do
   * not need to be exported again. Dependent entities are those which are
   * referenced from an entity that has been fired through entity hooks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return bool
   *   TRUE if it is eligible for export to Content Hub, FALSE otherwise.
   */
  public function isEligibleDependency(EntityInterface $entity) {
    if ($this->isEligibleEntity($entity)) {
      if ($entity_tracking = $this->contentHubEntitiesTracking->loadExportedByUuid($entity->uuid())) {
        if ($entity_tracking->isExported()) {
          return FALSE;
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns the list of enabled entity types for Content Hub.
   *
   * @return string[]
   *   A list of enabled entity type IDs.
   */
  public function getContentHubEnabledEntityTypeIds() {
    /** @var \Drupal\acquia_contenthub\Entity\ContentHubEntityTypeConfig[] $entity_type_ids */
    $entity_type_ids = $this->getContentHubEntityTypeConfigurationEntities();

    $enabled_entity_type_ids = [];
    foreach ($entity_type_ids as $entity_type_id => $entity_type_config) {
      $bundles = $entity_type_config->getBundles();

      // For a type to be enabled, it must at least have one bundle enabled.
      if (!empty(array_filter(array_column($bundles, 'enable_index')))) {
        $enabled_entity_type_ids[] = $entity_type_id;
      }
    }
    return $enabled_entity_type_ids;
  }

  /**
   * Returns the Content Hub configuration entity for this entity type.
   *
   * @param string $entity_type_id
   *   The Entity type ID.
   *
   * @return bool|\Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface
   *   The Configuration entity if exists, FALSE otherwise.
   */
  public function getContentHubEntityTypeConfigurationEntity($entity_type_id) {
    /** @var \Drupal\rest\RestResourceConfigInterface $contenthub_entity_config_storage */
    $contenthub_entity_config_storage = $this->entityTypeManager->getStorage('acquia_contenthub_entity_config');

    /** @var \Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface[] $contenthub_entity_config_ids */
    $contenthub_entity_config_ids = $contenthub_entity_config_storage->loadMultiple(array($entity_type_id));
    $contenthub_entity_config_id = isset($contenthub_entity_config_ids[$entity_type_id]) ? $contenthub_entity_config_ids[$entity_type_id] : FALSE;
    return $contenthub_entity_config_id;
  }

  /**
   * Returns the list of configured Content Hub configuration entities.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface[]
   *   An array of Content Hub Configuration entities
   */
  public function getContentHubEntityTypeConfigurationEntities() {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $contenthub_entity_config_storage */
    $contenthub_entity_config_storage = $this->entityTypeManager->getStorage('acquia_contenthub_entity_config');

    /** @var \Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface[] $contenthub_entity_config_ids */
    $contenthub_entity_config_ids = $contenthub_entity_config_storage->loadMultiple();
    return $contenthub_entity_config_ids;
  }

  /**
   * Obtains the list of entity types.
   */
  public function getAllowedEntityTypes() {
    // List of entities that are excluded from displaying on
    // entity configuration page and will not be pushed to Content Hub.
    $excluded_types = [
      'comment',
      'user',
      'contact_message',
      'shortcut',
      'menu_link_content',
      'user',
    ];

    $types = $this->entityTypeManager->getDefinitions();
    $entity_types = array();
    foreach ($types as $type => $entity) {
      // We only support content entity types at the moment, since config
      // entities don't implement \Drupal\Core\TypedData\ComplexDataInterface.
      if ($entity instanceof ContentEntityTypeInterface) {
        // Skip excluded types.
        if (in_array($type, $excluded_types)) {
          continue;
        }
        $bundles = $this->entityTypeBundleInfoManager->getBundleInfo($type);

        // Here we need to load all the different bundles?
        if (isset($bundles) && count($bundles) > 0) {
          foreach ($bundles as $key => $bundle) {
            $entity_types[$type][$key] = $bundle['label'];
          }
        }
      }
    }
    $entity_types = array_diff_key($entity_types, $excluded_types);
    return $entity_types;
  }

}
