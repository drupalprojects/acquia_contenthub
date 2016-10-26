<?php

/**
 * @file
 * Subscriber for REST-style routes.
 */

namespace Drupal\acquia_contenthub\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Drupal\acquia_contenthub\EntityManager;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;


/**
 * Subscriber for Acquia Content Hub REST routes.
 */
class ResourceRoutes extends RouteSubscriberBase {

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   *
   * @todo remove
   */
  protected $config;

  /**
   * The plugin manager for REST plugins.
   *
   * @var \Drupal\rest\Plugin\Type\ResourcePluginManager
   *
   * @todo remove
   */
  protected $manager;

  /**
   * The content hub entity manager.
   *
   * @var \Drupal\acquia_contenthub\EntityManager
   */
  protected $entityManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ResourceRoutes object.
   *
   * @param \Drupal\rest\Plugin\Type\ResourcePluginManager $manager
   *   The resource plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory holding resource settings.
   * @param \Drupal\acquia_contenthub\EntityManager $entity_manager
   *   The entity manager for Content Hub.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ResourcePluginManager $manager, ConfigFactoryInterface $config, EntityManager $entity_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->config = $config;
    $this->manager = $manager;
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Generates Content Hub REST resource routes every eligible entity type.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection) {
    // @todo the returned allowed entity types are wrong, see https://www.drupal.org/node/2822033. This means that we're generating routes even for entity types which have not been enabled at /admin/config/services/acquia-contenthub/configuration.
    $allowed_entity_types = $this->entityManager->getAllowedEntityTypes();

    foreach (array_keys($allowed_entity_types) as $entity_type_id) {
      // Match the behavior of \Drupal\rest\Plugin\rest\resource\EntityResource:
      // use the entity type's canonical link template if it has one, otherwise
      // use EntityResource's generic alternative.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $canonical_path = $entity_type->hasLinkTemplate('canonical')
        ? $entity_type->getLinkTemplate('canonical')
        : '/entity/' . $entity_type_id . '/{' . $entity_type_id . '}';

      $route = new Route($canonical_path, [
        '_controller' => '\Drupal\acquia_contenthub\Controller\ContentHubEntityRequestHandler::handle',
        // @see \Drupal\acquia_contenthub\Controller\ContentHubEntityRequestHandler
        // @todo Remove this when https://www.drupal.org/node/2822201 lands, and this module is able to require Drupal 8.3.x.
        '_acquia_content_hub_rest_resource_plugin_id' => 'entity:' . $entity_type_id,
      ]);
      $route->setOption('parameters', [
        $entity_type_id => [
          'type' => 'entity:' . $entity_type_id,
        ],
      ]);
      // Only allow the Acquia Content Hub CDF format.
      $route->setRequirement('_format', 'acquia_contenthub_cdf');
      // Only allow access to the CDF if the request is coming from a logged
      // in user with 'Administer Acquia Content Hub' permission or if it
      // is coming from Acquia Content Hub (validates the HMAC signature).
      $route->setRequirement('_contenthub_access_check', 'TRUE');
      // Only allow GET.
      $route->setMethods(['GET']);

      $collection->add('acquia_contenthub.entity.' . $entity_type_id . '.GET.acquia_contenthub_cdf', $route);
    }
  }

}
