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
   * @covers ::setTrackingEntity
   */
  public function testSetExportedEntity() {
    $entity = (object) [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-000000000000',
      'status_export' => ContentHubEntitiesTracking::INITIATED,
      'status_import' => '',
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
    ];

    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService();

    $this->contentHubEntitiesTracking->setExportedEntity($entity->entity_type, $entity->entity_id, $entity->entity_uuid, $entity->status_export, $entity->modified, $entity->origin);

    // Running basic tests.
    $this->assertEquals($entity->entity_id, $this->contentHubEntitiesTracking->getEntityId());
    $this->assertEquals($entity->entity_type, $this->contentHubEntitiesTracking->getEntityType());
    $this->assertEquals($entity->entity_uuid, $this->contentHubEntitiesTracking->getUuid());
    $this->assertEquals($entity->status_export, $this->contentHubEntitiesTracking->getExportStatus());
    $this->assertEquals($entity->modified, $this->contentHubEntitiesTracking->getModified());
    $this->assertEquals($entity->origin, $this->contentHubEntitiesTracking->getOrigin());

    $this->contentHubEntitiesTracking->setExportStatus(ContentHubEntitiesTracking::EXPORTED);
    $this->assertEquals(ContentHubEntitiesTracking::EXPORTED, $this->contentHubEntitiesTracking->getExportStatus());

    $modified = '2017-11-04T20:51:45+00:00';
    $this->contentHubEntitiesTracking->setModified($modified);
    $this->assertEquals($modified, $this->contentHubEntitiesTracking->getModified());

    $this->assertFalse($this->contentHubEntitiesTracking->setExportStatus(ContentHubEntitiesTracking::AUTO_UPDATE_ENABLED));

    // Assigning a Database Entity.
    $database_entity = [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-000000000000',
      'status_export' => ContentHubEntitiesTracking::INITIATED,
      'status_import' => '',
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
    ];
    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService($database_entity);
    $this->assertFalse($this->contentHubEntitiesTracking->loadImportedByUuid($entity->entity_uuid));

    $this->contentHubEntitiesTracking->loadExportedByUuid($database_entity['entity_uuid']);
    $this->assertEquals((object) $database_entity, $this->contentHubEntitiesTracking->getTrackingEntity());
  }

  /**
   * Test for Imported Entities.
   *
   * @covers ::setTrackingEntity
   */
  public function testSetImportedEntity() {
    $entity = (object) [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-000000000000',
      'status_export' => '',
      'status_import' => ContentHubEntitiesTracking::AUTO_UPDATE_ENABLED,
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
    ];

    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService();

    $this->contentHubEntitiesTracking->setImportedEntity($entity->entity_type, $entity->entity_id, $entity->entity_uuid, $entity->status_import, $entity->modified, $entity->origin);

    // Running basic tests.
    $this->assertEquals($entity->entity_id, $this->contentHubEntitiesTracking->getEntityId());
    $this->assertEquals($entity->entity_type, $this->contentHubEntitiesTracking->getEntityType());
    $this->assertEquals($entity->entity_uuid, $this->contentHubEntitiesTracking->getUuid());
    $this->assertEquals($entity->status_import, $this->contentHubEntitiesTracking->getImportStatus());
    $this->assertEquals($entity->modified, $this->contentHubEntitiesTracking->getModified());
    $this->assertEquals($entity->origin, $this->contentHubEntitiesTracking->getOrigin());

    $this->contentHubEntitiesTracking->setImportStatus(ContentHubEntitiesTracking::AUTO_UPDATE_LOCAL_CHANGE);
    $this->assertEquals(ContentHubEntitiesTracking::AUTO_UPDATE_LOCAL_CHANGE, $this->contentHubEntitiesTracking->getImportStatus());

    $modified = '2017-11-04T20:51:45+00:00';
    $this->contentHubEntitiesTracking->setModified($modified);
    $this->assertEquals($modified, $this->contentHubEntitiesTracking->getModified());

    $this->assertFalse($this->contentHubEntitiesTracking->setImportStatus(ContentHubEntitiesTracking::EXPORTED));

    // Assigning a Database Entity.
    $database_entity = [
      'entity_type' => 'node',
      'entity_id' => 1,
      'entity_uuid' => '00000000-0000-0000-0000-000000000000',
      'status_export' => '',
      'status_import' => ContentHubEntitiesTracking::AUTO_UPDATE_DISABLED,
      'modified' => '2016-12-09T20:51:45+00:00',
      'origin' => '11111111-1111-1111-1111-111111111111',
    ];
    $this->contentHubEntitiesTracking = $this->getContentHubEntitiesTrackingService($database_entity);
    $this->assertFalse($this->contentHubEntitiesTracking->loadExportedByUuid($entity->entity_uuid));

    $this->contentHubEntitiesTracking->loadImportedByUuid($database_entity['entity_uuid']);
    $this->assertEquals((object) $database_entity, $this->contentHubEntitiesTracking->getTrackingEntity());
  }

}
