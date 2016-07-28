<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\AcquiaContentHubServiceProvider.
 *
 * Filename MUST be CamelCase modulename (AcquiaContentHub) + ServiceProvider
 * for it to have any effect.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Make sure it exposes the acquia_contenthub_cdf format as json.
 */
class AcquiaContentHubServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')->getClass(), '\Drupal\Core\StackMiddleware\NegotiationMiddleware', TRUE)) {
      $container->getDefinition('http_middleware.negotiation')->addMethodCall('registerFormat', ['acquia_contenthub_cdf', ['application/json']]);
    }
  }

}
