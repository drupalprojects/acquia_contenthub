<?php
/**
 * @file
 * ContentHub Drush Commands.
 */

use Drupal\Component\Uuid\Uuid;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\ServerErrorResponseException;
use GuzzleHttp\Exception\ClientException;

/**
 * Implements hook_drush_command().
 */
function acquia_contenthub_drush_command() {
  $items["acquia-content-hub-local"] = array(
    "description" => "Prints the CDF from a local source (drupal site)",
    "arguments" => array(
      "entity-type" => "Entity type",
      "entity-id" => "Entity identifier or entity's UUID",
    ),
    "outputformat" => array(
      "default" => "json",
      'pipe-format' => 'config',
      'variable-name' => 'variables',
      'table-metadata' => array(
        'format' => 'var_export',
      ),
      'require-engine-capability' => array('format-list'),
    ),
    "required-arguments" => 2,
    "aliases" => array("ach-lo"),
  );

  $items["acquia-content-hub-remote"] = array(
    "description" => "Prints the CDF from a remote source (Content Hub)",
    "arguments" => array(
      "uuid" => "Entity's UUID",
    ),
    "required-arguments" => 1,
    "outputformat" => array(
      "default" => "json",
      'pipe-format' => 'config',
      'variable-name' => 'variables',
      'table-metadata' => array(
        'format' => 'var_export',
      ),
      'require-engine-capability' => array('format-list'),
    ),
    "aliases" => array("ach-re"),
  );

  $items["acquia-content-hub-compare"] = array(
    "description" => "Loads the CDF from a local and remote source, compares them and prints the differences.",
    "arguments" => array(
      "entity-type" => "Entity type",
      "uuid" => "Entity's UUID",
    ),
    "required-arguments" => 2,
    "aliases" => array("ach-comp"),
  );

  $items["acquia-content-hub-list"] = array(
    "description" => "List entities from the Content Hub using the listEntities() method.",
    "options" => array(
      "limit" => array(
        "description" => "The number of entities to be listed",
        "example_value" => "5",
      ),
      "start" => array(
        "description" => "The offset to start listing the entities (Useful for pagination).",
        "example_value" => "5",
      ),
      "origin" => array(
        "description" => "The Client's Origin UUID.",
        "example_value" => "00000000-0000-0000-0000-000000000000",
      ),
      "language" => array(
        "description" => "The Language that will be used to filter field values.",
        "example_value" => "en",
      ),
      "attributes" => array(
        "description" => "The attributes to display for all listed entities",
        "example_value" => "status,title",
      ),
      "type" => array(
        "description" => "The entity type",
        "example-value" => "node",
      ),
      "filters" => array(
        "description" => "Filters entities according to a set of of conditions as a key=value pair separated by commas. You could use regex too.",
        "example_value" => "title=New*,status=1",
      ),
    ),
    "outputformat" => array(
      "default" => "json",
      'pipe-format' => 'config',
      'variable-name' => 'variables',
      'table-metadata' => array(
        'format' => 'var_export',
      ),
      'require-engine-capability' => array('format-list'),
    ),
    "aliases" => array("ach-list"),
  );

  $items["acquia-content-hub-delete"] = array(
    "description" => "Deletes a single entity from the Content Hub",
    "arguments" => array(
      "uuid" => "Entity's UUID",
    ),
    "required-arguments" => 1,
    "aliases" => array("ach-del"),
  );

  $items["acquia-content-hub-purge"] = array(
    "description" => "Deletes all entities from the Content Hub",
    "aliases" => array("ach-purge"),
  );

  $items["acquia-content-hub-regenerate-secret"] = array(
    "description" => "Regenerates the Shared Secret used for Webhook Verification",
    "aliases" => array("ach-regsec"),
  );

  return $items;
}

/**
 * Loads and prints a local entity from Drupal in CDF Format.
 *
 * @param string $entity_type
 *   Entity type.
 * @param string $entity_id
 *   Entity's Uuid of entity's Identifier.
 *
 * @return array|bool
 *   Returns and array containing the CDF of a local entity, FALSE otherwise.
 */
function drush_acquia_contenthub_content_hub_local($entity_type, $entity_id) {
  $entity_type_manager = \Drupal::entityTypeManager();

  /** @var \Symfony\Component\Serializer\Serializer $serializer */
  $serializer = \Drupal::service('serializer');

  /** @var \Drupal\Core\Entity\EntityRepository $entity_repository */
  $entity_repository = \Drupal::service('entity.repository');

  if (empty($entity_type) || empty($entity_id)) {
    return drush_set_error(dt("Missing required parameters: entity_type and entity_id (or entity's uuid)"));
  }
  elseif (!$entity_type_manager->getDefinition($entity_type)) {
    return drush_set_error(dt("Entity type @entity_type does not exist", array(
      '@entity_type' => $entity_type,
    )));
  }
  else {
    if (Uuid::isValid($entity_id)) {
      $entity = $entity_repository->loadEntityByUuid($entity_type, $entity_id);
    }
    else {
      $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);
    }
  }

  if (!$entity) {
    drush_print(dt("Entity having entity_type = @entity_type and entity_id = @entity_id does not exist.", array(
      '@entity_type' => $entity_type,
      '@entity_id' => $entity_id,
    )));
  }
  // If nothing else, return our object structure.
  $output = $serializer->normalize($entity, 'content_hub_cdf');
  return $output;
}

