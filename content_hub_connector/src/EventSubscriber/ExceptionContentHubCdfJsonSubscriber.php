<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\EventSubscriber\ExceptionCdfJsonSubscriber.
 */

namespace Drupal\content_hub_connector\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionJsonSubscriber;

/**
 * Handle HAL JSON exceptions the same as JSON exceptions.
 */
class ExceptionCdfJsonSubscriber extends ExceptionJsonSubscriber {

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['content_hub_cdf'];
  }

}
