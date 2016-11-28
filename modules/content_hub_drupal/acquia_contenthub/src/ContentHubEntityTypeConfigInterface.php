<?php
/**
 * @file
 * Defines the interface for ContentHubEntityTypeConfig class.
 */

namespace Drupal\acquia_contenthub;

/**
 * Interface for ContentHubEntityTypeConfig class.
 */
interface ContentHubEntityTypeConfigInterface {

  /**
   * Obtains the list of bundles.
   */
  public function getBundles();

  /**
   * Checks whether this bundle is enabled.
   *
   * @param string $bundle
   *   The bundle to check for.
   *
   * @return bool
   *   TRUE if the the bundle is enabled, FALSE otherwise.
   */
  public function isEnableIndex($bundle);

  /**
   * Checks if the view modes rendering is enabled for this bundle.
   *
   * @param string $bundle
   *   The bundle to check for.
   *
   * @return bool
   *   TRUE if view modes rendering is enabled for this bundle, FALSE otherwise.
   */
  public function isEnabledViewModes($bundle);

  /**
   * Obtains the list of enabled view modes for a particular bundle.
   *
   * @param string $bundle
   *   The bundle to check for.
   *
   * @return array
   *   An array of view modes.
   */
  public function getRenderingViewModes($bundle);

  /**
   * Sets the bundle array for this configuration entity.
   *
   * @param array $bundles
   *   An array of bundles.
   */
  public function setBundles($bundles);

}