/**
 * Loads and prints a remote entity in CDF Format.
 *
 * @param string $uuid
 *   Entity's UUID.
 *
 * @return array|bool
 *   Returns an array containing the CDF of a remote entity
 */
function drush_acquia_contenthub_content_hub_remote($uuid) {
  if (Uuid::isValid($uuid)) {
    return drush_set_error(dt("Argument provided is not a UUID."));
  }
  else {
    /** @var \Drupal\acquia_contenthub\Client\ClientManager $client_manager */
    $client_manager = \Drupal::service('acquia_contenthub.client_manager');
    $client = $client_manager->getClient();
    if ($entity = $client->readEntity($uuid)) {
      return (array) $entity;
    }
    else {
      drush_print(dt("The Content Hub does not have an entity with UUID = @uuid.", array(
        '@uuid' => $uuid,
      )));
    }
  }
  return FALSE;
}


/**
 * Compares a CDF entity from local and remote source.
 *
 * @param string $entity_type
 *   The Entity type.
 * @param string $uuid
 *   The Entity's UUID.
 *
 * @return array
 *   Returns an array containing the differences between local and
 *   remote entities.
 */
function drush_acquia_contenthub_content_hub_compare($entity_type, $uuid) {
  $entity_type_manager = \Drupal::entityTypeManager();

  if (!$entity_type_manager->getDefinition($entity_type)) {
    return drush_set_error(dt("The entity type provided does not exist."));
  }

  if (!Uuid::isValid($uuid)) {
    return drush_set_error(dt("Argument provided is not a UUID."));
  }

  /** @var \Symfony\Component\Serializer\Serializer $serializer */
  $serializer = \Drupal::service('serializer');

  /** @var \Drupal\Core\Entity\EntityRepository $entity_repository */
  $entity_repository = \Drupal::service('entity.repository');

  /** @var \Drupal\acquia_contenthub\Client\ClientManager $client_manager */
  $client_manager = \Drupal::service('acquia_contenthub.client_manager');
  $client = $client_manager->getClient();

  // Get our local CDF version.
  $local_entity = $entity_repository->loadEntityByUuid($entity_type, $uuid);
  $local_cdf = $serializer->normalize($local_entity, 'content_hub_cdf');
  if (!$local_cdf) {
    $local_cdf = array();
  }

  // Get the Remote CDF version.
  $remote_cdf = $client->readEntity($uuid);
  if (!$remote_cdf) {
    $remote_cdf = array();
  }

  $local_compare = array_diff($local_cdf, (array) $remote_cdf);
  drush_print("Data from the local entity that doesn't appear in the remote entity, retrieved from Content Hub Backend:");
  drush_print_r(json_encode($local_compare, JSON_PRETTY_PRINT));
  drush_print("Data from the remote entity that doesn't appear in the local entity:");
  $remote_compare = array_diff((array) $remote_cdf, $local_cdf);
  drush_print_r(json_encode($remote_compare, JSON_PRETTY_PRINT));
}

/**
 * Lists entities from the Content Hub.
 *
 * @return void|array
 *   Returns a list of Content Hub Entities
 */
