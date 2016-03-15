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
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    // Get our normalizer service
    /** @var \Drupal\content_hub_connector\Normalizer\ContentEntityCdfNormalizer $normalizer */
    $entity_to_cdf_normalizer = \Drupal::service('content_hub_connector.normalizer.content_entity');
    $output = $entity_to_cdf_normalizer->normalize($node, $view_mode);
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
