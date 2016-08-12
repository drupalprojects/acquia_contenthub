<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Normalizer\ContentEntityCdfNormalizer.
 */

namespace Drupal\acquia_contenthub\Normalizer;

use Acquia\ContentHubClient\Asset;
use Acquia\ContentHubClient\Attribute;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\acquia_contenthub\ContentHubException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Acquia\ContentHubClient\Entity as ContentHubEntity;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Render\Renderer;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Json;
use Drupal\acquia_contenthub\EntityManager as EntityManager;

/**
 * Converts the Drupal entity object to a Acquia Content Hub CDF array.
 */
class ContentEntityCdfNormalizer extends NormalizerBase {

  /**
   * The format that the Normalizer can handle.
   *
   * @var string
   */
  protected $format = 'acquia_contenthub_cdf';

  /**
   * Base url.
   *
   * @var string
   */
  protected $baseUrl;

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
   * @var \Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractor
   */
  protected $contentEntityViewModesNormalizer;

  /**
   * The module handler service to create alter hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Entity Repository.
   *
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected  $entityRepository;

  /**
   * Base root path of the application.
   *
   * @var string
   */
  protected $baseRoot;

  /**
   * The Basic HTTP Kernel to make requests.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $kernel;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The entity manager.
   *
   * @var \Drupal\acquia_contenthub\EntityManager
   */
  protected $entityManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('acquia_contenthub.normalizer.content_entity_view_modes_extractor'),
      $container->get('module_handler'),
      $container->get('entity.repository'),
      $container->get('http_kernel.basic'),
      $container->get('renderer'),
      $container->get('acquia_contenthub.entity_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractorInterface $content_entity_view_modes_normalizer
   *   The content entity view modes normalizer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to create alter hooks.
   * @param \Drupal\Core\Entity\EntityRepository $entity_repository
   *   The entity repository.
   * @param \Drupal\acquia_contenthub\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ContentEntityViewModesExtractorInterface $content_entity_view_modes_normalizer, ModuleHandlerInterface $module_handler, EntityRepository $entity_repository, HttpKernelInterface $kernel, Renderer $renderer, EntityManager $entity_manager, EntityTypeManagerInterface $entity_type_manager) {
    global $base_url;
    $this->baseUrl = $base_url;
    $this->contentHubAdminConfig = $config_factory->get('acquia_contenthub.admin_settings');
    $this->contentEntityViewModesNormalizer = $content_entity_view_modes_normalizer;
    $this->moduleHandler = $module_handler;
    $this->entityRepository = $entity_repository;
    $this->kernel = $kernel;
    $this->renderer = $renderer;
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Return the global base_root variable that is defined by Drupal.
   *
   * We set this to a function so it can be overridden in a PHPUnit test.
   *
   * @return string
   *   Return global base_root variable.
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
   * @param object $entity
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
  public function normalize($entity, $format = NULL, array $context = []) {
    $context += ['account' => NULL];

    // Exit if the class does not support normalizing to the given format.
    if (!$this->supportsNormalization($entity, $format)) {
      return NULL;
    }

    // By executing the rendering here with this cache contexts, we are bubbling
    // it up to the dynamic page cache so that it varies by the query param
    // include_references. Do not remove.
    $cache = ['#cache' => ['contexts' => ['url.query_args:include_references']]];
    $this->renderer->render($cache);

    // Add query params to the context.
    $current_uri = \Drupal::request()->getRequestUri();
    $uri = UrlHelper::parse($current_uri);
    $context += ['query_params' => $uri['query']];

    // Set our required CDF properties.
    $entity_type_id = $context['entity_type'] = $entity->getEntityTypeId();
    $entity_uuid = $entity->uuid();
    $origin = $this->contentHubAdminConfig->get('origin');

    // Required Created field.
    if ($entity->hasField('created') && $entity->get('created')) {
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
    $contenthub_entity = new ContentHubEntity();
    $contenthub_entity
      ->setUuid($entity_uuid)
      ->setType($entity_type_id)
      ->setOrigin($origin)
      ->setCreated($created)
      ->setModified($modified);

    if ($view_modes = $this->contentEntityViewModesNormalizer->getRenderedViewModes($entity)) {
      $contenthub_entity->setMetadata([
        'base_root' => $base_root,
        'view_modes' => $view_modes,
      ]);
    }

    // We have to iterate over the entity translations and add all the
    // translations versions.
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $language) {
      $langcode = $language->getId();
      $localized_entity = $entity->getTranslation($langcode);
      $contenthub_entity = $this->addFieldsToContentHubEntity($contenthub_entity, $localized_entity, $langcode, $context);
    }

    // Create the array of normalized fields, starting with the URI.
    $normalized = [
      'entities' => [$contenthub_entity],
    ];

    // Add all references to it if the include_references is true.
    if (!empty($context['query_params']['include_references']) && $context['query_params']['include_references'] == 'true') {

      $referenced_entities = [];
      $referenced_entities = $this->getMultilevelReferencedFields($entity, $referenced_entities, $context);

      foreach ($referenced_entities as $entity) {
        // Check if entity is a valid entity to be pushed to HUB.
        if ($this->entityManager->isEligibleEntity($entity)) {
          // Generate our URL where the isolated rendered view mode lives.
          // This is the best way to really make sure the content in Content Hub
          // and the content shown to any user is 100% the same.
          try {
            $url = Url::fromRoute('acquia_contenthub.entity.' . $entity->getEntityTypeId() . '.GET.acquia_contenthub_cdf', [
              'entity_type' => $entity->getEntityTypeId(),
              'entity_id' => $entity->id(),
              $entity->getEntityTypeId() => $entity->id(),
              '_format' => 'acquia_contenthub_cdf',
            ])->toString();
            $request = Request::create($url);
            /** @var \Drupal\Core\Render\HtmlResponse $response */
            $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);
            $referenced_entity_cdf_json = $response->getContent();
            $referenced_entity_list_cdf = Json::decode($referenced_entity_cdf_json);
            $referenced_entity_list_cdf = array_pop($referenced_entity_list_cdf);
            if (is_array($referenced_entity_list_cdf)) {
              foreach ($referenced_entity_list_cdf as $referenced_entity_cdf) {
                $normalized['entities'][] = $referenced_entity_cdf;
              }
            }
          }
          catch (\Exception $e) {
            // Do nothing, route does not exist.
          }
        }
      }
    }

    return $normalized;

  }

