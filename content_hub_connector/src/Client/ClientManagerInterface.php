<?php
/**
 * @file
 * Contains \Drupal\content_hub_connector\Client\ClientManagerInterface.
 */

namespace Drupal\content_hub_connector\Client;

/**
 * Interface for CipherInterface.
 */
interface ClientManagerInterface {

  /**
   * Gets a Content Hub Client Object.
   *
   * @param array $config
   *   Configuration array.
   *
   * @return \Acquia\ContentHubClient\ContentHub
   *   Returns the Content Hub Client
   */
  public function getClient();

}
