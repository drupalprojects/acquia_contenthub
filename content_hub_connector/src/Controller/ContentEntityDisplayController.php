<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Controller\ContentEntityDisplayController.
 */

namespace Drupal\content_hub_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Class ContentEntityDisplayController.
 *
 * @package Drupal\content_hub_connector\Controller
 */
class ContentEntityDisplayController extends ControllerBase {

  /**
   * View node by view node name.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $view_mode_name
   *   The view mode's name.
   *
   * @return string
   *   The html page that is being viewed in given view mode.
   */
  public function viewNode(NodeInterface $node, $view_mode_name = 'full') {
    return $this->view($node, $view_mode_name);
  }

  /**
   * Preview entity view modes.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Entity that is being rendered.
   * @param string $view_mode_name
   *   The view mode's name.
   *
   * @return string
   *   The html page that is being viewed in given view mode.
   */
  private function view(ContentEntityInterface $entity, $view_mode_name = 'full') {
    $markup = entity_view($entity, $view_mode_name);
    $build[] = [
      '#markup' => render($markup),
    ];
    return $build;
  }

}
