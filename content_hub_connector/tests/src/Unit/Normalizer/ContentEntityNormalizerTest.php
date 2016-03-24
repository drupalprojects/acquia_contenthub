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

    // Fake Content Hub Connector Config
    $this->contentHubEntityConfig = array(
      'test',
    );

    $this->contentHubAdminConfig = array(
      'test',
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
   * Tests the normalize() method without a mandatory created and changed field.
   *
   * @covers ::normalize
   */
  public function testNormalizeOneField() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array('0' => array('value' => 'test'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Define which languages we have set on the site.
    $languages = array('en');

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, $languages);

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'content_hub_cdf');

    // Start testing our result set.
    $this->assertArrayHasKey('entities', $normalized);
    // We want 1 result in there.
    $this->assertCount(1, $normalized['entities']);

    // Since there is only 1 entity, we are fairly certain the first one is
    // ours.
    /** @var \Acquia\ContentHubClient\Entity $normalized_entity */
    $normalized_entity = array_pop($normalized['entities']);
    // Check if it is of the expected class.
    $this->assertTrue($normalized_entity instanceof \Acquia\ContentHubClient\Entity);

    // Check the UUID property.
    $this->assertEquals('custom-uuid', $normalized_entity->getUuid());

    // Check if there was a created date set.
    $this->assertNotEmpty($normalized_entity->getCreated());

    // Check if there was a modified date set.
    $this->assertNotEmpty($normalized_entity->getModified());

    // Check if there was an origin property set.
    $this->assertEquals('test-origin', $normalized_entity->getOrigin());

    // Check if there was a type property set to the entity type.
    $this->assertEquals('node', $normalized_entity->getType());

    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));
  }

  /**
   * Tests the normalize() method with a regular entity and no view modes.
   *
   * @covers ::normalize
   */
  public function testNormalizeWithCreatedAndChanged() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array('0' => array('value' => 'test'))),
      'created' => $this->createMockFieldListItem('created', 'timestamp', TRUE, NULL, array('0' => array('value' => '1458811508'))),
      'changed' => $this->createMockFieldListItem('changed', 'timestamp', TRUE, NULL, array('0' => array('value' => '1458811509'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Define which languages we have set on the site.
    $languages = array('en');

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, $languages);

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'content_hub_cdf');

    // Start testing our result set.
    $this->assertArrayHasKey('entities', $normalized);
    // We want 1 result in there.
    $this->assertCount(1, $normalized['entities']);

    // Since there is only 1 entity, we are fairly certain the first one is
    // ours.
    /** @var \Acquia\ContentHubClient\Entity $normalized_entity */
    $normalized_entity = array_pop($normalized['entities']);
    // Check if it is of the expected class.
    $this->assertTrue($normalized_entity instanceof \Acquia\ContentHubClient\Entity);

    // Check if there was a created date set.
    $this->assertEquals($normalized_entity->getCreated(), date('c', 1458811508));

    // Check if there was a modified date set.
    $this->assertEquals($normalized_entity->getModified(), date('c', 1458811509));

    // Check if field_1 has the correct values
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));
  }

  /**
   * Tests the normalize() method with a regular entity and no view modes.
   *
   * @covers ::normalize
   */
  public function testNormalizeWithFieldWithoutAccess() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array('0' => array('value' => 'test'))),
      'field_2' => $this->createMockFieldListItem('field_2', 'string', FALSE, NULL, array('0' => array('value' => 'test'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Define which languages we have set on the site.
    $languages = array('en');

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, $languages);

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'content_hub_cdf');

    // Start testing our result set.
    $this->assertArrayHasKey('entities', $normalized);
    // We want 1 result in there.
    $this->assertCount(1, $normalized['entities']);

    // Since there is only 1 entity, we are fairly certain the first one is
    // ours.
    /** @var \Acquia\ContentHubClient\Entity $normalized_entity */
    $normalized_entity = array_pop($normalized['entities']);
    // Check if it is of the expected class.
    $this->assertTrue($normalized_entity instanceof \Acquia\ContentHubClient\Entity);

    // Check if field_1 has the correct values
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));

    // Field 2 should not be part of the normalizer.
    $this->assertFalse($normalized_entity->getAttribute('field_2'));
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

    // The mock account should get passed directly into the access() method on
    // field items from $context['account'].
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, $mock_account, array('0' => array('value' => 'test'))),
      'field_2' => $this->createMockFieldListItem('field_2', 'string', FALSE, $mock_account, array('0' => array('value' => 'test'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Define which languages we have set on the site.
    $languages = array('en');

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, $languages);

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'content_hub_cdf');

    // Start testing our result set.
    $this->assertArrayHasKey('entities', $normalized);
    // We want 1 result in there.
    $this->assertCount(1, $normalized['entities']);

    // Since there is only 1 entity, we are fairly certain the first one is
    // ours.
    /** @var \Acquia\ContentHubClient\Entity $normalized_entity */
    $normalized_entity = array_pop($normalized['entities']);
    // Check if it is of the expected class.
    $this->assertTrue($normalized_entity instanceof \Acquia\ContentHubClient\Entity);

    // Check if field_1 has the correct values
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));

    // Field 2 should not be part of the normalizer.
    $this->assertFalse($normalized_entity->getAttribute('field_2'));

  }

  /**
   * Make sure we return the expected normalization results.
   *
   * For all the given definitions of fields with their respective values, we
   * need to be sure that when ->normalize is executed, it returns the expected
   * results.
   *
   * @param array $definitions
   */
  protected function getFieldsSerializer(array $definitions) {
    $serializer = $this->getMockBuilder('Symfony\Component\Serializer\Serializer')
      ->disableOriginalConstructor()
      ->setMethods(array('normalize'))
      ->getMock();

    $serializer->expects($this->any())
      ->method('normalize')
      ->with($this->containsOnlyInstancesOf('Drupal\Core\Field\FieldItemListInterface'), 'json', ['account' => NULL, 'entity_type' => 'node'])
      ->willReturnCallback(function($field, $format, $context) {
        if ($field) {
          return $field->getValue();
        }
        return NULL;
      });

    return $serializer;
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

    // return the given content.
    $content_entity_mock->method('get')->willReturnCallback(function($name) use ($definitions) {
      if (isset($definitions[$name])) {
        return $definitions[$name];
      }
      return NULL;
    });

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
  protected function createMockFieldListItem($name, $type = 'string', $access = TRUE, $user_context = NULL, $return_value = array()) {
    $mock = $this->getMock('Drupal\Core\Field\FieldItemListInterface');
    $mock->method('access')
      ->with('view', $user_context)
      ->will($this->returnValue($access));

    $field_def = $this->getMock('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_def->method('getName')->willReturn($name);
    $field_def->method('getType')->willReturn($type);

    $mock->method('getValue')->willReturn($return_value);

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