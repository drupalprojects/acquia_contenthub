<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector/EntityManager.
 */

namespace Drupal\content_hub_connector;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Url;

/**
 * Provides a service for managing entity actions for Content Hub.
 */
class EntityManager {

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Constructs an ContentEntityNormalizer object.
   */
  public function __construct(LoggerChannelFactory $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Executes an action in the Content Hub on a selected drupal entity.
   *
   * @param object $entity
   *   The Drupal Entity object.
   * @param string $type
   *   The entity type.
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
    if ($this->isElegibleEntity($entity)) {
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
   * @param string $action
   *   The action to execute for bulk upload: 'INSERT' or 'UPDATE'.
   * @param EntityInterface $entity
   *   The Content Hub Entity.
   */
  public function entityActionSend(EntityInterface $entity, $action) {
    /** @var \Drupal\content_hub_connector\Client\ClientManagerInterface $client_manager */
    $client_manager = \Drupal::service('content_hub_connector.client_manager');

    try {
      $client = $client_manager->getClient();
    }
    catch (ContentHubConnectorException $e) {
      $this->loggerFactory->get('content_hub_connector')->error($e->getMessage());
      return;
    }

    if($resource = $this->getResourceUrl($entity)) {
      switch ($action) {
        case 'INSERT':
          $client->createEntities($resource);
          break;

        case 'UPDATE':
          $client->updateEntity($resource, $entity->uuid());
          break;

        case 'DELETE':
          $client->deleteEntity($entity->uuid());
          break;
      }
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
        $path = 'node/' . $entity->id() . '/cdf';
        break;

      default:
        return FALSE;
    }

    $config = \Drupal::config('content_hub_connector.admin_settings');
    $rewrite_localdomain = $config->get('content_hub_connector_rewrite_localdomain');
    if ($rewrite_localdomain) {
      $url = Url::fromUri($rewrite_localdomain . '/' . $path);
    }
    else {
      global $base_root;
      $url = Url::fromUri($base_root . '/' . $path);
    }
    return $url->toUriString();
  }

  /**
   * Checks whether the current entity should be transferred to Content Hub.
   *
   * @param object $entity
   *   The Drupal entity.
   * @param string $type
   *   The Drupal entity type.
   *
   * @return bool
   *   True if it can be parsed, False if it not a suitable entity for sending
   *   to content hub.
   */
  function isElegibleEntity(EntityInterface $entity) {
    $config = \Drupal::config('content_hub_connector.entity_config');
    $hubentities = $config->get('content_hub_connector_hubentities_' . $entity->getEntityTypeId());
    $bundle = $entity->bundle();
    if (isset($hubentities[$bundle]) && $hubentities[$bundle] == $bundle) {
      return TRUE;
    }
    return FALSE;
  }

}