  /**
   * Get fields from given entity.
   *
   * Get the fields from a given entity and add them to the given content hub
   * entity object.
   *
   * @param \Acquia\ContentHubClient\Entity $contenthub_entity
   *   The Content Hub Entity that will contain all the Drupal entity fields.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Entity.
   * @param string $langcode
   *   The language that we are parsing.
   * @param array $context
   *   Additional Context such as the account.
   *
   * @return \Acquia\ContentHubClient\Entity ContentHubEntity
   *   The Content Hub Entity with all the data in it.
   *
   * @throws \Drupal\acquia_contenthub\ContentHubException
   *   The Exception will be thrown if something is going awol.
   */
  protected function addFieldsToContentHubEntity(ContentHubEntity $contenthub_entity, \Drupal\Core\Entity\ContentEntityInterface $entity, $langcode = 'und', array $context = array()) {
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

      if ($type == NULL) {
        continue;
      }

      $values = [];
      if ($field instanceof \Drupal\Core\Field\EntityReferenceFieldItemListInterface) {

        /** @var \Drupal\Core\Entity\EntityInterface[] $referenced_entities */
        $referenced_entities = $field->referencedEntities();
        /*
         * @todo Should we check the class type here?
         * I think we need to make sure it is also an entity that we support?
         * The return value could be anything that is compatible with TypedData.
         */
        foreach ($referenced_entities as $key => $referenced_entity) {
          // In the case of images/files, etc... we need to add the assets.
          $file_types = array(
            'image',
            'file',
            'video',
          );

          // Special case for type as we do not want the reference for the
          // bundle.
          if ($name === 'type') {
            $values[$langcode][] = $referenced_entity->id();
          }
          elseif (in_array($field_type, $file_types)) {
            // If this is a file type, then add the asset to the CDF.
            $uuid_token = '[' . $referenced_entity->uuid() . ']';
            $asset_url = file_create_url($entity->{$name}[$key]->entity->getFileUri());
            $asset = new Asset();
            $asset->setUrl($asset_url);
            $asset->setReplaceToken($uuid_token);
            $contenthub_entity->addAsset($asset);

            // Now add the value.
            $values[$langcode][] = $uuid_token;
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
        throw new ContentHubException($message);
      }

      if (strstr($type, 'array')) {
        $attribute->setValues($values);
      }
      else {
        $value = array_pop($values[$langcode]);
        $attribute->setValue($value, $langcode);
      }

      // If attribute exists already, append to the existing values.
      if (!empty($contenthub_entity->getAttribute($name))) {
        $existing_attribute = $contenthub_entity->getAttribute($name);
        $this->appendToAttribute($existing_attribute, $attribute->getValues());
        $attribute = $existing_attribute;
      }

      // Add it to our contenthub entity.
      $contenthub_entity->setAttribute($name, $attribute);
    }

    // Allow alterations of the CDF to happen.
    $context['entity'] = $entity;
    $context['langcode'] = $langcode;
    $this->moduleHandler->alter('acquia_contenthub_cdf', $contenthub_entity, $context);

    // Adds the entity URL to CDF.
    if (empty($contenthub_entity->getAttribute('url'))) {
      global $base_path;
      switch ($entity->getEntityTypeId()) {
        case 'file':
          $value = file_create_url($entity->getFileUri());
          break;

        default:
          // Get entity URL fromRoute.
          $route_name = $entity->toUrl()->getRouteName();
          $route_params = $entity->toUrl()->getRouteParameters();
          $value = Url::fromRoute($route_name, $route_params)->toString();
          $value = str_replace($base_path, '/', $value);
          $value = Url::fromUri($this->baseUrl . $value)->toUriString();
          break;
      }
      $att = new \Acquia\ContentHubClient\Attribute('string');
      $contenthub_entity->setAttribute('url', $att->setValue($value, $langcode));

      return $contenthub_entity;
    }
  }

  /**
   * Get entity reference fields.
   *
   * Get the fields from a given entity and add them to the given content hub
   * entity object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Entity.
   * @param array $context
   *   Additional Context such as the account.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[] $referenced_entities
   *   All referenced entities.
   */
  public function getReferencedFields(ContentEntityInterface $entity, array $context = array()) {
    /** @var \Drupal\Core\Field\FieldItemListInterface[] $fields */
    $fields = $entity->getFields();
    $referenced_entities = [];
    // Ignore the entity ID and revision ID.
    // Excluded comes here.
    $excluded_fields = $this->excludedProperties($entity);
    foreach ($fields as $name => $field) {
      // Continue if this is an excluded field or the current user does not
      // have access to view it.
      if (in_array($field->getFieldDefinition()->getName(), $excluded_fields) || !$field->access('view', $context['account']) || $name == 'type') {
        continue;
      }

      if ($field instanceof \Drupal\Core\Field\EntityReferenceFieldItemListInterface) {

        /** @var \Drupal\Core\Entity\EntityInterface[] $referenced_entities */
        $referenced_entities = array_merge($field->referencedEntities(), $referenced_entities);
      }
    }

    return $referenced_entities;
  }

  /**
   * Get multilevel entity reference fields.
   *
   * Get the fields from a given entity and add them to the given content hub
   * entity object. This also includes dependencies of the dependencies.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Entity.
   * @param array $referenced_entities
   *   The list of Multilevel referenced entities. This must be passed as an
   *   initialized array.
   * @param array $context
   *   Additional Context such as the account.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[] $referenced_entities
   *   All referenced entities.
   */
  public function getMultilevelReferencedFields(ContentEntityInterface $entity, &$referenced_entities, array $context = array()) {
    // Collecting all referenced_entities UUIDs.
    $uuids = [];
    foreach ($referenced_entities as $entity) {
      $uuids[] = $entity->uuid();
    }

    // Obtaining all the referenced entities for the current entity.
    $ref_entities = $this->getReferencedFields($entity, $context);
    foreach ($ref_entities as $key => $entity) {
      if (!in_array($entity->uuid(), $uuids)) {
        $referenced_entities[] = $entity;
        $this->getMultilevelReferencedFields($entity, $referenced_entities, $context);
      }
    }

    return $referenced_entities;

  }

  /**
   * Adds Content Hub Data to Drupal Entity Fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Entity.
   * @param \Acquia\ContentHubClient\Entity $contenthub_entity
   *   The Content Hub Entity.
   * @param string $langcode
   *   The language code.
   * @param array $context
   *   Context.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The Drupal Entity after integrating data from Content Hub.
   */
  protected function addFieldsToDrupalEntity(\Drupal\Core\Entity\ContentEntityInterface $entity, ContentHubEntity $contenthub_entity, $langcode = 'und', array $context = array()) {
    /** @var \Drupal\Core\Field\FieldItemListInterface[] $fields */
    $fields = $entity->getFields();

    // Get our field mapping. This maps drupal field types to Content Hub
    // attribute types.
    $type_mapping = $this->getFieldTypeMapping();

    // Ignore the entity ID and revision ID.
    // Excluded comes here.
    $excluded_fields = $this->excludedProperties($entity);
    $excluded_fields[] = 'langcode';
    $excluded_fields[] = 'type';

    // Iterate over all attributes.
    foreach ($contenthub_entity->getAttributes() as $name => $attribute) {

      // If it is an excluded property, then skip it.
      if (in_array($name, $excluded_fields)) {
        continue;
      }

      $field = $fields[$name];
      if (isset($field)) {
        // Try to map it to a known field type.
        $field_type = $field->getFieldDefinition()->getType();

        $value = $attribute['value'][$langcode];
        $output = [];

        if (strpos($type_mapping[$field_type], 'array') !== FALSE) {
          foreach ($value as $item) {
            $output = json_decode($item, TRUE);
          }
          $value = $output;
        }

        $entity->$name = $value;
      }
    }

    return $entity;
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
   * @see hook_acquia_contenthub_field_type_mapping_alter()
   */
  public function getFieldTypeMapping() {
    $mapping = [];
    // It's easier to write and understand this array in the form of
    // $default_mapping => array($data_types) and flip it below.
    $default_mapping = array(
      'string' => array(
        // These are special field names that we do not want to parse as
        // arrays.
        'title',
        'type',
        'langcode',
      ),
      'array<string>' => array(
        'fallback',
        'text_with_summary',
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
      ),
      'array<boolean>' => array(
        'boolean',
      ),
    );

    foreach ($default_mapping as $contenthub_type => $data_types) {
      foreach ($data_types as $data_type) {
        $mapping[$data_type] = $contenthub_type;
      }
    }

    // Allow other modules to intercept and define what default type they want
    // to use for their data type.
    $this->moduleHandler->alter('acquia_contenthub_field_type_mapping', $mapping);

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
      'created',
      'changed',
      'uri',

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
    $this->moduleHandler->alter('acquia_contenthub_exclude_fields', $excluded_to_alter, $entity);
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
    $context += ['account' => NULL];

    // Exit if the class does not support denormalization of the given data,
    // class and format.
    if (!$this->supportsDenormalization($data, $class, $format)) {
      return NULL;
    }

    $contenthub_entity = new ContentHubEntity($data);
    $entity_type = $contenthub_entity->getType();
    $bundle = reset($contenthub_entity->getAttribute('type')['value']);
    $langcodes = $contenthub_entity->getAttribute('langcode')['value'];

    // Does this entity exist in this site already?
    $entity = $this->entityRepository->loadEntityByUuid($entity_type, $contenthub_entity->getUuid());
    if ($entity == NULL) {

      // Transforming Content Hub Entity into a Drupal Entity.
      $values = [
        'uuid' => $contenthub_entity->getUuid(),
        'type' => $bundle,
      ];

      // Status is by default unpublished if it is a node.
      if ($entity_type == 'node') {
        $values['status'] = 0;
      }

      $entity = $this->entityTypeManager->getStorage($entity_type)->create($values);
    }

    // Assigning langcodes.
    $entity->langcode = array_values($langcodes);

    // We have to iterate over the entity translations and add all the
    // translations versions.
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $language => $languagedata) {
      // Make sure the entity language is one of the language contained in the
      // Content Hub Entity.
      if (in_array($language, $langcodes)) {
        $localized_entity = $entity->getTranslation($language);
        $entity = $this->addFieldsToDrupalEntity($localized_entity, $contenthub_entity, $language, $context);
      }
    }
    return $entity;
  }

}
