<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Acquia\ContentHubClient\Attribute;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\content_hub_connector\ContentHubConnectorException;
use Drupal\Core\Entity\ContentEntityInterface;
use Acquia\ContentHubClient\Entity as ChubEntity;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Converts the Drupal entity object to a Acquia Content Hub CDF array.
 */
class ContentEntityNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   * The specific content hub keys.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $contentHubAdminConfig;

  /**
   * The content entity view modes normalizer.
   *
   * @var \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractor
   */
  protected $contentEntityViewModesNormalizer;

  /**
   * The module handler service to create alter hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Base root path of the application.
   *
   * @var string
   */
  protected $baseRoot;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractorInterface $content_entity_view_modes_normalizer
   *   The content entity view modes normalizer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_hander
   *   The module handler to create alter hooks.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ContentEntityViewModesExtractorInterface $content_entity_view_modes_normalizer, ModuleHandlerInterface $module_handler) {
    $this->contentHubAdminConfig = $config_factory->get('content_hub_connector.admin_settings');
    $this->contentEntityViewModesNormalizer = $content_entity_view_modes_normalizer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Return the global base_root variable that is defined by Drupal.
   *
   * We set this to a function so it can be overridden in a PHPUnit test.
   *
   * @return string
   */
  public function getBaseRoot() {
    if (isset($GLOBALS['base_root'])) {
      return $GLOBALS['base_root'];
    }
    return '';
  }

  /**
   * Normalizes an object into a set of arrays/scalars.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Object to normalize. Due to the constraints of the class, we know that
   *   the object will be of the ContentEntityInterface type.
   * @param string $format
   *   The format that the normalization result will be encoded as.
   * @param array $context
   *   Context options for the normalizer.
   *
   * @return array|string|bool|int|float|null
   *   Return normalized data.
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    $context += array(
      'account' => NULL,
    );

    // Exit if the class does not support normalizing to the given format.
    if (!$this->supportsNormalization($entity, $format)) {
      return NULL;
    }

    // Set our required CDF properties.
    $entity_type_id = $context['entity_type'] = $entity->getEntityTypeId();
    $entity_uuid = $entity->uuid();
    $origin = $this->contentHubAdminConfig->get('origin');

    // Required Created field.
    if ($entity->get('created')) {
      $created = date('c', $entity->get('created')->getValue()[0]['value']);
    }
    else {
      $created = date('c');
    }

    // Required Modified field.
    if ($entity->get('changed')) {
      $modified = date('c', $entity->get('changed')->getValue()[0]['value']);
    }
    else {
      $modified = date('c');
    }

    // Base Root Path.
    $base_root = $this->getBaseRoot();

    // Initialize Content Hub entity.
    $content_hub_entity = new ChubEntity();
    $content_hub_entity
      ->setUuid($entity_uuid)
      ->setType($entity_type_id)
      ->setOrigin($origin)
      ->setCreated($created)
      ->setModified($modified);

    if ($view_modes = $this->contentEntityViewModesNormalizer->getRenderedViewModes($entity)) {
      $content_hub_entity->setMetadata(array(
        'base_root' => $base_root,
        'view_modes' => $view_modes,
      ));
    }

    // We have to iterate over the entity translations and add all the
    // translations versions.
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $language) {
      $langcode = $language->getId();
      $localized_entity = $entity->getTranslation($langcode);
      $content_hub_entity = $this->addFieldsToContentHubEntity($content_hub_entity, $localized_entity, $langcode, $context);
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
   * Get fields from given entity.
   *
   * Get the fields from a given entity and add them to the given content hub
   * entity object.
   *
   * @param \Acquia\ContentHubClient\Entity $content_hub_entity
   *   The Content Hub Entity that will contain all the Drupal entity fields.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Entity.
   * @param string $langcode
   *   The language that we are parsing.
   * @param array $context
   *   Additional Context such as the account.
   *
   * @return \Acquia\ContentHubClient\Entity ChubEntity
   *   The Content Hub Entity with all the data in it.
   *
   * @throws \Drupal\content_hub_connector\ContentHubConnectorException
   *   The Exception will be thrown if something is going awol.
   */
  protected function addFieldsToContentHubEntity(ChubEntity $content_hub_entity, \Drupal\Core\Entity\ContentEntityInterface $entity, $langcode = 'und', array $context = array()) {
    /** @var \Drupal\Core\Field\FieldItemListInterface[] $fields */
    $fields = $entity->getFields();

    // Get our field mapping. This maps drupal field types to Content Hub
    // attribute types.
    $type_mapping = $this->getFieldTypeMapping();

    // Ignore the entity ID and revision ID.
    // Excluded comes here.
    $excluded_fields = $this->excludedProperties($entity);
    foreach ($fields as $name => $field) {
      // Continue if this is an excluded field or the current user does not
      // have access to view it.
      if (in_array($field->getFieldDefinition()->getName(), $excluded_fields) || !$field->access('view', $context['account'])) {
        continue;
      }

      // Get the plain version of the field in regular json.
      $serialized_field = $this->serializer->normalize($field, 'json', $context);
      $items = $serialized_field;
      // If there's nothing in this field, ignore it.
      if ($items == NULL) {
        continue;
      }

      // Try to map it to a known field type.
      $field_type = $field->getFieldDefinition()->getType();
      // Go to the fallback data type when the field type is not known.
      $type = $type_mapping['fallback'];
      if (isset($type_mapping[$name])) {
        $type = $type_mapping[$name];
      }
      elseif (isset($type_mapping[$field_type])) {
        // Set it to the fallback type which is string.
        $type = $type_mapping[$field_type];
      }

      $values = array();
      if ($field instanceof \Drupal\Core\Field\EntityReferenceFieldItemListInterface) {

        /** @var \Drupal\Core\Entity\EntityInterface[] $referenced_entities */
        $referenced_entities = $field->referencedEntities();
        /*
         * @todo Should we check the class type here?
         * I think we need to make sure it is also an entity that we support?
         * The return value could be anything that is compatible with TypedData.
         */
        foreach ($referenced_entities as $referenced_entity) {

          // Special case for type as we do not want the reference for the
          // bundle.
          if ($name === 'type') {
            $values[$langcode][] = $referenced_entity->id();
          }
          else {
            $values[$langcode][] = $referenced_entity->uuid();
          }
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
      try {
        $attribute = new \Acquia\ContentHubClient\Attribute($type);
      }
      catch (\Exception $e) {
        $args['%type'] = $type;
        $message = new FormattableMarkup('No type could be registered for %type.', $args);
        throw new ContentHubConnectorException($message);
      }

      if (strstr($type, 'array')) {
        $attribute->setValues($values);
      }
      else {
        $value = array_pop($values[$langcode]);
        $attribute->setValue($value, $langcode);
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

    // Allow alterations of the CDF to happen.
    $context['entity'] = $entity;
    $context['langcode'] = $langcode;
    $this->moduleHandler->alter('content_hub_connector_cdf', $content_hub_entity, $context);

    return $content_hub_entity;
  }

  /**
   * Append to existing values of Content Hub Attribute.
   *
   * @param \Acquia\ContentHubClient\Attribute $attribute
   *   The attribute.
   * @param array $values
   *   The attribute's values.
   */
  public function appendToAttribute(Attribute $attribute, $values) {
    $old_values = $attribute->getValues();
    $values = array_merge($old_values, $values);
    $attribute->setValues($values);
  }

  /**
   * Retrieves the mapping for known data types to Content Hub's internal types.
   *
   * Inspired by the getFieldTypeMapping in search_api.
   *
   * Search API uses the complex data format to normalize the data into a
   * document-structure suitable for search engines. However, since content hub
   * for Drupal 8 just got started, it focusses on the field types for now
   * instead of on the complex data types. Changing this architecture would
   * mean that we have to adopt a very similar structure as can be seen in the
   * Utility class of Search API. That would also mean we no longer have to
   * explicitly support certain field types as they map back to the known
   * complex data types such as string, uri that are known in Drupal Core.
   *
   * @return string[]
   *   An array mapping all known (and supported) Drupal field types to their
   *   corresponding Content Hub data types. Empty values mean that fields of
   *   that type should be ignored by the Content Hub.
   *
   * @see hook_content_hub_connector_field_type_mapping_alter()
   */
  public function getFieldTypeMapping() {
    $mapping = array();
    // It's easier to write and understand this array in the form of
    // $default_mapping => array($data_types) and flip it below.
    $default_mapping = array(
      'string' => array(
        // These are special field names that we do not want to parse as
        // arrays.
        'title',
        'langcode',
      ),
      'array<string>' => array(
        'fallback',
      ),
      'array<reference>' => array(
        'entity_reference',
      ),
      'array<integer>' => array(
        'integer',
        'timespan',
        'timestamp',
      ),
      'array<number>' => array(
        'decimal',
        'float',
      ),
      // Types we know about but want/have to ignore.
      NULL => array(
        'password',
        'file',
        'image',
      ),
      'array<boolean>' => array(
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
    $this->moduleHandler->alter('content_hub_connector_field_type_mapping', $mapping);

    return $mapping;
  }

  /**
   * Provides a list of entity properties that will be excluded from the CDF.
   *
   * When building the CDF entity for the Content Hub we are exporting Drupal
   * entities that will be imported by other Drupal sites, so nids, tids, fids,
   * etc. should not be transferred, as they will be different in different
   * Drupal sites. We are relying in Drupal <uuid>'s as the entity identifier.
   * So <uuid>'s will persist through the different sites.
   * (We will need to verify this claim!)
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return array
   *   An array of excluded properties.
   */
  protected function excludedProperties(ContentEntityInterface $entity) {
    $excluded = array(
      // The following properties are always included in constructor, so we do
      // not need to check them again.
      'id',
      'revision',
      'uuid',
      // 'type',.
      'created',
      'changed',

      // Getting rid of workflow fields.
      'status',
      'sticky',
      'promote',

      // Getting rid of identifiers and others.
      'vid',
      'nid',
      'fid',
      'tid',
      'uid',
      'cid',

      // Do not send revisions.
      'revision_uid',
      'revision_translation_affected',
      'revision_timestamp',

      // Translation fields.
      'content_translation_outdated',
      'content_translation_source',
      'default_langcode',

      // Do not include comments.
      'comment',
      'comment_count',
      'comment_count_new',
    );

    $excluded_to_alter = array();

    // Allow users to define more excluded properties.
    // Allow other modules to intercept and define what default type they want
    // to use for their data type.
    $this->moduleHandler->alter('content_hub_connector_exclude_fields', $excluded_to_alter, $entity);
    $excluded = array_merge($excluded, $excluded_to_alter);
    return $excluded;
  }

  /**
   * Denormalizes data back into an object of the given class.
   *
   * @param mixed $data
   *   Data to restore.
   * @param string $class
   *   The expected class to instantiate.
   * @param string $format
   *   Format the given data was extracted from.
   * @param array $context
   *   Options available to the denormalizer.
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    // TODO: Implement denormalize() method.
  }

}
