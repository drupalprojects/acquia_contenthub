<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\ContentEntityCdfNormalizer.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Drupal\content_hub_connector\ContentHubConnectorException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\serialization\Normalizer\EntityNormalizer;
use Acquia\ContentHubClient\Entity as ChubEntity;

/**
 *
 */
class ContentEntityCdfNormalizer extends EntityNormalizer {

  /**
   * Static cache for the field type mapping.
   *
   * @var array
   *
   * @see getFieldTypeMapping()
   */
  protected static $fieldTypeMapping = array();

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = array('Drupal\Core\Entity\ContentEntityInterface');

  /**
   * @var string[]
   */
  protected $format = array('json');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $entity_type_id = $context['entity_type'] = $object->getEntityTypeId();
    $entity_type = $this->entityManager->getDefinition($entity_type_id);
    $id_key = $entity_type->getKey('id');
    $uuid_key = $entity_type->getKey('uuid');
    $entity_uuid = $object->uuid();

    $config = \Drupal::config('content_hub_connector.admin_settings');
    $origin = $config->get('content_hub_connector_origin');

    $created = isset($object->created) ? date('c', $object->created->getValue()[0]['value']) : date('c', time());
    $modified = isset($object->changed) ? date('c', $object->changed->getValue()[0]['value']) : date('c', time());

    $content_hub_entity = new ChubEntity();
    $content_hub_entity
        ->setUuid($entity_uuid)
        ->setType($entity_type_id)
        ->setOrigin($origin)
        ->setCreated($created)
        ->setModified($modified);

    $included_fields = array(
      'title',
      'body',
      'status',
      'promote',
      'langcode',
    );

    $type_mapping = static::getFieldTypeMapping();

    /** @todo
    /* This is probably not the cleanest way to do this but at the moment
    /* of writing this I do not understand the full chain in serializing.
     */

    /** @var /Symfony\Component\Serializer\Serializer $serializer */
    $serializer =  \Drupal::service('serializer');
    $serialized_object = $serializer->normalize($object, $format, $context);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
    $field_definitions = $object->getFieldDefinitions();
    foreach ($object as $name => $field) {
      $field_type = $field_definitions[$name]->getType();
      $items = $serialized_object[$name];
      if ($items !== NULL && in_array($name, $included_fields)) {

        if (isset($type_mapping[$field_type])) {
          $type = $type_mapping[$field_type];
        }
        else {
          $args['%property'] = $field_definitions[$name]->getLabel();
          $args['%type'] = $field_type;
          $message = new FormattableMarkup('No default data type mapping could be found for property %property of type %type.', $args);
          throw new ContentHubConnectorException($message);
        }

        // @todo Look at this, I have no idea if this assumption is accurate.
        if (count($items) > 1) {
          $attribute = new \Acquia\ContentHubClient\Attribute('array<' . $type . '>');
        }
        else {
          $attribute = new \Acquia\ContentHubClient\Attribute($type);
        }
        $values = array();

        // Loop over the items to get the values for each field.
        foreach ($items as $item) {
          $value = $item['value'];

          // Special case when Format is set. Include the whole item for
          // transferring purposes.
          if (isset($item['format'])) {
            // Why are we doing this? Looks like it happens to support D8 to D7?
            if ($item['format'] == 'basic_html') {
              $item['format'] = 'filtered_html';
            }
            $value = json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
          }
          $values[$item['lang']][] = $value;
        }

        // For compatibility, rename langcode to language.
        $name = ($name == 'langcode') ? 'language' : $name;
        // Add our language specific fields.
        foreach ($values as $langcode => $values) {
          $attribute->setValue($values, $langcode);
        }
        // Add it to our content_hub entity.
        $content_hub_entity->setAttribute($name, $attribute);
      }

      // Adding type.
      $attribute = new \Acquia\ContentHubClient\Attribute(\Acquia\ContentHubClient\Attribute::TYPE_STRING);
      $attribute->setValue($object->type->getValue()[0]['target_id']);
      $content_hub_entity->setAttribute('type', $attribute);

    }
    return array(
      'entities' => [
        0 => (array) $content_hub_entity,
      ],
    );
  }

  /**
   * Retrieves the mapping for known data types to Content Hub's internal types.
   * Inspired by the getFieldTypeMapping in search_api.
   *
   * @return string[]
   *   An array mapping all known (and supported) Drupal data types to their
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
          'string_long',
          'text_long',
          'text_with_summary',
          'text',
          'string',
          'email',
          'uri',
          'filter_format',
          'duration_iso8601',
          'field_item:path',
          'language',
        ),
        'reference' => array(
          'entity_reference'
        ),
        'integer' => array(
          'integer',
          'timespan',
        ),
        'number' => array(
          'decimal',
          'float',
        ),
        // Types we know about but want/have to ignore.
        NULL => array(
          'datetime_iso8601',
          'timestamp',
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

}
