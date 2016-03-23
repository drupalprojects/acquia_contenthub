<?php

/**
 * @file
 * Contains \Drupal\Tests\content_hub_connector\Unit\Normalizer\ContentEntityNormalizerTest.
 */

namespace Drupal\Tests\content_hub_connector\Unit\Normalizer;

use Drupal\content_hub_connector\Normalizer\ContentEntityNormalizer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\content_hub_connector\Normalizer\ContentEntityNormalizer
 * @group content_hub_connector
 */
class ContentEntityNormalizerTest extends UnitTestCase {

  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The mock serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $serializer;

  /**
   * The normalizer under test.
   *
   * @var \Drupal\content_hub_connector\Normalizer\ContentEntityNormalizer
   */
  protected $contentEntityNormalizer;

  /**
   * The mock view modes extractor.
   *
   * @var \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractor
   */
  protected $contentEntityViewModesExtractor;

  /**
   * The mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The mock module handler factory.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Content Hub Connector config used for the scope of this test.
   *
   * @var array
   */
  protected $contentHubEntityConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->container = new ContainerBuilder();
    $entity_manager = $this->prophesize(EntityManagerInterface::class)->reveal();
    $this->container->set('entity.manager', $entity_manager);
    \Drupal::setContainer($this->container);

    $this->configFactory = $this->getMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('content_hub_connector.admin_settings')
      ->will($this->returnValue($this->createMockForContentHubAdminConfig()));

    $this->contentEntityViewModesExtractor = $this->getMock('\Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractorInterface');
    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->contentEntityNormalizer = new ContentEntityNormalizer($this->configFactory, $this->contentEntityViewModesExtractor, $this->moduleHandler);
    $this->serializer = $this->getMockBuilder('Symfony\Component\Serializer\Serializer')
      ->disableOriginalConstructor()
      ->setMethods(array('normalize'))
      ->getMock();
    $this->contentEntityNormalizer->setSerializer($this->serializer);

    // Fake Content Hub Connector Config
    $this->contentHubEntityConfig = array(
      'test',
    );

