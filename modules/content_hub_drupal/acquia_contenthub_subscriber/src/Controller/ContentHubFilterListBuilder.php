<?php
/**
 * @file
 * ContentHubFilter List Builder.
 */

namespace Drupal\acquia_contenthub_subscriber\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Content Hub Filter.
 */
class ContentHubFilterListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Content Hub Filter');
    $header['id'] = $this->t('Machine name');
    $header['publish_setting'] = $this->t('Publish setting');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $this->getLabel($entity);
    $row['id'] = $entity->id();
    $row['publish_setting'] = $entity->getPublishSetting();


    // You probably want a few more properties here...

    return $row + parent::buildRow($entity);
  }

}