function drush_acquia_contenthub_content_hub_list() {
  /** @var \Drupal\acquia_contenthub\Client\ClientManager $client_manager */
  $client_manager = \Drupal::service('acquia_contenthub.client_manager');
  $client = $client_manager->getClient();

  $options = array();

  // Obtaining the limit.
  $limit = drush_get_option("limit");
  if (isset($limit)) {
    $limit = (int) $limit;
    if ($limit < 1 || $limit > 1000) {
      return drush_set_error(dt("The limit has to be an integer from 1 to 1000."));
    }
    else {
      $options['limit'] = $limit;
    }
  }

  // Obtaining the offset.
  $start = drush_get_option("start");
  if (isset($start)) {
    if (!is_numeric($start)) {
      return drush_set_error(dt("The start offset has to be numeric starting from 0."));
    }
    else {
      $options['start'] = $start;
    }
  }

  // Filtering by origin.
  $origin = drush_get_option("origin");
  if (isset($origin)) {
    if (Uuid::isValid($origin)) {
      $options['origin'] = $origin;
    }
    else {
      return drush_set_error(dt("The origin has to be a valid UUID."));
    }
  }

  // Filtering by language.
  $language = drush_get_option("language");
  if (isset($language)) {
    if (strlen($language) == 2) {
      $options['language'] = $language;
    }
    else {
      return drush_set_error(dt("The language has to be provided as a 2-letter language code."));
    }
  }

  // Filtering by fields.
  $fields = drush_get_option("attributes");
  if (isset($fields)) {
    $options['fields'] = $fields;
  }

  // Filtering by type.
  $type = drush_get_option("type");
  if (isset($type)) {
    $options['type'] = $type;
  }

  // Building the filters.
  $filters = drush_get_option("filters");
  if (isset($filters)) {
    $filters = isset($filters) ? explode(",", $filters) : FALSE;
    foreach ($filters as $key => $filter) {
      list($name, $value) = explode("=", $filter);
      $filters[$name] = $value;
      unset($filters[$key]);
    }
    $options['filters'] = $filters;
  }
  $list = $client->listEntities($options);
  return $list;
}

/**
 * Deletes single entities from the Content Hub.
 *
 * @param string $uuid
 *   Uuid of entity to delete.
 *
 * @return bool
 *   TRUE if entity is deleted, FALSE otherwise.
 */
function drush_acquia_contenthub_content_hub_delete($uuid) {
  /** @var \Drupal\acquia_contenthub\Client\ClientManager $client_manager */
  $client_manager = \Drupal::service('acquia_contenthub.client_manager');

  if (!Uuid::isValid($uuid)) {
    return drush_set_error(dt("Argument provided is not a UUID."));
  }
  else {
    if (drush_confirm(dt('Are you sure you want to delete the entity with uuid = @uuid from the Content Hub? There is no way back from this action!', array(
      '@uuid' => $uuid,
    )))) {
      $client = $client_manager->getClient();
      if ($client->deleteEntity($uuid)) {
        drush_print(dt("Entity with UUID = @uuid has been successfully deleted from the Content Hub.", array(
          '@uuid' => $uuid,
        )));
      }
      else {
        return drush_set_error(dt("Entity with UUID = @uuid cannot be deleted.", array(
          '@uuid' => $uuid,
        )));
      }
    }
    else {
      drush_user_abort();
    }
  }
}

/**
 * Purges entities from the Content Hub.
 */
function drush_acquia_contenthub_content_hub_purge() {
  /** @var \Drupal\acquia_contenthub\Client\ClientManager $client_manager */
  $client_manager = \Drupal::service('acquia_contenthub.client_manager');
  $client = $client_manager->getClient();

  if (drush_confirm('Are you sure you want to PURGE ALL your entities from the Content Hub? There is no way back from this action!!')) {
    try {
      $list = $client->purge();
      drush_print("All your entities in the Content Hub have been successfully purged. \n");
      return $list;
    }
    catch (ClientException $e) {
      $response = $e->getResponse()->json();
    }
    catch (ServerErrorResponseException $e) {
      $response = $e->getResponse()->json();
    }
    catch (BadResponseException $e) {
      $response = $e->getResponse()->json();
    }
    if (isset($response['error']) && $error = $response['error']) {
      return drush_set_error(dt("Error purging entities from the Content Hub [Error Code = @error_code: @error_message]", array(
        '@error_code' => $error['code'],
        '@error_message' => $error['message'],
      )));
    }
  }
  else {
    drush_user_abort();
  }
}

/**
 * Regenerates a Shared Secret from the Content Hub.
 */
function drush_acquia_contenthub_content_hub_regenerate_secret() {
  /** @var \Drupal\acquia_contenthub\Client\ClientManager $client_manager */
  $client_manager = \Drupal::service('acquia_contenthub.client_manager');
  $client = $client_manager->getClient();

  $warning_message = "Are you sure you want to REGENERATE your shared-secret in the Content Hub?\n" .
    "*************************************************************************************\n" .
    "PROCEED WITH CAUTION. THIS COULD POTENTIALLY LEAD TO HAVING SOME SITES OUT OF SYNC.\n" .
    "Make sure you have ALL your sites correctly configured to receive webhooks before attempting to do this.\n" .
    "For more information, check https://docs.acquia.com/content-hub/known-issues.\n" .
    "*************************************************************************************\n";
  if (drush_confirm($warning_message)) {
    if ($client->regenerateSharedSecret()) {
      drush_print("Your Shared Secret has been regenerated. All your clients who have registered to received webhooks are being notified of this change.\n");
    }
    else {
      return drush_set_error(dt("Error trying to regenerate the shared-secret in your subscription. Try again later."));
    }
  }
  else {
    drush_user_abort();
  }

}
