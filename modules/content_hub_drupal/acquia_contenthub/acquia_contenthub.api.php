<?php

/**
 * @file
 * Hooks provided by the Acquia Content Hub module.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Acquia\ContentHubClient\Entity as ContentHubEntity;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the excluded field types and names that get converted into a CDF.
 *
 * Modules may implement this hook to alter the fields that
 * get excluded from being converted into a Content Hub CDF object. For example
 * the status, sticky and promote flags are excluded because they define the
 * state of a piece of content and not the piece of content itself. Acquia
 * Content Hub's main responsibility is transferring content, not the state of
 * it.
 *
 * @param array $excluded_fields
 *   The Field types that are excluded from being normalized into a CDF
 *   document.
 *
 * @see \Drupal\acquia_contenthub\Normalizer\ContentEntityNormalizer
 */
function hook_acquia_contenthub_exclude_field_alter(array &$excluded_fields, ContentEntityInterface $entity) {
  // Do not include the uuid field.
  $excluded_fields[] = 'uuid';
  // Gets a specific entity key and add it to the excluded fields array.
  $excluded_fields[] = $entity->getEntityType()->getKey('id');
}

/**
 * Alter the field type mapping that maps field types to Content Hub types.
 *
 * Modules may implement this hook to alter the field type mapping. This is used
 * to tell Acquia Content Hub which Drupal field type maps to which data type
 * in Acquia Content Hub so that it correctly stores it and it allows for better
 * filtering, searching and storage.
 *
 * Be careful with altering existing field types as it could severely damage the
 * content in your Acquia Content Hub account.
 *
 * @param array $mapping
 *   The mapping of field types to their Content Hub Types. Example:
 *   $mapping = [
 *     'entity_reference => 'array<reference>',
 *     'integer' => 'array<integer>',
 *     'timespan' => 'array<integer>',
 *     'timestamp' => 'array<integer>',
 *     ...
 *   ];
 *   Available Content Hub Types, all are also available as multiple.
 *   - integer
 *   - string
 *   - boolean
 *   - reference
 *   - number.
 *
 * @see \Drupal\acquia_contenthub\Normalizer\ContentEntityNormalizer
 */
function hook_acquia_contenthub_field_type_mapping_alter(array &$mapping) {
  $mapping['my_custom_field'] = 'array<string>';
}


/**
 * Alter the excluded field types and names that get converted into a CDF.
 *
 * Modules may implement this hook to alter...
 *
 * @param \Acquia\ContentHubClient\Entity $content_hub_entity
 *   The Acquia Content Hub entity.
 * @param array $context
 *   Array consists out of at least 3 keys:
 *
 *   array['account'] object
 *     Defines the account it is being requested as.
 *
 *   array['entity'] Drupal\Core\Entity\ContentEntityInterface
 *     The entity that is being normalized.
 *
 *   array['langcode'] string
 *    The language the object was requested to be normalized in. Usually the
 *    normalization process iterates over all languages. Careful when making
 *    a selection based on this parameter.
 *
 * @see \Drupal\acquia_contenthub\Normalizer\ContentEntityNormalizer
 */
function hook_acquia_contenthub_cdf_alter(ContentHubEntity &$content_hub_entity, array &$context) {
  $langcode = isset($context['langcode']) ? $context['langcode'] : \Drupal::languageManager()->getDefaultLanguage();
  $content_hub_entity->setAttributeValue('my_attribute', 'this_is_my_value', $langcode);
}
