<?php
/**
 * @file
 * Contains \Drupal\Tests\acquia_contenthub\Unit\EntityManagerTest.
 */

namespace Drupal\Tests\acquia_contenthub\Unit;

use Drupal\acquia_contenthub\Entity\ContentHubEntityTypeConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\acquia_contenthub\EntityManager;

/**
 * PHPUnit for the EntityManager class.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\EntityManager
 *
 * @group acquia_contenthub
 */
class EntityManagerTest extends UnitTestCase {

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  private $loggerFactory;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  private $configFactory;

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager|\PHPUnit_Framework_MockObject_MockObject
   */
  private $clientManager;

  /**
   * The Content Hub Imported Entities Service.
   *
   * @var \Drupal\acquia_contenthub\ContentHubImportedEntities|\PHPUnit_Framework_MockObject_MockObject
   */
  private $contentHubImportedEntities;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $entityTypeBundleInfoManager;

  /**
   * The Basic HTTP Kernel to make requests.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $kernel;

  /**
   * Settings.
   *
   * @var \Drupal\Core\Config\Config|\PHPUnit_Framework_MockObject_MockObject
   */
  private $settings;

  /**
   * Content Entity Type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $contentEntityType;

  /**
   * Config Entity Type.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  private $configEntityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->loggerFactory = $this->getMockBuilder('Drupal\Core\Logger\LoggerChannelFactory')
      ->disableOriginalConstructor()
      ->getMock();
    $this->configFactory = $this->getMockBuilder('Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->getMock();
    $this->clientManager = $this->getMock('Drupal\acquia_contenthub\Client\ClientManagerInterface');
    $this->contentHubImportedEntities = $this->getMockBuilder('Drupal\acquia_contenthub\ContentHubImportedEntities')
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityTypeManager = $this->getMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->entityTypeBundleInfoManager = $this->getMock('Drupal\Core\Entity\EntityTypeBundleInfoInterface');
    $this->kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

    $this->settings = $this->getMockBuilder('Drupal\Core\Config\Config')
      ->disableOriginalConstructor()
      ->getMock();

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('acquia_contenthub.admin_settings')
      ->willReturn($this->settings);

    $this->contentEntityType = $this->getMock('Drupal\Core\Entity\ContentEntityTypeInterface');
    $this->configEntityType = $this->getMock('Drupal\Core\Config\Entity\ConfigEntityTypeInterface');
  }

  /**
   * Builds a ContentHubEntityTypeConfig entity.
   *
   * @param string $id
   *   The Configuration entity ID.
   *
   * @return \Drupal\acquia_contenthub\Entity\ContentHubEntityTypeConfig
   *   The Configuration entity.
   */
  protected function getContentHubEntityTypeConfigEntity($id) {
    $bundles = [
      'entity_type_1' => [
        'bundle_11' => [
          'enable_index' => 1,
          'enable_viewmodes' => 1,
          'rendering' => [
            'view_1',
            'view_2',
            'view_3',
          ],
        ],
      ],
      'entity_type_2' => [
        'bundle_21' => [
          'enable_index' => 1,
          'enable_viewmodes' => 0,
          'rendering' => [],
        ],
        'bundle_22' => [
          'enable_index' => 0,
          'enable_viewmodes' => 0,
          'rendering' => [
            'view_4',
          ],
        ],
        'bundle_23' => [
          'enable_index' => 1,
          'enable_viewmodes' => 1,
          'rendering' => [],
        ],
      ],
      'entity_type_3' => [
        'bundle_31' => [
          'enable_index' => 0,
          'enable_viewmodes' => 0,
          'rendering' => [],
        ],
      ],
    ];
    $values = [
      'id' => $id,
    ];
    $config_entity = new ContentHubEntityTypeConfig($values, 'acquia_contenthub_entity_config');
    $config_entity->setBundles($bundles[$id]);
    return $config_entity;
  }

  /**
   * Defines the Content Hub Entity Types Configuration.
   *
   * @return array
   *   An array of Content Hub Entity Types configuration.
   */
  private function getContentHubConfigStorage() {
    // Creating Configuration Entities.
    $config_entity1 = $this->getContentHubEntityTypeConfigEntity('entity_type_1');
    $config_entity2 = $this->getContentHubEntityTypeConfigEntity('entity_type_2');
    $config_entity3 = $this->getContentHubEntityTypeConfigEntity('entity_type_3');

    // Grouping configuration entities.
    $config_entities = [
      'entity_type_1' => $config_entity1,
      'entity_type_2' => $config_entity2,
      'entity_type_3' => $config_entity3,
    ];
    $config_storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
    $config_storage->method('loadMultiple')->willReturn($config_entities);
    return $config_storage;
  }

  /**
   * Test for getContentHubEnabledEntityTypeIds() method.
   *
   * @covers ::getContentHubEnabledEntityTypeIds
   */
  public function testGetContentHubEnabledEntityTypeIds() {
    $entity_manager = new EntityManager($this->loggerFactory, $this->configFactory, $this->clientManager, $this->contentHubImportedEntities, $this->entityTypeManager, $this->entityTypeBundleInfoManager, $this->kernel);

    $config_storage = $this->getContentHubConfigStorage();
    $this->entityTypeManager->method('getStorage')->with('acquia_contenthub_entity_config')->willReturn($config_storage);

    $enabled_entity_type_ids = $entity_manager->getContentHubEnabledEntityTypeIds();
    $expected_entity_type_ids = [
      'entity_type_1',
      'entity_type_2',
    ];
    $this->assertEquals($expected_entity_type_ids, $enabled_entity_type_ids);
  }

  /**
   * Test for getAllowedEntityTypes() method.
   *
   * @covers ::getAllowedEntityTypes
   */
  public function testGetAllowedEntityTypes() {

    $entity_manager = new EntityManager($this->loggerFactory, $this->configFactory, $this->clientManager, $this->contentHubImportedEntities, $this->entityTypeManager, $this->entityTypeBundleInfoManager, $this->kernel);

    // Second content entity does not have bundles.
    $this->contentEntityType->expects($this->once())
      ->method('getLabel')
      ->willReturn('content_entity_2');

    $entity_types = [
      'content_entity_1' => $this->contentEntityType,
      'content_entity_2' => $this->contentEntityType,
      'comment' => $this->contentEntityType,
      'user' => $this->contentEntityType,
      'config_entity_1' => $this->configEntityType,
      'config_entity_2' => $this->configEntityType,
    ];

    $this->entityTypeManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($entity_types);

    $bundles = [
      'bundle1' => [
        'label' => 'bundle1',
      ],
      'bundle2' => [
        'label' => 'bundle2',
      ],
    ];

    $this->entityTypeBundleInfoManager->expects($this->at(0))
      ->method('getBundleInfo')
      ->with('content_entity_1')
      ->willReturn($bundles);
    $this->entityTypeBundleInfoManager->expects($this->at(1))
      ->method('getBundleInfo')
      ->with('content_entity_2')
      ->willReturn(NULL);

    $entity_types = $entity_manager->getAllowedEntityTypes();
    $expected_entity_types = [
      'content_entity_1' => [
        'bundle1' => 'bundle1',
        'bundle2' => 'bundle2',
      ],
      'content_entity_2' => [
        'content_entity_2' => 'content_entity_2',
      ],
    ];
    $this->assertEquals($expected_entity_types, $entity_types);
  }

}
