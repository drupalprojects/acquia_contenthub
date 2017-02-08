<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Tests\ContentHubEntityTrait.
 */

namespace Drupal\acquia_contenthub\Tests;

/**
 * Content Hub Entity Trait.
 */
trait ContentHubEntityTrait {

  /**
   * Origin of the entity.
   */
  private $origin = '2c8b1237-1ee6-453e-aa11-3edf9e9c5f9d';

  /**
   * Timestamp of last modification.
   */
  private $modified = '';

  /**
   * Convert an regular entity to Content Hub entity.
   *
   * @param object $entity
   *   A Drupal entity.
   */
  private function convertToContentHubEntity($entity) {
    $imported_entity = $this->container->get('acquia_contenthub.acquia_contenthub_entities_tracking')->setImportedEntity($entity->getEntityTypeId(), $entity->id(), $entity->uuid(), $this->modified, $this->origin);
    $imported_entity->save();
  }

}
