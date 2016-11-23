<?php

/**
 * @file
 * Content Hub Entity Configuration Entity.
 */

namespace Drupal\acquia_contenthub\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\acquia_contenthub\ContentHubEntityTypeConfigInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

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
   *   An array keyed by bundle.
   */
  protected $bundles;

  /**
   * Gets the list of bundles and their configuration.
   *
   * @return array
   *   An array keyed by bundle.
   */
  public function getBundles() {
    return $this->bundles;
  }

  /**
   * Check if this bundle is enabled.
   *
   * @param string $bundle
   *   The entity bundle.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isEnableIndex($bundle) {
    return $this->bundles[$bundle]['enable_index'];
  }

  /**
   * Check if view modes are enabled.
   *
   * @param string $bundle
   *   The entity bundle.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isEnableViewModes($bundle) {
    return $this->bundles[$bundle]['enable_viewmodes'];
  }

  /**
   * Obtains the list of rendering view modes.
   *
   * Note this does not check whether the view modes are enabled so a previous
   * check on that has to be done.
   *
   * @param string $bundle
   *   The entity bundle.
   * @return array
   *   An array of rendering view modes.
   */
  public function getRenderingViewModes($bundle) {
    return $this->bundles[$bundle]['rendering'];
  }

  /**
   * Sets the bundles.
   *
   * @param array $bundles
   *   An array of bundles configuration.
   */
  public function setBundles($bundles) {
    $this->bundles = $bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add dependencies on module.
    $entity_type = $this->entityTypeManager()->getDefinition($this->id());
    $this->addDependency('module', $entity_type->getProvider());

    // Add config dependencies.
    $bundles = array_keys($this->getBundles());
    foreach ($bundles as $bundle) {
      if ($this->isEnableIndex($bundle)) {
        // Add dependency on this particular bundle.
        $config_bundle = $entity_type->getBundleConfigDependency($bundle);
        $this->addDependency($config_bundle['type'], $config_bundle['name']);

        // Add dependencies on all enabled view modes.
        if ($this->isEnableViewModes($bundle)) {
          $view_modes = $this->getRenderingViewModes($bundle);
          foreach ($view_modes as $view_mode) {
            // Enable dependency on these view modes.
            /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
            $viewmode = "{$entity_type->id()}.$bundle.$view_mode";
            if ($display = EntityViewDisplay::load($viewmode)) {
              $this->addDependency($display->getConfigDependencyKey(), $display->getConfigDependencyName());
            }
          }
        }
      }
    }
    return $this;
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected function entityTypeManager() {
    return \Drupal::entityTypeManager();
  }


}