    $this->contentHubAdminConfig = array(
      'test'
    );
  }

  /**
   * @covers ::supportsNormalization
   */
  public function testSupportsNormalization() {
    $content_mock = $this->getMock('Drupal\Core\Entity\ContentEntityInterface');
    $config_mock = $this->getMock('Drupal\Core\Entity\ConfigEntityInterface');
    $this->assertTrue($this->contentEntityNormalizer->supportsNormalization($content_mock));
    $this->assertFalse($this->contentEntityNormalizer->supportsNormalization($config_mock));
  }

  /**
   * Tests the normalize() method.
   *
   * @covers ::normalize
   */
  public function testNormalize() {
    $this->serializer->expects($this->any())
      ->method('normalize')
      ->with($this->containsOnlyInstancesOf('Drupal\Core\Field\FieldItemListInterface'), 'json', ['account' => NULL, 'entity_type' => 'node'])
      ->will($this->returnValue(array(array('value' => 'test'))));

    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1'),
      'field_2' => $this->createMockFieldListItem('field_2', FALSE),
      'created' => $this->createMockFieldListItem('created', FALSE, NULL, array('0' => array('value' => '0'))),
      'changed' => $this->createMockFieldListItem('changed', FALSE, NULL, array('0' => array('value' => '0'))),
    );
    $languages = array('en', 'nl');
    $content_entity_mock = $this->createMockForContentEntity($definitions, $languages);

    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'content_hub_cdf');

    $this->assertArrayHasKey('entities', $normalized);

    $this->assertNotEmpty($normalized['entities'][0]['attributes']);
    $this->assertNotEmpty($normalized['entities'][0]['attributes']);
    $this->assertArrayHasKey('field_1', $normalized['entities'][0]['attributes']);
    $this->assertNotEmpty($normalized['entities'][0]['attributes']['field_1']);

    $values = $normalized['entities'][0]['attributes']['field_1']->getValues();

    $this->assertArrayHasKey('en', $values);
    $this->assertArrayHasKey('nl', $values);

    $this->assertArrayNotHasKey('field_2', $normalized['entities'][0]['attributes']);
  }

  /**
   * Tests the normalize() method with account context passed.
   *
   * @covers ::normalize
   */
  public function _testNormalizeWithAccountContext() {
    $mock_account = $this->getMock('Drupal\Core\Session\AccountInterface');

    $context = [
      'account' => $mock_account,
    ];

    $this->serializer->expects($this->any())
      ->method('normalize')
      ->with($this->containsOnlyInstancesOf('Drupal\Core\Field\FieldItemListInterface'), 'test_format', $context)
      ->will($this->returnValue('test'));

    // The mock account should get passed directly into the access() method on
    // field items from $context['account'].
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', TRUE, $mock_account),
      'field_2' => $this->createMockFieldListItem('field_2', FALSE, $mock_account),
    );

    $content_entity_mock = $this->createMockForContentEntity($definitions);

    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'test_format', $context);

    $this->assertArrayHasKey('field_1', $normalized);
    $this->assertEquals('test', $normalized['field_1']);
    $this->assertArrayNotHasKey('field_2', $normalized);
  }

  /**
   * Creates a mock content entity.
   *
   * @param $definitions
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   */
  public function createMockForContentEntity($definitions, $languages) {
    $content_entity_mock = $this->getMockBuilder('Drupal\Core\Entity\ContentEntityBase')
      ->disableOriginalConstructor()
      ->setMethods(array('getFields', 'getEntityTypeId', 'uuid', 'get', 'getTranslationLanguages', 'getTranslation'))
      ->getMockForAbstractClass();

    $content_entity_mock->method('getFields')->willReturn($definitions);

    $mock = $this->getMock('Drupal\Core\Field\FieldItemListInterface');
    $mock->method('getValue')->willReturn(array('0' => array('value' => '0')));

    $content_entity_mock->method('get')->willReturn($mock);

    $content_entity_mock->method('getEntityTypeId')->willReturn('node');

    $content_entity_mock->method('uuid')->willReturn('custom-uuid');

    $content_entity_mock->method('getTranslation')->willReturn($content_entity_mock);

    $languages = $this->createMockLanguageList($languages);
    $content_entity_mock->method('getTranslationLanguages')->willReturn($languages);

    return $content_entity_mock;
  }

  public function createMockForContentHubAdminConfig() {
    $content_hub_admin_config = $this->getMockBuilder('Drupal\Core\Config\ImmutableConfig')
      ->disableOriginalConstructor()
      ->setMethods(array('get'))
      ->getMockForAbstractClass();

    $content_hub_admin_config->method('get')->with('origin')->willReturn('test-origin');

    return $content_hub_admin_config;
  }

  /**
   * Creates a mock field list item.
   *
   * @param bool $access
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected function createMockFieldListItem($name, $access = TRUE, $user_context = NULL, $return_value = array()) {
    $mock = $this->getMock('Drupal\Core\Field\FieldItemListInterface');
    $mock->method('access')
      ->with('view', $user_context)
      ->will($this->returnValue($access));

    $field_def = $this->getMock('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_def->method('getName')->willReturn($name);
    $field_def->method('getType')->willReturn('decimal');

    $mock->method('getFieldDefinition')->willReturn($field_def);

    return $mock;
  }

  /**
   * Creates a mock language list.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]|\PHPUnit_Framework_MockObject_MockObject
   */
  protected function createMockLanguageList($languages = array('en')) {
    $language_objects = array();
    foreach ($languages as $language) {
      $mock = $this->getMock('Drupal\Core\Language\LanguageInterface');
      $mock->method('getId')->willReturn($language);
      $language_objects[$language] = $mock;
    }
    return $language_objects;
  }

}
