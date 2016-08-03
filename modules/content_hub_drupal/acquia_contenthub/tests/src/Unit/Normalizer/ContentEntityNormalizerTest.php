<?php

/**
 * @file
 * Contains \Drupal\Tests\acquia_contenthub\Unit\Normalizer\ContentEntityNormalizerTest.
 */

namespace Drupal\Tests\acquia_contenthub\Unit\Normalizer;

use Drupal\acquia_contenthub\Normalizer\ContentEntityCdfNormalizer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * PHPUnit test for the ContentEntityNormalizer class.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Normalizer\ContentEntityCdfNormalizer
 *
 * @group acquia_contenthub
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
   * @var \Drupal\acquia_contenthub\Normalizer\ContentEntityCdfNormalizer
   */
  protected $contentEntityNormalizer;

  /**
   * The mock view modes extractor.
   *
   * @var \Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractor
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
   * The Acquia Content Hub config used for the scope of this test.
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
      ->with('acquia_contenthub.admin_settings')
      ->will($this->returnValue($this->createMockForContentHubAdminConfig()));

    $this->contentEntityViewModesExtractor = $this->getMock('\Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractorInterface');
    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');

    $this->contentEntityNormalizer = new ContentEntityCdfNormalizer($this->configFactory, $this->contentEntityViewModesExtractor, $this->moduleHandler);

    // Fake Acquia Content Hub Config.
    $this->contentHubEntityConfig = array(
      'test',
    );

    $this->contentHubAdminConfig = array(
      'test',
    );
  }

  /**
   * Test the supportsNormalization method.
   *
   * @covers ::supportsNormalization
   */
  public function testSupportsNormalization() {
    $content_mock = $this->getMock('Drupal\Core\Entity\ContentEntityInterface');
    $config_mock = $this->getMock('Drupal\Core\Entity\ConfigEntityInterface');
    $this->assertTrue($this->contentEntityNormalizer->supportsNormalization($content_mock));
    $this->assertFalse($this->contentEntityNormalizer->supportsNormalization($config_mock));
  }

  /**
   * Test the getBaseRoot function.
   *
   * @covers ::getBaseRoot
   */
  public function testGetBaseRoot() {
    // With the global set.
    $GLOBALS['base_root'] = 'test';
    $this->assertEquals('test', $this->contentEntityNormalizer->getBaseRoot());
    unset($GLOBALS['base_root']);

    // Without the global set.
    $this->assertEquals('', $this->contentEntityNormalizer->getBaseRoot());
  }

  /**
   * Tests the normalize() method.
   *
   * Tests to see if it errors on the wrong object.
   *
   * @covers ::normalize
   */
  public function testNormalizeIncompatibleClass() {
    // Create a config entity class.
    $config_mock = $this->getMock('Drupal\Core\Entity\ConfigEntityInterface');
    // Normalize the Config Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($config_mock, 'acquia_contenthub_cdf');
    // Make sure it didn't do anything.
    $this->assertNull($normalized);
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 1 field and checks if it appears in the normalized result.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeOneField() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array('0' => array('value' => 'test'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);

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
    // Check if the field has the given value.
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 1 field with multiple values and checks if it appears in the
   * normalized result. Also adds multiple languages to see if it properly
   * combines them.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   * @covers ::appendToAttribute
   */
  public function testNormalizeOneFieldMultiValued() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array(array('value' => 'test'), array('value' => 'test2'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en', 'nl'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);

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
    // Check if the field has the given value.
    $expected_output = array(
      'en' => array('test', 'test2'),
      'nl' => array('test', 'test2'),
    );
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), $expected_output);
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 1 field and the created and changed fields. Make sure there is
   * no changed or created field in the final attributes as those are
   * excluded.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   * @covers ::excludedProperties
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

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Check if there was a created date set.
    $this->assertEquals($normalized_entity->getCreated(), date('c', 1458811508));
    // Check if there was a modified date set.
    $this->assertEquals($normalized_entity->getModified(), date('c', 1458811509));
    // Check if field_1 has the correct values.
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));
    // Field created should not be part of the normalizer.
    $this->assertFalse($normalized_entity->getAttribute('created'));
    // Field changed should not be part of the normalizer.
    $this->assertFalse($normalized_entity->getAttribute('changed'));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 1 field but with any content in it. The field should not be present
   * and should be ignored.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeWithNoFieldValue() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array()),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Field created should not be part of the normalizer.
    $this->assertFalse($normalized_entity->getAttribute('field_1'));
  }

  /**
   * Tests the normalize() method.
   *
   * Test that we can also map field names. The field type String maps to the
   * content hub type array<string> while the field name title field is
   * explicitely mapped to the singular version "string" for the content hub
   * types. Test that this is actually the case.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeWithFieldNameAsType() {
    $definitions = array(
      'title' => $this->createMockFieldListItem('title', 'string', TRUE, NULL, array('0' => array('value' => 'test'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Check if field_1 has the correct values
    // Different expected value. Title is never plural.
    $this->assertEquals($normalized_entity->getAttribute('title')->getValues(), array('en' => 'test'));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests that we support other field types such as boolean, etc..
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeWithNonStringFieldType() {
    $definitions = array(
      'voted' => $this->createMockFieldListItem('voted', 'boolean', TRUE, NULL, array('0' => array('value' => TRUE))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Check if field_1 has the correct values
    // Different expected value. Title is never plural.
    $this->assertEquals($normalized_entity->getAttribute('voted')->getValues(), array('en' => array(TRUE)));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests that we support complex fields with more than just a value key.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeWithComplexFieldValues() {
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, NULL, array('0' => array('value' => 'test', 'random_key' => 'random_data'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Check if field_1 has the correct values.
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('{"value":"test","random_key":"random_data"}')));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 2 fields. The user has access to 1 field but not the other.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
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

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Check if field_1 has the correct values.
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));
    // Field 2 should not be part of the normalizer.
    $this->assertFalse($normalized_entity->getAttribute('field_2'));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 2 fields given a passed user context. Field 1 is accessible, but
   * field 2 is not.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeWithAccountContext() {
    $mock_account = $this->getMock('Drupal\Core\Session\AccountInterface');
    $context = ['account' => $mock_account];

    // The mock account should get passed directly into the access() method on
    // field items from $context['account'].
    $definitions = array(
      'field_1' => $this->createMockFieldListItem('field_1', 'string', TRUE, $mock_account, array('0' => array('value' => 'test'))),
      'field_2' => $this->createMockFieldListItem('field_2', 'string', FALSE, $mock_account, array('0' => array('value' => 'test'))),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions, $mock_account);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity with English support.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf', $context);

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);
    // Check if field_1 has the correct values.
    $this->assertEquals($normalized_entity->getAttribute('field_1')->getValues(), array('en' => array('test')));
    // Field 2 should not be part of the resultset.
    $this->assertFalse($normalized_entity->getAttribute('field_2'));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 1 entity reference field and checks if it appears in the normalized
   * result. It should return the UUID of the referenced item.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeReferenceField() {
    $definitions = array(
      'field_ref' => $this->createMockEntityReferenceFieldItemList('field_ref', TRUE, NULL),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);

    // Check if the field has the given value.
    $this->assertEquals($normalized_entity->getAttribute('field_ref')->getValues(), array('en' => array('test-uuid-reference-1', 'test-uuid-reference-2')));
  }

  /**
   * Tests the normalize() method.
   *
   * Tests 1 entity reference field and checks if it appears in the normalized
   * result. It should return the id of the referenced item.
   *
   * @covers ::normalize
   * @covers ::addFieldsToContentHubEntity
   */
  public function testNormalizeTypeReferenceField() {
    $definitions = array(
      'type' => $this->createMockEntityReferenceFieldItemList('type', TRUE, NULL),
    );

    // Set our Serializer and expected serialized return value for the given
    // fields.
    $serializer = $this->getFieldsSerializer($definitions);
    $this->contentEntityNormalizer->setSerializer($serializer);

    // Create our Content Entity.
    $content_entity_mock = $this->createMockForContentEntity($definitions, array('en'));

    // Normalize the Content Entity with the class that we are testing.
    $normalized = $this->contentEntityNormalizer->normalize($content_entity_mock, 'acquia_contenthub_cdf');

    // Check if valid result.
    $this->doTestValidResultForOneEntity($normalized);
    // Get our Content Hub Entity out of the result.
    $normalized_entity = $this->getContentHubEntityFromResult($normalized);

    // Check if the field has the given value.
    $this->assertEquals($normalized_entity->getAttribute('type')->getValues(), array('en' => array('test-id-reference-1', 'test-id-reference-2')));
  }

  /**
   * Test the getFieldTypeMapping method.
   *
   * @covers ::getFieldTypeMapping
   */
  public function testGetFieldTypeMapping() {
    $mapping = $this->contentEntityNormalizer->getFieldTypeMapping();
    $this->assertNotEmpty($mapping);
    $this->assertEquals('array<boolean>', $mapping['boolean']);
    $this->assertEquals(NULL, $mapping['password']);
    $this->assertEquals('array<number>', $mapping['decimal']);
    $this->assertEquals('array<reference>', $mapping['entity_reference']);
    $this->assertEquals('array<string>', $mapping['fallback']);
    $this->assertEquals('string', $mapping['title']);
    $this->assertEquals('string', $mapping['langcode']);
  }

  /**
   * Test the denormalize method.
   *
   * @covers ::denormalize
   */
  public function testDenormalize() {
    $denormalized = $this->contentEntityNormalizer->denormalize(NULL, NULL);
    $this->assertNull($denormalized);
  }

  /**
   * Check if the base result set is correctly set to 1 entity.
   */
  private function doTestValidResultForOneEntity($normalized) {
    // Start testing our result set.
    $this->assertArrayHasKey('entities', $normalized);
    // We want 1 result in there.
    $this->assertCount(1, $normalized['entities']);
  }

  /**
   * Get the Content Hub Entity from our normalized array.
   *
   * @param array $normalized
   *   The normalized array structure containing the content hub entity
   *   objects.
   *
   * @return \Acquia\ContentHubClient\Entity
   *   The first ContentHub Entity from the resultset.
   */
  private function getContentHubEntityFromResult(array $normalized) {
    // Since there is only 1 entity, we are fairly certain the first one is
    // ours.
    /** @var \Acquia\ContentHubClient\Entity $normalized_entity */
    $normalized_entity = array_pop($normalized['entities']);
    // Check if it is of the expected class.
    $this->assertTrue($normalized_entity instanceof \Acquia\ContentHubClient\Entity);
    return $normalized_entity;
  }

  /**
   * Make sure we return the expected normalization results.
   *
   * For all the given definitions of fields with their respective values, we
   * need to be sure that when ->normalize is executed, it returns the expected
   * results.
   *
   * @param array $definitions
   *   The field definitions.
   * @param array $user_context
   *   The user context such as the account.
   */
  protected function getFieldsSerializer(array $definitions, $user_context = NULL) {
    $serializer = $this->getMockBuilder('Symfony\Component\Serializer\Serializer')
      ->disableOriginalConstructor()
      ->setMethods(array('normalize'))
      ->getMock();

    $serializer->expects($this->any())
      ->method('normalize')
      ->with($this->containsOnlyInstancesOf('Drupal\Core\Field\FieldItemListInterface'), 'json', ['account' => $user_context, 'entity_type' => 'node'])
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
   * @param array $definitions
   *   The field definitions.
   * @param array $languages
   *   The languages that this fake entity should have.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   *   The fake ContentEntity.
   */
  public function createMockForContentEntity($definitions, $languages) {
    $enabled_methods = array(
      'getFields',
      'getEntityTypeId',
      'uuid',
      'get',
      'getTranslationLanguages',
      'getTranslation',
    );

    $content_entity_mock = $this->getMockBuilder('Drupal\Core\Entity\ContentEntityBase')
      ->disableOriginalConstructor()
      ->setMethods($enabled_methods)
      ->getMockForAbstractClass();

    $content_entity_mock->method('getFields')->willReturn($definitions);

    // Return the given content.
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

  /**
   * Returns a fake ContentHubAdminConfig object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The fake config.
   */
  public function createMockForContentHubAdminConfig() {
    $contenthub_admin_config = $this->getMockBuilder('Drupal\Core\Config\ImmutableConfig')
      ->disableOriginalConstructor()
      ->setMethods(array('get'))
      ->getMockForAbstractClass();

    $contenthub_admin_config->method('get')->with('origin')->willReturn('test-origin');

    return $contenthub_admin_config;
  }

  /**
   * Creates a mock field list item.
   *
   * @param bool $access
   *   Defines wether anyone has access to this field or not.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\PHPUnit_Framework_MockObject_MockObject
   *   The mocked field items.
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
   * Creates a mock field entity reference field item list.
   *
   * @param bool $access
   *   Defines wether anyone has access to this field or not.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|\PHPUnit_Framework_MockObject_MockObject
   *   The mocked field items.
   */
  protected function createMockEntityReferenceFieldItemList($name, $access = TRUE, $user_context = NULL) {
    $mock = $this->getMock('Drupal\Core\Field\EntityReferenceFieldItemListInterface');
    $mock->method('access')
      ->with('view', $user_context)
      ->will($this->returnValue($access));

    $field_def = $this->getMock('\Drupal\Core\Field\FieldDefinitionInterface');
    $field_def->method('getName')->willReturn($name);
    $field_def->method('getType')->willReturn('entity_reference');

    $mock->method('getValue')->willReturn('bla');

    $referenced_entities = [];
    $entity1 = $this->getMock('\Drupal\Core\Entity\EntityInterface');
    $entity1->method('id')->willReturn('test-id-reference-1');
    $entity1->method('uuid')->willReturn('test-uuid-reference-1');
    $referenced_entities[] = $entity1;

    $entity2 = $this->getMock('\Drupal\Core\Entity\EntityInterface');
    $entity2->method('id')->willReturn('test-id-reference-2');
    $entity2->method('uuid')->willReturn('test-uuid-reference-2');
    $referenced_entities[] = $entity2;

    $mock->method('getFieldDefinition')->willReturn($field_def);
    $mock->method('referencedEntities')->willReturn($referenced_entities);

    return $mock;
  }

  /**
   * Creates a mock language list.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]|\PHPUnit_Framework_MockObject_MockObject
   *   The mocked Languages.
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
