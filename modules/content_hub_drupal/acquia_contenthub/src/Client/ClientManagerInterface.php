<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Client\ClientManagerInterface.
 */

namespace Drupal\acquia_contenthub\Client;

use Symfony\Component\HttpFoundation\Request as Request;

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
  public function getConnection($config);

  /**
   * Resets the connection to allow to pass connection variables.
   *
   * This should be used when we need to pass connection variables instead
   * of using the ones stored in Drupal variables.
   *
   * @param array $variables
   *   The array of variables to pass through.
   * @param array $config
   *   The Configuration options.
   */
  public function resetConnection(array $variables, $config = array());

  /**
   * Checks whether the current client has a valid connection to Content Hub.
   *
   * @param bool $full_check
   *   Use TRUE to make a full validation (check that the drupal variables
   *   provide a valid connection to Content Hub). By default it makes a 'quick'
   *   validation just by making sure that the variables are set.
   *
   * @return bool
   *   TRUE if client is connected, FALSE otherwise.
   */
  public static function isConnected($full_check = FALSE);

  /**
   * Makes an API Call Request to Content Hub, with exception handling.
   *
   * It handles generic exceptions and allows for text overrides.
   *
   * @param string $request
   *   The name of the request.
   * @param array $args
   *   The arguments to pass to the request.
   * @param array $exception_messages
   *   The exception messages to overwrite.
   *
   * @return bool|mixed
   *   The return value of the request if succeeds, FALSE otherwise.
   */
  public function createRequest($request, $args = array(), $exception_messages = array());

  /**
   * Extracts HMAC signature from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request to evaluate signature.
   * @param string $secret_key
   *   The secret key used to generate the signature.
   *
   * @return string
   *   The HMAC signature for this request.
   */
  public function getRequestSignature(Request $request, $secret_key = '');

}
