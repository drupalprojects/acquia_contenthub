<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\EventSubscriber\ExceptionCdfJsonSubscriber.
 */

namespace Drupal\acquia_contenthub\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionJsonSubscriber;

/**
 * Handle Content Hub CDF JSON exceptions the same as JSON exceptions.
 */
class ExceptionCdfJsonSubscriber extends ExceptionJsonSubscriber {

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['content_hub_cdf'];
  }

}
