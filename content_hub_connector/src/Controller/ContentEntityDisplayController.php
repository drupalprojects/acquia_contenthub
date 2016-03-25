<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Controller\ContentEntityDisplayController.
 */

namespace Drupal\content_hub_connector\Controller;

use Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractor;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentEntityDisplayController.
 *
 * @package Drupal\content_hub_connector\Controller
 */
class ContentEntityDisplayController extends ControllerBase {

  /**
   * The Content Entity View Modes Extractor.
   *
   * @var \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractor
   *   The view modes extractor.
   */
  protected $contentEntityViewModesExtractor;

  /**
   * Constructs a new ContentEntityDisplayController object.
   *
   * @param \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractor $content_entity_view_modes_extractor
   *   The view modes extractor.
   */
  public function __construct(ContentEntityViewModesExtractor $content_entity_view_modes_extractor) {
    $this->contentEntityViewModesExtractor = $content_entity_view_modes_extractor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_hub_connector.normalizer.content_entity_view_modes_extractor')
    );
  }

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
  public function viewNode(NodeInterface $node, $view_mode_name = 'teaser') {
    $html = $this->contentEntityViewModesExtractor->getViewModeMinimalHtml($node, $view_mode_name);
    // Map the rendered render array to a HtmlResponse.
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
  }

}
