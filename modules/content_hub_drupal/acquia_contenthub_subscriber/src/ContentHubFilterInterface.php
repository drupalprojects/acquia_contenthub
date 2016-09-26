<?php
/**
 * @file
 * Content Hub Filter Interface.
 */

namespace Drupal\acquia_contenthub_subscriber;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Acquia Content Hub Filter entity.
 */
interface ContentHubFilterInterface extends ConfigEntityInterface {

  /**
   * Returns the Publish setting in human-readable format.
   *
   * @return string.
   *   Returns the Human readable version of the publish setting.
   */
  public function getPublishSetting();

  /**
   * Returns the Author.
   *
   * @return mixed
   *   Returns the author account name.
   */
  public function getAuthor();

  /**
   * Returns the conditions string.
   *
   * @return string
   *   Returns the condition string.
   */
  public function getConditions();

}
