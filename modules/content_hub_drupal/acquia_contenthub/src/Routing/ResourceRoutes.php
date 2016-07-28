<?php

namespace Drupal\acquia_contenthub\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
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
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory holding resource settings.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct( ConfigFactoryInterface $config) {
    $this->config = $config;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   * @return array
   */
  protected function alterRoutes(RouteCollection $collection) {
    $enabled_resources = ['node' => 'node/{node}', 'block_content' => 'block/{block_content}'];

    // Iterate over all enabled resource plugins.
    foreach ($enabled_resources as $name => $path) {
      $route_info = array(
        'path' => $path,
        'defaults' => array(
          '_controller' => 'Drupal\rest\RequestHandler::handle',
          '_plugin' => 'entity:' . $name,
        ),
        'requirements' => array(
          '_method' => 'GET',
          '_format' => 'acquia_contenthub_cdf',
          # This is fine as the rest module will check if the account has permissions
          # to view the node.
          # @see EntityResource::get()
          '_access' => 'TRUE'
        ),
        'options' => array(
          'parameters' => array(
            $name => array(
              'type' => 'entity:' . $name,
              'converter' => 'paramconverter.entity'
            )
          ),
          'compiler_class' => '\Drupal\Core\Routing\RouteCompiler',
          '_route_filters' => array(
            'request_format_route_filter',
            'content_type_header_matcher',
          ),
          '_route_enhancers' => array(
            'route_enhancer.param_conversion',
          ),
          '_access_checks' => array(
            'access_check.default',
          )
        ),
        'host' => NULL,
        'schemes' => array(),
        'methods' => array('GET'),
        'condition' => '',
      );

      $route = new Route($route_info['path'], $route_info['defaults'], $route_info['requirements'], $route_info['options'], $route_info['host'], $route_info['schemes'], $route_info['methods'], $route_info['condition']);
      $collection->add("acquia_contenthub.content_hub_cdf.$name", $route);
    }
  }

}
