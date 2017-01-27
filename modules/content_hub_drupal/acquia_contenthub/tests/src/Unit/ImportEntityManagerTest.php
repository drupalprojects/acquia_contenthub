<?php

/**
 * @file
 * Contains \Drupal\Tests\acquia_contenthub\Unit\ImportEntityManagerTest.
 */

namespace Drupal\Tests\acquia_contenthub\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\acquia_contenthub\ImportEntityManager;

/**
 * PHPUnit test for the ImportEntityManager class.
 *
 * @coversDefaultClass Drupal\acquia_contenthub\ImportEntityManager
 *
 * @group acquia_contenthub
 */
class ImportEntityManagerTest extends UnitTestCase {

  /**
   * The Content Hub Entities Tracking Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntitiesTracking|\PHPUnit_Framework_MockObject_MockObject
   */
  private $contentHubEntitiesTracking;

  /**
   * The Content Hub Import Controller.
   *
   * @var \Drupal\acquia_contenthub\Controller\ContentHubEntityImportController|\PHPUnit_Framework_MockObject_MockObject
   */
  private $contentHubImportController;

  /**
   * Diff module's entity comparison service.
   *
   * @var Drupal\diff\DiffEntityComparison|\PHPUnit_Framework_MockObject_MockObject
   */
  private $diffEntityComparison;

  /**
   * Import entity manager.
   *
   * @var \Drupal\acquia_contenthub\ImportEntityManager
   */
  private $importEntityManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->contentHubEntitiesTracking = $this->getMockBuilder('Drupal\acquia_contenthub\ContentHubEntitiesTracking')
      ->disableOriginalConstructor()
      ->getMock();
    $this->contentHubImportController = $this->getMockBuilder('Drupal\acquia_contenthub\Controller\ContentHubEntityImportController')
      ->disableOriginalConstructor()
      ->getMock();
    $this->diffEntityComparison = $this->getMockBuilder('Drupal\diff\DiffEntityComparison')
      ->disableOriginalConstructor()
      ->getMock();
    $this->importEntityManager = new ImportEntityManager($this->contentHubEntitiesTracking, $this->contentHubImportController, $this->diffEntityComparison);
  }

  /**
   * Tests the entityUpdate() method, node is not imported.
   *
   * @covers ::entityUpdate
   */
  public function testEntityUpdateNodeNotImported() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn(NULL);

    $this->contentHubImportController->expects($this->never())
      ->method('saveDrupalEntity');

    $this->importEntityManager->entityUpdate($node);
  }

  /**
   * Tests the entityUpdate() method, node is pending sync.
   *
   * @covers ::entityUpdate
   */
  public function testEntityUpdateNodeIsPendingSync() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(FALSE);

    $this->contentHubImportController->expects($this->never())
      ->method('saveDrupalEntity');

    $this->importEntityManager->entityUpdate($node);
  }

  /**
   * Tests the entityUpdate() method, node is during sync.
   *
   * @covers ::entityUpdate
   */
  public function testEntityUpdateNodeIsDuringSync() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(TRUE);
    $node->__contenthub_synchronized = TRUE;

    $this->contentHubImportController->expects($this->never())
      ->method('saveDrupalEntity');

    $this->importEntityManager->entityUpdate($node);
  }

  /**
   * Tests the entityUpdate() method, node is to be resync'ed.
   *
   * @covers ::entityUpdate
   */
  public function testEntityUpdateNodeToResync() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(TRUE);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('getUuid')
      ->willReturn('xxx-yyy');
    $this->contentHubImportController->expects($this->once())
      ->method('saveDrupalEntity')
      ->with('xxx-yyy', $node);
    $this->importEntityManager->entityUpdate($node);
  }

  /**
   * Tests the entityPresave() method, node is not imported.
   *
   * @covers ::entityPresave
   */
  public function testEntityPresaveNodeNotImported() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn(NULL);

    $this->diffEntityComparison->expects($this->never())
      ->method('compareRevisions');

    $this->importEntityManager->entityUpdate($node);
  }

  /**
   * Tests the entityPresave() method, node is pending sync.
   *
   * @covers ::entityPresave
   */
  public function testEntityPresaveNodeIsPendingSync() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(TRUE);

    $this->diffEntityComparison->expects($this->never())
      ->method('compareRevisions');

    $this->importEntityManager->entityPresave($node);
  }

  /**
   * Tests the entityPresave() method, node is has local change.
   *
   * @covers ::entityPresave
   */
  public function testEntityPresaveNodeHasLocalChange() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(FALSE);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('hasLocalChange')
      ->willReturn(TRUE);

    $this->diffEntityComparison->expects($this->never())
      ->method('compareRevisions');

    $this->importEntityManager->entityPresave($node);
  }


  /**
   * Tests the entityPresave() method, compare, and no setLocalChange.
   *
   * @covers ::entityPresave
   */
  public function testEntityPresaveCompareNoLocalChange() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $original_node = $this->getMock('\Drupal\node\NodeInterface');
    $node->original = $original_node;

    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(FALSE);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('hasLocalChange')
      ->willReturn(FALSE);

    $field_comparisons = [
      'same_field_1' => [
        '#data' => [
          '#left' => 'same_value_1',
          '#right' => 'same_value_1',
        ],
      ],
      'same_field_2' => [
        '#data' => [
          '#left' => 'same_value_2',
          '#right' => 'same_value_2',
        ],
      ],
    ];

    $this->diffEntityComparison->expects($this->once())
      ->method('compareRevisions')
      ->with($original_node, $node)
      ->willReturn($field_comparisons);
    $this->contentHubEntitiesTracking->expects($this->never())
      ->method('setLocalChange');
    $this->contentHubEntitiesTracking->expects($this->never())
      ->method('save');

    $this->importEntityManager->entityPresave($node);
  }

  /**
   * Tests the entityPresave() method, compare, and yes setLocalChange.
   *
   * @covers ::entityPresave
   */
  public function testEntityPresaveCompareYesLocalChange() {
    $node = $this->getMock('\Drupal\node\NodeInterface');
    $original_node = $this->getMock('\Drupal\node\NodeInterface');
    $node->original = $original_node;

    $node->expects($this->once())
      ->method('id')
      ->willReturn(12);
    $node->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('loadImportedByDrupalEntity')
      ->with('node', 12)
      ->willReturn($this->contentHubEntitiesTracking);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('isPendingSync')
      ->willReturn(FALSE);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('hasLocalChange')
      ->willReturn(FALSE);

    $field_comparisons = [
      'same_field_1' => [
        '#data' => [
          '#left' => 'same_value_1',
          '#right' => 'same_value_1',
        ],
      ],
      'difference_field_2' => [
        '#data' => [
          '#left' => 'a_value',
          '#right' => 'a_different_value',
        ],
      ],
      'same_field_2' => [
        '#data' => [
          '#left' => 'same_value_2',
          '#right' => 'same_value_2',
        ],
      ],
    ];

    $this->diffEntityComparison->expects($this->once())
      ->method('compareRevisions')
      ->with($original_node, $node)
      ->willReturn($field_comparisons);
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('setLocalChange');
    $this->contentHubEntitiesTracking->expects($this->once())
      ->method('save');

    $this->importEntityManager->entityPresave($node);
  }

}
