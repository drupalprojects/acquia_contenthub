<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Client\ClientManagerInterface.
 */

namespace Drupal\acquia_contenthub\Client;

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
  public function getClient($config);

}
