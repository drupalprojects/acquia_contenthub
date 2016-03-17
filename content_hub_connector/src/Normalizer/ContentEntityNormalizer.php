<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Acquia\ContentHubClient\Attribute;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\content_hub_connector\ContentHubConnectorException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Acquia\ContentHubClient\Entity as ChubEntity;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Converts the Drupal entity object structure to a Acquia Content Hub CDF array
 * structure.
 */
class ContentEntityNormalizer extends NormalizerBase {

  /**
   * Static cache for the field type mapping.
   *
   * @var array
   *
   * @see getFieldTypeMapping()
   */
  protected static $fieldTypeMapping = array();

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   * The specific content hub keys
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $contentHubAdminConfig;

  /**g
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Constructs an ContentEntityNormalizer object.
   */
  public function __construct(LoggerChannelFactory $logger_factory) {
    $this->loggerFactory = $logger_factory;
    $this->contentHubAdminConfig = \Drupal::config('content_hub_connector.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $context += array(
      'account' => NULL,
    );

    // Make sure it's a content entity. Maybe this check is not necessary but
    // to be sure we execute it anyway.
    if (false === $object instanceof \Drupal\Core\Entity\ContentEntityInterface) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $object;

    // Set our required CDF properties
    $entity_type_id = $context['entity_type'] = $entity->getEntityTypeId();
    $entity_uuid = $entity->uuid();
    $origin = $this->contentHubAdminConfig->get('origin');
    $created = date('c', $entity->get('created')->getValue()[0]['value']);
    $modified = date('c', $entity->get('created')->getValue()[0]['value']);
    $language = $entity->language();

    // Initialize Content Hub entity.
    $content_hub_entity = new ChubEntity();
    $content_hub_entity
      ->setUuid($entity_uuid)
      ->setType($entity_type_id)
      ->setOrigin($origin)
      ->setCreated($created)
      ->setModified($modified);

    // Get our field mapping. This maps drupal field types to Content Hub
    // attribute types.
    $type_mapping = static::getFieldTypeMapping();

    // We have to iterate over the entity translations and add all the
    // translations here as well.
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $language) {

      $langcode = $language->getId();
      $localized_entity = $entity->getTranslation($langcode);
      /** @var \Drupal\Core\Field\FieldItemListInterface[] $fields */
      $fields = $localized_entity->getFields();

      // Add bundle/type attribute.
      // If attribute exists already, append to the existing values.
      $attribute = new \Acquia\ContentHubClient\Attribute("string");
      $attribute->setValue($localized_entity->bundle(), $langcode);
      if (!empty($content_hub_entity->getAttribute('type'))) {
        $existing_attribute = $content_hub_entity->getAttribute('type');
        $this->appendToAttribute($existing_attribute, $attribute->getValues());
        $attribute = $existing_attribute;
      }
      $content_hub_entity->setAttribute('type', $attribute);

      // Ignore the entity ID and revision ID.
      $exclude = array($localized_entity->getEntityType()->getKey('id'), $localized_entity->getEntityType()->getKey('revision'), 'type');
      foreach ($fields as $name => $field) {
        // Continue if this is an excluded field or the current user does not
        // have access to view it.
        if (in_array($field->getFieldDefinition()->getName(), $exclude) || !$field->access('view', $context['account'])) {
          continue;
        }

        // Get the plain version of the field in regular json
        $serialized_field = $this->serializer->normalize($field, 'json', $context);
        $items = $serialized_field;

        // If there's nothing in this field, ignore it.
        if ($items == NULL) {
          continue;
        }

        // Try to map it to a known field type.
        $field_type = $field->getFieldDefinition()->getType();
        // Print an error to watchdog if the drupal field type is not known.
        if (!isset($type_mapping[$field_type])) {
          $args['%property'] = $field->getFieldDefinition()->getLabel();
          $args['%type'] = $field_type;
          $message = new FormattableMarkup('No default field type mapping could be found for property %property of field type %type.', $args);
          $this->loggerFactory->get('content_hub_connector')->error($message);
          continue;
        }

        // Skip unsupported field types.
        if (empty($type_mapping[$field_type])) {
          continue;
        }

        $values = array();

        if ($field instanceof \Drupal\Core\Field\EntityReferenceFieldItemList) {

          // Make sure it's a EntityReferenceFieldItemList. Maybe this check
          // is not necessary but to be sure we execute it anyway.
          if (false === $field instanceof \Drupal\Core\Field\EntityReferenceFieldItemList) {
            return FALSE;
          }

          /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $field */
          $referenced_entities = $field->referencedEntities();
          foreach ($referenced_entities as $referenced_entity) {
            $values[$langcode][] = $referenced_entity->uuid();
          }
        }
        else {
          // Loop over the items to get the values for each field.
          foreach ($items as $item) {
            $keys = array_keys($item);
            if (count($keys) == 1 && isset($item['value'])) {
              $value = $item['value'];
            }
            else {
              $value = json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            }
            $values[$langcode][] = $value;
          }
        }

        // Count if we need to use an array as type or not.
        $count = 0;
        foreach ($values as $language => $l_values) {
          if ($count > 1) {
            break;
          }
          $count = count($l_values);
        }

        // Set our attribute type and assign the values to it.
        $type = $type_mapping[$field_type];
        try {
          // If we have multiple languages or multiple values, always use an
          // array.
          if ($count > 1 || count($values) > 1) {
            $type = 'array<' . $type_mapping[$field_type] . '>';
            $attribute = new \Acquia\ContentHubClient\Attribute($type);
            $attribute->setValues($values);
          }
          else {
            $attribute = new \Acquia\ContentHubClient\Attribute($type);
            $value = array_pop($values[$language]);
            $attribute->setValue($value, $language);
          }
        }
        catch (\Exception $e) {
          $args['%type'] = $type;
          $message =  new FormattableMarkup('No type could be registered for %type.', $args);
          throw new ContentHubConnectorException($message);
        }

        // For compatibility with Drupal 7, duplicate langcode to language.
        if ($name == 'langcode') {
          $content_hub_entity->setAttribute('language', $attribute);
        }

        // If attribute exists already, append to the existing values.
        if (!empty($content_hub_entity->getAttribute($name))) {
          $existing_attribute = $content_hub_entity->getAttribute($name);
          $this->appendToAttribute($existing_attribute, $attribute->getValues());
          $attribute = $existing_attribute;
        }

        // Add it to our content_hub entity.
        $content_hub_entity->setAttribute($name, $attribute);
      }
    }

