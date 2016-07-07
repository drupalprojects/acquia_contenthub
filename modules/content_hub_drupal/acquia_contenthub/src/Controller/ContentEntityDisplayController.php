<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Controller\ContentEntityDisplayController.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractor;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentEntityDisplayController.
 *
 * Serves the route to show a rendered view mode of a given Node and a given
 * view mode.
 *
 * @todo Ideally this takes inspiration from the _wrapper_format usage in Core.
 *
 * @see \Drupal\Core\EventSubscriber\MainContentViewSubscriber::WRAPPER_FORMAT
 *
 * @package Drupal\acquia_contenthub\Controller
 */
class ContentEntityDisplayController extends ControllerBase {

  /**
   * The Content Entity View Modes Extractor.
   *
   * @var \Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractor
   *   The view modes extractor.
   */
  protected $contentEntityViewModesExtractor;

  /**
   * Constructs a new ContentEntityDisplayController object.
   *
   * @param \Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractor $content_entity_view_modes_extractor
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
      $container->get('acquia_contenthub.normalizer.content_entity_view_modes_extractor')
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
