<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector/EntityManager.
 */

namespace Drupal\content_hub_connector;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\content_hub_connector\Client\ClientManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\content_hub_connector\ContentHubImportedEntities;

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
   * @var \Drupal\content_hub_connector\Client\ClientManager
   */
  protected $clientManager;

  protected $contentHubImportedEntities;


  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *    The config factory.
   * @param \Drupal\content_hub_connector\Client\ClientManagerInterface $client_manager
   *    The client manager.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory, ClientManagerInterface $client_manager, ContentHubImportedEntities $ch_imported_entities) {
    global $base_root;
    $this->baseRoot = $base_root;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->clientManager = $client_manager;
    $this->contentHubImportedEntities = $ch_imported_entities;
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
    if (isset($entity->__content_hub_synchronized)) {
      unset($entity->__content_hub_synchronized);
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
    /** @var \Drupal\content_hub_connector\Client\ClientManagerInterface $client_manager */
    try {
      $client = $this->clientManager->getClient();
    }
    catch (ContentHubConnectorException $e) {
      $this->loggerFactory->get('content_hub_connector')->error($e->getMessage());
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
      $this->loggerFactory->get('content_hub_connector')->error($message);
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
          $this->loggerFactory->get('content_hub_connector')->error($message);
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
          $this->loggerFactory->get('content_hub_connector')->error($message);
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
          $this->loggerFactory->get('content_hub_connector')->error($message);
          return;
        }
        break;
    }
    // Make sure it is within the 2XX range. Expected response is a 202.
    if ($response->getStatusCode()[0] == '2' && $response->getStatusCode()[1] == '0') {
      $message = new FormattableMarkup($message_string, $args);
      $this->loggerFactory->get('content_hub_connector')->error($message);
    }

  }

  /**
   * Returns the local Resource URL.
   *
   * This is an absolute URL, which base_url can be overwritten with the
   * variable 'content_hub_connector_rewrite_localdomain', which is especially
   * useful in cases where the Content Hub module is installed in a Drupal site
   * that is running locally (not from the public internet).
   *
   * @return string|bool
   *   The absolute resource URL, if it can be generated, FALSE otherwise.
   */
  public function getResourceUrl(EntityInterface $entity) {
    switch ($entity->getEntityTypeId()) {
      case 'node':
        $path = 'node/' . $entity->id() . '?_format=content_hub_cdf';
        break;

      default:
        return FALSE;
    }

    $config = $this->configFactory->get('content_hub_connector.admin_settings');
    $rewrite_localdomain = $config->get('rewrite_domain');
    if ($rewrite_localdomain) {
      $url = Url::fromUri($rewrite_localdomain . '/' . $path);
    }
    else {
      $url = Url::fromUri($this->baseRoot . '/' . $path);
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
    $entity_type_config = $this->configFactory->get('content_hub_connector.entity_config')->get('entities.' . $entity->getEntityTypeId());
    $bundle_id = $entity->bundle();
    if (empty($entity_type_config) || empty($entity_type_config[$bundle_id]) || empty($entity_type_config[$bundle_id]['enabled'])) {
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
    /** @var \Drupal\content_hub_connector\Client\ClientManagerInterface $client_manager */
    try {
      $client = $this->clientManager->getClient();
      $ch_entity = $client->readEntity($uuid);
    }
    catch (ContentHubConnectorException $e) {
      $this->loggerFactory->get('content_hub_connector')->error($e->getMessage());
      return FALSE;
    }

    return $ch_entity;
  }

}
