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
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *    The config factory.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory, ClientManagerInterface $client_manager, ContentHubImportedEntities $acquia_contenthub_imported_entities, EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info_manager) {
    global $base_root;
    $this->baseRoot = $base_root;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->clientManager = $client_manager;
    $this->contentHubImportedEntities = $acquia_contenthub_imported_entities;
    $this->entityTypeManager = $entity_manager;
    $this->entityTypeBundleInfoManager = $entity_type_bundle_info_manager;

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
    // Checking if the entity has already been synchronized so not to generate
    // an endless loop.
    if (isset($entity->__contenthub_synchronized)) {
      unset($entity->__contenthub_synchronized);
      return;
    }
    // Entity has not been sync'ed, then proceed with it.
    if ($this->isEligibleEntity($entity)) {
      // @todo In Drupal 7 this used the shutdown function
      // drupal_register_shutdown_function(array($this, 'entityActionSend',
      // $action, $entity));
      // figure out if we really need to do this?
      $this->entityActionSend($entity, $action);
    }
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
  public function getResourceUrl(EntityInterface $entity) {
    // Check if there are link templates defined for the entity type and
    // use the path from the route instead of the default.
    $entity_type = $entity->getEntityType();
    $entity_type_id = $entity->getEntityTypeId();

    $route_name = 'acquia_contenthub.entity.' . $entity_type_id . '.GET.acquia_contenthub_cdf';
    $url_options = array(
      'entity_type' => $entity_type_id,
      $entity_type_id => $entity->id(),
      '_format' => 'acquia_contenthub_cdf',
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
   * Checks whether the current entity should be transferred to Content Hub.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return bool
   *   True if it can be parsed, False if it not a suitable entity for sending
   *   to content hub.
   */
  protected function isEligibleEntity(EntityInterface $entity) {
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
   * @return \Acquia\ContentHubClient\Entity
   *   The Content Hub Entity.
   */
  public function loadRemoteEntity($uuid) {
    /** @var \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager */
    try {
      $client = $this->clientManager->getConnection();
      $contenthub_entity = $client->readEntity($uuid);
    }
    catch (ContentHubException $e) {
      $this->loggerFactory->get('acquia_contenthub')->error($e->getMessage());
      return FALSE;
    }

    return $contenthub_entity;
  }

  /**
   * Obtains the list of entity types.
   */
  public function getAllowedEntityTypes() {
    // List of entities that are excluded from displaying on
    // entity configuration page and will not be pushed to Content Hub.
    // @Todo Support Blocks in future.
    $excluded_types = [
      'block_content',
      'comment',
      'user',
      'contact_message',
      'shortcut',
      'menu_link_content',
      'user',
    ];

    // If the config "acquia_contenthub.entity_config.block_content_support"
    // is set to TRUE, then enable support for block content.
    if ((bool) $this->configFactory->get('acquia_contenthub.entity_config')->get('block_content_support')) {
      // Unset block_content.
      unset($excluded_types[0]);
    }

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
