<?php

/**
 * @file
 * Contains \Drupal\node\Routing\RouteSubscriber.
 */

namespace Drupal\content_hub_connector\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = (new Route('/node/{node}/cdf'))
      ->addDefaults([
        '_controller' => '\Drupal\content_hub_connector\Controller\CdfViewController::view',
        '_title_callback' => '\Drupal\content_hub_connector\Controller\CdfViewController::title',
      ])
      ->setRequirement('node', '\d+')
      ->setRequirement('_entity_access', 'node.view');

    $collection->add('entity.node.cdf', $route);

  }

}
