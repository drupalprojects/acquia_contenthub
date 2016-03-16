<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Task\Manager.
 */

namespace Drupal\content_hub_connector\Task;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a service for managing entity actions for Content Hub.
 */
class Manager {

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
  public function entityAction($entity, $type, $action) {
    // Checking if the entity has already been synchronized so not to generate
    // an endless loop.
    if (isset($entity->__content_hub_synchronized)) {
      unset($entity->__content_hub_synchronized);
      return;
    }

    // Entity has not been sync'ed, then procced with it.
    $hubentity = $this->isElegibleEntity($entity, $type);
    if ($hubentity) {
      drupal_register_shutdown_function(array($this, 'entityActionSend', $action, $entity));
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
  public function entityActionSend($action, EntityInterface $entity) {
    /** @var \Drupal\content_hub_connector\Client\ClientManagerInterface $client_manager */
    $client_manager = \Drupal::service('content_hub_connector.client_manager');
    $client = $client_manager->getClient();
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
        $path = 'node/' . $entity->id();
        break;

      case 'taxonomy_term':
        $path = '/term/taxonomy_term/' . $entity->id();
        break;

      default:
        return FALSE;

    }
    $path .= '?_format=json';

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
   * @return object|bool
   *   The Drupal entity, if it is a Content Hub entity, FALSE otherwise.
   */
  function isElegibleEntity($entity, $type) {
    $config = \Drupal::config('content_hub_connector.entity_config');
    $hubentities = $config->get('content_hub_connector_hubentities_' . $type);
    if (empty($entity) || !isset($hubentities)) {
      return FALSE;
    }
    if (!empty($entity->getType()) && isset($hubentities[$entity->getType()]) && $hubentities[$entity->getType()]) {
      return $entity;
    }
    elseif (isset($hubentities[$type]) && $hubentities[$type]) {
      return $entity;
    }
    return FALSE;
  }

}
