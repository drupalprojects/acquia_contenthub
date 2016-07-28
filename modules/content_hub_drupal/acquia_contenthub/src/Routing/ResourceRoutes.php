<?php

namespace Drupal\acquia_contenthub\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Symfony\Component\Routing\Route;
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
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\rest\Plugin\Type\ResourcePluginManager $manager
   *   The resource plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory holding resource settings.
   */
  public function __construct(ResourcePluginManager $manager, ConfigFactoryInterface $config) {
    $this->config = $config;
    $this->manager = $manager;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   * @return array
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Possible entity types: do
    // var_dump(array_keys($this->manager->getDefinitions())); and then drush cr
    // It will list all entity types supported by REST.
    // @todo -> iterate over possible types and only support the ContentEntity
    // derivatives.
    $supported_entity_types = array(
      'entity:block_content',
      'entity:node',
      'entity:comment',
      'entity:user'
    );

    $enabled_resources = array_intersect_key(array_flip($supported_entity_types), $this->manager->getDefinitions());

    if (count($supported_entity_types) != count($enabled_resources)) {
      trigger_error('rest.settings lists resources relying on the following missing plugins: ' . implode(', ', array_keys(array_diff_key($supported_entity_types, $enabled_resources))));
    }

    // Iterate over all enabled resource plugins.
    foreach ($enabled_resources as $id => $enabled_methods) {
      $plugin = $this->manager->getInstance(array('id' => $id));

      /* @var \Symfony\Component\Routing\Route $route */
      foreach ($plugin->routes() as $name => $route) {
        // @todo: Are multiple methods possible here?
        $methods = $route->getMethods();

        // Only expose routes where the method is GET
        if ($methods[0] != "GET") {
          continue;
        }
        $route->setRequirement('_format', 'acquia_contenthub_cdf');
        $route->setRequirement('_access', 'TRUE');
        $collection->add("acquia_contenthub.content_hub_cdf.$name", $route);
      }
    }
  }
}
