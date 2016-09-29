<?php

/**
 * @file
 * Subscriber for REST-style routes.
 */

namespace Drupal\acquia_contenthub_subscriber\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Symfony\Component\Routing\RouteCollection;


/**
 * Subscriber for REST-style routes.
 */
class ResourceRoutes extends RouteSubscriberBase {

  /**
   * The plugin manager for REST plugins.
   *
   * @var \Drupal\rest\Plugin\Type\ResourcePluginManager
   */
  protected $manager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\rest\Plugin\Type\ResourcePluginManager $manager
   *   The resource plugin manager.
   */
  public function __construct(ResourcePluginManager $manager) {
    $this->manager = $manager;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection) {

    // ResourcePluginManager $manager.
    /* @var \Drupal\rest\Plugin\ResourceInterface[] $resources */
    $resources = $this->manager->getDefinitions();

    // Iterate over all enabled resource plugins.
    foreach ($resources as $id => $enabled_methods) {
      // Only take routes for 'contenthub_filter'.
      if ($id !== 'contenthub_filter') {
        continue;
      }

      /* @var \Drupal\rest\Plugin\rest\resource\EntityResource $plugin */
      $plugin = $this->manager->getInstance(array('id' => $id));

      /* @var \Symfony\Component\Routing\Route $route */
      foreach ($plugin->routes() as $name => $route) {

        // Allowed route names.
        $allowed_names = array(
          'contenthub_filter.GET.json',
          'contenthub_filter.POST',
          // 'contenthub_filter.PATCH',
        );
        if (!in_array($name, $allowed_names)) {
          continue;
        }

        // Do not take routes that are not in our list.
        $allowed_paths = array(
          '/acquia_contenthub/contenthub_filter/{contenthub_filter}',
          '/acquia_contenthub/contenthub_filter'
        );
        if (!in_array($route->getPath(), $allowed_paths)) {
          continue;
        }

        // Only allow GET, POST (for now).
        // @TODO: Enable PATCH, DELETE.
        // @TODO: Are multiple methods possible here?
        $methods = $route->getMethods();
        if (!in_array($methods[0], array('GET', 'POST'))) {
          continue;
        }

        // Support JSON.
        if ($route->getRequirement('_format') !== 'json') {
          $route->setRequirement('_format', 'json');
        }

        // Add cookie-based authentication.
        $route->setOption('_auth', array('cookie'));

        $collection->add('rest.' . $name, $route);
      }
    }
  }

}