    // Create the array of normalized fields, starting with the URI.
    $normalized = array(
      'entities' => array(
        $content_hub_entity,
      ),
    );

    return $normalized;
  }

  /**
   * Append to existing values of Content Hub Attribute
   *
   * @param \Acquia\ContentHubClient\Attribute $attribute
   * @param array $values
   *
   */
  public function appendToAttribute(Attribute $attribute, $values) {
    $old_values = $attribute->getValues();
    $values = array_merge($old_values, $values);
    $attribute->setValues($values);
  }

  /**
   * Retrieves the mapping for known data types to Content Hub's internal types.
   * Inspired by the getFieldTypeMapping in search_api.
   *
   * Search API uses the complex data format to normalize the data into a
   * documentstructure suitable for search engines. However, since content hub
   * for Drupal 8 just got started, it focusses on the field types for now
   * instead of on the complex data types. Changing this architecture would
   * mean that we have to adopt a very similar structure as can be seen in the
   * Utility class of Search API. That would also mean we no longer have to
   * explicitely support certain field types as they map back to the known
   * complex data types such as string, uri that are known in Drupal Core.
   *
   * @return string[]
   *   An array mapping all known (and supported) Drupal field types to their
   *   corresponding Content Hub data types. Empty values mean that fields of
   *   that type should be ignored by the Content Hub.
   *
   * @see hook_content_hub_connector_field_type_mapping_alter()
   */
  public static function getFieldTypeMapping() {
    // Check the static cache first.
    if (empty(static::$fieldTypeMapping)) {
      // It's easier to write and understand this array in the form of
      // $search_api_field_type => array($data_types) and flip it below.
      $default_mapping = array(
        'string' => array(
          // Core Fields FieldTypes
          'changed',
          'created',
          'email',
          'language',
          'map',
          'string',
          'string_long',
          'timestamp',
          'uri',
          'uuid',

          // text module FieldTypes
          'text',
          'text_long',
          'text_with_summary',

          // comment module FieldTypes
          'comment',

          // datetime module FieldTypes
          'datetime',

          // link module FieldTypes
          'link',

        ),
        'reference' => array(
          'entity_reference',
        ),
        'integer' => array(
          'integer',
          'timespan',
          'timestamp',
        ),
        'number' => array(
          'decimal',
          'float',
        ),
        // Types we know about but want/have to ignore.
        NULL => array(
          'password',
          'file',
          'image',

        ),
        'boolean' => array(
          'boolean',
        ),
      );

      foreach ($default_mapping as $content_hub_type => $data_types) {
        foreach ($data_types as $data_type) {
          $mapping[$data_type] = $content_hub_type;
        }
      }

      // Allow other modules to intercept and define what default type they want
      // to use for their data type.
      \Drupal::moduleHandler()->alter('content_hub_connector_field_type_mapping', $mapping);

      static::$fieldTypeMapping = $mapping;
    }

    return static::$fieldTypeMapping;
  }

  /**
   * Denormalizes data back into an object of the given class.
   *
   * @param mixed $data data to restore
   * @param string $class the expected class to instantiate
   * @param string $format format the given data was extracted from
   * @param array $context options available to the denormalizer
   *
   * @return object
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    // TODO: Implement denormalize() method.
  }
}
