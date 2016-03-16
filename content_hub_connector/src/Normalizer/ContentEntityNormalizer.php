<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\content_hub_connector\ContentHubConnectorException;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Acquia\ContentHubClient\Entity as ChubEntity;

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
   * The hypermedia link manager.
   *
   * @var \Drupal\rest\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The specific content hub keys
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $contentHubAdminConfig;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\rest\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   */
  public function __construct(LinkManagerInterface $link_manager, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler) {
    $this->linkManager = $link_manager;
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->contentHubAdminConfig = \Drupal::config('content_hub_connector.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    $context += array(
      'account' => NULL,
    );

    $entity_type_id = $context['entity_type'] = $entity->getEntityTypeId();
    $entity_type = $this->entityManager->getDefinition($entity_type_id);
    $id_key = $entity_type->getKey('id');
    $uuid_key = $entity_type->getKey('uuid');
    $entity_uuid = $entity->uuid();


    $origin = $this->contentHubAdminConfig->get('content_hub_connector_origin');

    $created = isset($entity->created) ? date('c', $entity->created->getValue()[0]['value']) : date('c', time());
    $modified = isset($entity->changed) ? date('c', $entity->changed->getValue()[0]['value']) : date('c', time());

    $content_hub_entity = new ChubEntity();
    $content_hub_entity
      ->setUuid($entity_uuid)
      ->setType($entity_type_id)
      ->setOrigin($origin)
      ->setCreated($created)
      ->setModified($modified);

    $type_mapping = static::getFieldTypeMapping();

    /** @var \Drupal\Core\Field\FieldItemListInterface[] $fields */
    $fields = $entity->getFields();

    // Ignore the entity ID and revision ID.
    $exclude = array($entity->getEntityType()->getKey('id'), $entity->getEntityType()->getKey('revision'));
    foreach ($fields as $name => $field) {
      // Continue if this is an excluded field or the current user does not have
      // access to view it.
      if (in_array($field->getFieldDefinition()->getName(), $exclude) || !$field->access('view', $context['account'])) {
        continue;
      }

      // Get the plain version of the field in regular json
      $serialized_field = $this->serializer->normalize($field, 'json', $context);

      $field_type = $field->getFieldDefinition()->getType();
      $items = $serialized_field;
      if ($items !== NULL) {

        if (isset($type_mapping[$field_type])) {
          $type = $type_mapping[$field_type];
        }
        else if (empty($type_mapping[$field_type])) {
          // skip unsupported types.
          continue;
        }
        else {
          $args['%property'] = $field->getFieldDefinition()->getLabel();
          $args['%type'] = $field_type;
          $message = new FormattableMarkup('No default data type mapping could be found for property %property of type %type.', $args);
          throw new ContentHubConnectorException($message);
        }

        // @todo Look at this, I have no idea if this assumption is accurate.
        if (count($items) > 1) {
          $type = 'array<' . $type . '>';
        }
        try {
          $attribute = new \Acquia\ContentHubClient\Attribute('array<' . $type . '>');
        }
        catch (\Exception $e) {
          $args['%type'] = $type;
          $message =  new FormattableMarkup('No type could be registered for %type.', $args);
          throw new ContentHubConnectorException($message);
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
        foreach ($values as $langcode => $value) {
          $attribute->setValue($value, $langcode);
        }
        // Add it to our content_hub entity.
        $content_hub_entity->setAttribute($name, $attribute);
      }
    }

    // Create the array of normalized fields, starting with the URI.
    /** @var $entity \Drupal\Core\Entity\ContentEntityInterface */
    $normalized = array(
      'entities' => array(
        $content_hub_entity,
      ),
    );

    return $normalized;
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
          'uuid',
          'datetime_iso8601',
        ),
        'reference' => array(
          'entity_reference',
        ),
        'integer' => array(
          'integer',
          'timespan',
          'timestamp'
        ),
        'number' => array(
          'decimal',
          'float',
        ),
        // Types we know about but want/have to ignore.
        NULL => array(
          'password'
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
