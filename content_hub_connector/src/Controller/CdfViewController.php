<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Controller\CdfViewController.
 */

namespace Drupal\content_hub_connector\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Controller\EntityViewController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Defines a controller to render a single node into a CDF.
 */
class CdfViewController extends EntityViewController {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node, $view_mode = 'full') {
    /** @var \Symfony\Component\Serializer\Serializer $serializer */
    $serializer =  \Drupal::service('serializer');
    $output = $serializer->normalize($node, 'content_hub_cdf');
    return new JsonResponse($output);
  }

  /**
   * The _title_callback for the page that renders a single node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(EntityInterface $node) {
    return $this->entityManager->getTranslationFromContext($node)->label();
  }



}
