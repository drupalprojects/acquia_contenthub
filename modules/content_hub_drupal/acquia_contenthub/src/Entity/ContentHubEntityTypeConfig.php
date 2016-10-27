<?php

/**
 * @file
 * Content Hub Entity Configuration Entity.
 */

namespace Drupal\acquia_contenthub\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface;

/**
 * Defines a ContentHubEntityConfig configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "acquia_contenthub_entity_config",
 *   label = @Translation("Acquia Content Hub Entity configuration"),
 *   config_prefix = "acquia_contenthub",
 *   admin_permission = "Administer Acquia Content Hub'",
 *   label_callback = "getLabelFromPlugin",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   config_export = {
 *     "id",
 *     "bundles",
 *   }
 * )
 */
class ContentHubEntityTypeConfig extends ConfigEntityBase implements ContentHubEntityTypeConfigInterface {

  /**
   * The Content Hub Entity Type Configuration.
   *
   * @var string
   */
  protected $id;

  /**
   * The Bundle Configuration.
   *
   * @var array
   */
  protected $bundles;


  public function getBundles() {
    return $this->bundles;
  }

  public function getBundleConfiguration($bundle) {
    return $this->bundles[$bundle];
  }

  public function getEnabledIndex($bundle) {
    return $this->bundles[$bundle]['enable_index'];
  }

  public function getEnabledViewModes($bundle) {
    return $this->bundles[$bundle]['enable_viewmodes'];
  }

  public function getRendering($bundle) {
    return $this->bundles[$bundle]['rendering'];
  }

  public function setBundles($bundles) {
    $this->bundles = $bundles;
  }

}
