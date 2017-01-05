<?php
/**
 * @file
 * Contains \Drupal\Tests\acquia_contenthub\Unit\ContentHubEntitiesTrackingTest.
 */

namespace Drupal\Tests\acquia_contenthub\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\acquia_contenthub\ContentHubEntitiesTracking;

/**
 * PHPUnit tests for the ContentHubEntitiesTracking class.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\ContentHubEntitiesTracking
 *
 * @group acquia_contenthub
 */
class ContentHubEntitiesTrackingTest extends UnitTestCase {

  /**
   * Content Hub Entities Tracking.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   */
  protected $contentHubEntitiesTracking;

  /**
   * The Site Origin.
   *
   * @var string
   */
  protected $siteOrigin = '22222222-2222-2222-2222-222222222222';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Loads a ContentHubEntitiesTracking object.
   *
   * @param array|null $database_entity
   *   An entity array, that would come as result of a query to the database.
   * @param string|null $site_origin
   *   The site origin.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   *   The loaded object.
   */
  protected function getContentHubEntitiesTrackingService($database_entity = NULL, $site_origin = NULL) {

    // If Site Origin is not set, use default.
    $site_origin = isset($site_origin) ? $site_origin : $this->siteOrigin;

    $database = $this->getMockBuilder('\Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();

    // If we do not provide a database entity, do not use database.
    if (isset($database_entity)) {
      $select = $this->getMock('\Drupal\Core\Database\Query\SelectInterface');
      $select->expects($this->any())
        ->method('fields')
        ->with('ci')
        ->will($this->returnSelf());

      $execute = $this->getMock('\Drupal\Core\Executable\ExecutableInterface');
      $select->expects($this->any())
        ->method('condition')
        ->with('entity_uuid', $database_entity['entity_uuid'])
        ->will($this->returnValue($execute));

      $statement = $this->getMock('\Drupal\Core\Database\StatementInterface');
      $statement->expects($this->any())
        ->method('fetchAssoc')
        ->willReturn($database_entity);

      $execute->expects($this->any())
        ->method('execute')
        ->will($this->returnValue($statement));

      $database->expects($this->any())
        ->method('select')
        ->withAnyParameters()
        ->will($this->returnValue($select));
    }

    $admin_config = $this->getMockBuilder('\Drupal\Core\Config\ImmutableConfig')
      ->disableOriginalConstructor()
      ->getMock();
    $admin_config->method('get')
      ->with('origin')
      ->willReturn($site_origin);

    $config_factory = $this->getMockBuilder('Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->getMock();
    $config_factory
      ->method('get')
      ->with('acquia_contenthub.admin_settings')
      ->willReturn($admin_config);

    return new ContentHubEntitiesTracking($database, $config_factory);
  }


  /**
   * Test for Exported Entities.
   *
   * @covers ::setExportedEntity
   */
  public function testSetExportedEntity() {
    $entity = (object) [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-000000000000',
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
    ];

    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService();
    $this->contentHubEntitiesTracking->setExportedEntity($entity->entity_type, $entity->entity_id, $entity->entity_uuid, $entity->modified, $entity->origin);

    // Running basic tests.
    $this->assertEquals($entity->entity_type, $this->contentHubEntitiesTracking->getEntityType());
    $this->assertEquals($entity->entity_id, $this->contentHubEntitiesTracking->getEntityId());
    $this->assertEquals($entity->entity_uuid, $this->contentHubEntitiesTracking->getUuid());
    $this->assertEquals($entity->modified, $this->contentHubEntitiesTracking->getModified());
    $this->assertEquals($entity->origin, $this->contentHubEntitiesTracking->getOrigin());
    $this->assertTrue($this->contentHubEntitiesTracking->isInitiated());
    $this->assertFalse($this->contentHubEntitiesTracking->isExported());

    $this->contentHubEntitiesTracking->setExported();
    $this->assertFalse($this->contentHubEntitiesTracking->isInitiated());
    $this->assertTrue($this->contentHubEntitiesTracking->isExported());

    $modified = '2017-11-04T20:51:45+00:00';
    $this->contentHubEntitiesTracking->setModified($modified);
    $this->assertEquals($modified, $this->contentHubEntitiesTracking->getModified());

    // Assigning a Database Entity.
    $database_entity = [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-111111112222',
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
      'status_export' => ContentHubEntitiesTracking::INITIATED,
      'status_import' => '',
    ];

    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService($database_entity);

    // Trying to load an imported entity should return false.
    $this->assertFalse($this->contentHubEntitiesTracking->loadImportedByUuid($database_entity['entity_uuid']));

    // Loading an exported entity should work.
    $this->contentHubEntitiesTracking->loadExportedByUuid($database_entity['entity_uuid']);
    $this->assertEquals($database_entity['entity_type'], $this->contentHubEntitiesTracking->getEntityType());
    $this->assertEquals($database_entity['entity_id'], $this->contentHubEntitiesTracking->getEntityId());
    $this->assertEquals($database_entity['entity_uuid'], $this->contentHubEntitiesTracking->getUuid());
    $this->assertEquals($database_entity['modified'], $this->contentHubEntitiesTracking->getModified());
    $this->assertEquals($database_entity['origin'], $this->contentHubEntitiesTracking->getOrigin());
    $this->assertTrue($this->contentHubEntitiesTracking->isInitiated());
    $this->assertFalse($this->contentHubEntitiesTracking->isExported());
  }

  /**
   * Test for Imported Entities.
   *
   * @covers ::setImportedEntity
   */
  public function testSetImportedEntity() {
    $entity = (object) [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-000000000000',
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
    ];

    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService();
    $this->contentHubEntitiesTracking->setImportedEntity($entity->entity_type, $entity->entity_id, $entity->entity_uuid, $entity->modified, $entity->origin);

    // Running basic tests.
    $this->assertEquals($entity->entity_type, $this->contentHubEntitiesTracking->getEntityType());
    $this->assertEquals($entity->entity_id, $this->contentHubEntitiesTracking->getEntityId());
    $this->assertEquals($entity->entity_uuid, $this->contentHubEntitiesTracking->getUuid());
    $this->assertEquals($entity->modified, $this->contentHubEntitiesTracking->getModified());
    $this->assertEquals($entity->origin, $this->contentHubEntitiesTracking->getOrigin());
    $this->assertTrue($this->contentHubEntitiesTracking->isAutoUpdate());
    $this->assertFalse($this->contentHubEntitiesTracking->hasLocalChange());
    $this->assertFalse($this->contentHubEntitiesTracking->isPendingSync());

    $this->contentHubEntitiesTracking->setLocalChange();
    $this->assertFalse($this->contentHubEntitiesTracking->isAutoUpdate());
    $this->assertTrue($this->contentHubEntitiesTracking->hasLocalChange());
    $this->assertFalse($this->contentHubEntitiesTracking->isPendingSync());

    $this->contentHubEntitiesTracking->setPendingSync();
    $this->assertFalse($this->contentHubEntitiesTracking->isAutoUpdate());
    $this->assertTrue($this->contentHubEntitiesTracking->hasLocalChange());
    $this->assertTrue($this->contentHubEntitiesTracking->isPendingSync());

    $modified = '2017-11-04T20:51:45+00:00';
    $this->contentHubEntitiesTracking->setModified($modified);
    $this->assertEquals($modified, $this->contentHubEntitiesTracking->getModified());

    // Assigning a Database Entity.
    $database_entity = [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-111111111111',
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
      'status_export' => '',
      'status_import' => ContentHubEntitiesTracking::AUTO_UPDATE_DISABLED,
    ];
    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService($database_entity);

    // Trying to load an exported entity should return false.
    $this->assertFalse($this->contentHubEntitiesTracking->loadExportedByUuid($database_entity['entity_uuid']));

    // Trying to load an imported entity should work.
    $this->contentHubEntitiesTracking->loadImportedByUuid($database_entity['entity_uuid']);
    $this->assertEquals($database_entity['entity_type'], $this->contentHubEntitiesTracking->getEntityType());
    $this->assertEquals($database_entity['entity_id'], $this->contentHubEntitiesTracking->getEntityId());
    $this->assertEquals($database_entity['entity_uuid'], $this->contentHubEntitiesTracking->getUuid());
    $this->assertEquals($database_entity['modified'], $this->contentHubEntitiesTracking->getModified());
    $this->assertEquals($database_entity['origin'], $this->contentHubEntitiesTracking->getOrigin());
    $this->assertFalse($this->contentHubEntitiesTracking->isAutoUpdate());
    $this->assertFalse($this->contentHubEntitiesTracking->hasLocalChange());
    $this->assertFalse($this->contentHubEntitiesTracking->isPendingSync());
  }

}
