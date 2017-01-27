<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\ImportEntityManager.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Core\Entity\EntityInterface;
use Drupal\diff\DiffEntityComparison;
use Drupal\acquia_contenthub\Controller\ContentHubEntityImportController;

/**
 * Provides a service for managing imported entities' actions.
 */
class ImportEntityManager {

  /**
   * The Content Hub Entities Tracking Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   */
  private $contentHubEntitiesTracking;

  /**
   * The Content Hub Import Controller.
   *
   * @var \Drupal\acquia_contenthub\Controller\ContentHubEntityImportController
   */
  private $contentHubImportController;

  /**
   * Diff module's entity comparison service.
   *
   * @var Drupal\diff\DiffEntityComparison
   */
  private $diffEntityComparison;

  /**
   * Constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntitiesTracking $entities_tracking
   *   The Content Hub Entities Tracking Service.
   * @param \Drupal\acquia_contenthub\Controller\ContentHubEntityImportController $entity_import_controller
   *   The Content Hub Entities Import Controller.
   */
  public function __construct(ContentHubEntitiesTracking $entities_tracking, ContentHubEntityImportController $entity_import_controller, DiffEntityComparison $entity_comparison) {
    $this->contentHubEntitiesTracking = $entities_tracking;
    $this->contentHubImportController = $entity_import_controller;
    $this->diffEntityComparison = $entity_comparison;
  }

  /**
   * Act on the entity's update action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being updated.
   */
  public function entityUpdate(EntityInterface $entity) {
    $imported_entity = $this->contentHubEntitiesTracking->loadImportedByDrupalEntity($entity->getEntityTypeId(), $entity->id());
    // Do nothing, if:
    // 1) Not an imported entity.
    // 2) Is not pending sync or during sync.
    if (!$imported_entity || !$imported_entity->isPendingSync() || isset($entity->__contenthub_synchronized)) {
      return;
    }

    // Otherwise, re-import the entity.
    $this->contentHubImportController->saveDrupalEntity($imported_entity->getUuid(), $entity);
  }

  /**
   * Act on the entity's presave action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being saved.
   */
  public function entityPresave(EntityInterface $entity) {
    // If the entity is "pending sync" or already "has local change", skip.
    $imported_entity = $this->contentHubEntitiesTracking->loadImportedByDrupalEntity($entity->getEntityTypeId(), $entity->id());
    if (!$imported_entity || $imported_entity->isPendingSync() || $imported_entity->hasLocalChange()) {
      return;
    }

    // Otherwise check if the entity has introduced any local changes.
    $field_comparisons = $this->diffEntityComparison->compareRevisions($entity->original, $entity);

    $has_local_change = FALSE;
    foreach ($field_comparisons as $field_comparison) {
      if ($field_comparison['#data']['#left'] !== $field_comparison['#data']['#right']) {
        $has_local_change = TRUE;
        break;
      }
    }

    // Don't do anything if there is no local change.
    if (!$has_local_change) {
      return;
    }

    // Otherwise, set and store the imported entity as having local changes.
    $imported_entity->setLocalChange();
    $imported_entity->save();
  }

}
