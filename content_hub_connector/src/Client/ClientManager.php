<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Client\ClientManager.
 */

namespace Drupal\content_hub_connector\Client;

use Acquia\ContentHubClient\ContentHub;
use Drupal\content_hub_connector\Cipher;
use Drupal\content_hub_connector\ClientManagerInterface;

/**
 * Provides a service for managing pending server tasks.
 */
class ClientManager implements ClientManagerInterface {

  /**
   * Function returns content hub client.
   *
   * @param array $config
   *   Configuration array.
   *
   * @return \Acquia\ContentHubClient\ContentHub
   *   Returns the Content Hub Client
   */
  public function getClient($config = array()) {
    // @todo Make sure this injects using proper service injection methods.
    $config_drupal = \Drupal::config('content_hub_connector.admin_settings');

    // Override configuration.
    $config = array_merge(array(
      'base_url' => $config_drupal->get('content_hub_connector_hostname'),
    ), $config);

    // Get API information
    $api = $config_drupal->get('content_hub_connector_api_key');
    $origin = $config_drupal->get('content_hub_connector_origin');
    $encryption = (bool) $config_drupal->get('content_hub_connector_encryption_key_file');

    if ($encryption) {
      $secret = $config_drupal->get('content_hub_connector_secret_key');
      $secret = $this->cipher()->decrypt($secret);
    }
    else {
      $secret = $config_drupal->get('content_hub_connector_secret_key');
    }

    $client = new ContentHub($api, $secret, $origin, $config);
    return $client;
  }

  /**
   * Returns a cipher class for encrypting and decrypting text.
   *
   * @Todo Make this work!
   *
   * @return \Drupal\content_hub_connector\CipherInterface
   *   The Cipher object to use for encrypting the data.
   */
  public function cipher() {
    // @todo Make sure this injects using proper service injection methods.
    $config = \Drupal::config('content_hub_connector.admin_settings');
    $filepath = $config->get('content_hub_connector_encryption_key_file');
    $cipher = new Cipher($filepath);
    return $cipher;
  }


}
