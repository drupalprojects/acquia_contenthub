<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\ContentHubConnectorServiceProvider.
 */

namespace Drupal\content_hub_connector;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Make sure it exposes the content_hub_cdf format as json.
 */
class ContentHubConnectorServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')->getClass(), '\Drupal\Core\StackMiddleware\NegotiationMiddleware', TRUE)) {
      $container->getDefinition('http_middleware.negotiation')->addMethodCall('registerFormat', ['content_hub_cdf', ['application/json']]);
    }
  }

}

