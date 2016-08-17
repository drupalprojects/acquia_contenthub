<?php

/**
 * @file
 * Subscriber for REST-style routes.
 */

namespace Drupal\acquia_contenthub\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Drupal\acquia_contenthub\EntityManager;
use Symfony\Component\Routing\RouteCollection;


/**
 * Subscriber for REST-style routes.
 */
class ResourceRoutes extends RouteSubscriberBase {

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The plugin manager for REST plugins.
   *
   * @var \Drupal\rest\Plugin\Type\ResourcePluginManager
   */
  protected $manager;

  /**
   * The content hub entity manager.
   *
   * @var \Drupal\acquia_contenthub\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\rest\Plugin\Type\ResourcePluginManager $manager
   *   The resource plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory holding resource settings.
   * @param \Drupal\acquia_contenthub\EntityManager $entity_manager
   *   The entity manager for Content Hub.
   */
  public function __construct(ResourcePluginManager $manager, ConfigFactoryInterface $config, EntityManager $entity_manager) {
    $this->config = $config;
    $this->manager = $manager;
    $this->entityManager = $entity_manager;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection) {

    $allowed_entity_types = $this->entityManager->getAllowedEntityTypes();
    // ResourcePluginManager $manager.
    /* @var \Drupal\rest\Plugin\ResourceInterface[] $resources */
    $resources = $this->manager->getDefinitions();

    // Iterate over all enabled resource plugins.
    foreach ($resources as $id => $enabled_methods) {
      /* @var \Drupal\rest\Plugin\rest\resource\EntityResource $plugin */
      $plugin = $this->manager->getInstance(array('id' => $id));

      /* @var \Symfony\Component\Routing\Route $route */
      foreach ($plugin->routes() as $name => $route) {
        // @todo: Are multiple methods possible here?
        $methods = $route->getMethods();
        // Only expose routes where the method is GET.
        if ($methods[0] != "GET") {
          continue;
        }
        // We have a couple of GET's in the list (XML, JSON, and potentially
        // content_hubOnly add it once, so filter on the JSON one to make sure
        // we only add it once.
        if ($route->getRequirement('_format') !== 'json') {
          continue;
        }
        // Unset routes that are not in our list.
        if (!in_array($plugin->getDerivativeId(), array_keys($allowed_entity_types))) {
          $route_name = 'acquia_contenthub.entity.' . $plugin->getDerivativeId() . '.GET.acquia_contenthub_cdf';
          $collection->remove($route_name);
          continue;
        }

        $route->setRequirement('_format', 'acquia_contenthub_cdf');

        // Only allow access to the CDF if the request is coming from a logged
        // in user with 'Administer Acquia Content Hub' permission or if it
        // is coming from Acquia Content Hub (validates the HMAC signature).
        $route->setRequirement('_contenthub_access_check', 'TRUE');

        // Remove the permission required. Open for all and controlled by
        // entity_access.
        $requirements = $route->getRequirements();
        unset($requirements['_permission']);
        $route->setRequirements($requirements);

        $route_name = 'acquia_contenthub.entity.' . $plugin->getDerivativeId() . '.GET.acquia_contenthub_cdf';
        $collection->add($route_name, $route);
      }
    }
  }

}
