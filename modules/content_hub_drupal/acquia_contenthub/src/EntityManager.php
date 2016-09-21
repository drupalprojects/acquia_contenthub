<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\EntityManager.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\acquia_contenthub\ContentHubImportedEntities;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Provides a service for managing entity actions for Content Hub.
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
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * The Content Hub Imported Entities Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubImportedEntities
   */
  protected $contentHubImportedEntities;

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
   * The Basic HTTP Kernel to make requests.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $kernel;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_imported_entities'),
      $container->get('acquia_contenthub.entity_manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('http_kernel.basic')
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
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory, ClientManagerInterface $client_manager, ContentHubImportedEntities $acquia_contenthub_imported_entities, EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info_manager, HttpKernelInterface $kernel) {
    global $base_root;
    $this->baseRoot = $base_root;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->clientManager = $client_manager;
    $this->contentHubImportedEntities = $acquia_contenthub_imported_entities;
    $this->entityTypeManager = $entity_manager;
    $this->entityTypeBundleInfoManager = $entity_type_bundle_info_manager;
    $this->kernel = $kernel;
    // Get the content hub config settings.
    $this->config = $this->configFactory->get('acquia_contenthub.admin_settings');
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
      unset($entity->__contenthub_synchronized);
      return;
    }

    // Comparing entity's origin with site's origin.
    $origin = $this->config->get('origin');
    if (isset($entity->__content_hub_origin) && $entity->__content_hub_origin !== $origin) {
      unset($entity->__content_hub_origin);
      return;
    }

    // Entity has not been sync'ed, then proceed with it.
    if ($this->isEligibleEntity($entity)) {
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

      if ($action !== 'DELETE') {
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
      else {
        $this->entityActionSend($entity, $action);
      }
    }
    else {
      // Entity has not been sync'ed, then proceed with it.
      // Is this an entity that does not belong to this site? Has it been
      // previously imported from Content Hub? Or was this entity type selected
      // in the Entity Configuration page?
      $uuid = $entity->uuid();
      // We cannot bulk upload this entity because it does not belong to this
      // site or it wasn't selected in the Entity Configuration Page.
      // Add it to the pool of failed entities.
      if (isset($uuid)) {
        $this->entityFailures(1);
        $args = array(
          '%type' => $type,
          '%uuid' => $entity->uuid(),
        );
        // We can use this pool of failed entities to display a message to the
        // user about the entities that failed to export.
        $message = new FormattableMarkup('Cannot export %type entity with UUID = %uuid to Content Hub because it was previously imported (did not originate from this site) or it wasn\'t selected in the Entity Configuration Page.', $args);
        $this->loggerFactory->get('acquia_contenthub')->error($message);
        return;
      }
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
    $entities = &drupal_static(__FUNCTION__);
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
    $total = &drupal_static(__FUNCTION__);
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
    $entity_type = $entity->getEntityType();
    $entity_type_id = $entity->getEntityTypeId();

    $route_name = 'acquia_contenthub.entity.' . $entity_type_id . '.GET.acquia_contenthub_cdf';
    $url_options = array(
      'entity_type' => $entity_type_id,
      $entity_type_id => $entity->id(),
      '_format' => 'acquia_contenthub_cdf',
      'include_references' => $include_references,
    );

    $url = Url::fromRoute($route_name, $url_options);
    $path = $url->toString();

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
   * Builds the bulk-upload url to make a single request.
   *
   * @param string $params
   *   Bulk-upload Url query params.
   *
   * @return string
   *   returns URL.
   */
  public function getBulkResourceUrl($params) {

    $route_name = 'acquia_contenthub.acquia_contenthub_bulk_cdf';
    $url = Url::fromRoute($route_name, $params);
    $path = $url->toString();

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
    $entity_type_config = $this->configFactory->get('acquia_contenthub.entity_config')->get('entities.' . $entity->getEntityTypeId());
    $bundle_id = $entity->bundle();
    if (empty($entity_type_config) || empty($entity_type_config[$bundle_id]) || empty($entity_type_config[$bundle_id]['enable_index'])) {
      return FALSE;
    }

    // If the entity has been imported before, then it didn't originate from
    // this site and shouldn't be exported.
    if ($this->contentHubImportedEntities->loadByDrupalEntity($entity->getEntityTypeId(), $entity->id()) !== FALSE) {
      return FALSE;
    }

    return TRUE;
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
    if ($entity = $this->clientManager->createRequest('readEntity', array($uuid))) {
      return new ContentHubEntityDependency($entity);
    }
    return FALSE;
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
  public function getRemoteDependencies(ContentHubEntityDependency $content_hub_entity, $use_chain = TRUE) {
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
  public function getAllRemoteDependencies(ContentHubEntityDependency $content_hub_entity, &$dependencies, $use_chain = TRUE) {
    // Obtaining dependencies of this entity.
    $dep_dependencies = $this->getRemoteDependencies($content_hub_entity, $use_chain);

    foreach ($dep_dependencies as $uuid => $content_hub_dependency) {
      if (isset($dependencies[$uuid])) {
        continue;
      }

      $dependencies[$uuid] = $content_hub_dependency;
      $this->getAllRemoteDependencies($content_hub_dependency, $dependencies, $use_chain);
    }
    return array_reverse($dependencies, TRUE);
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
      if ($entity instanceof ContentEntityType) {
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
        else {
          // In cases where there are no bundles, but the entity can be
          // selected.
          $entity_types[$type][$type] = $entity->getLabel();
        }
      }
    }
    $entity_types = array_diff_key($entity_types, $excluded_types);
    return $entity_types;
  }

}
