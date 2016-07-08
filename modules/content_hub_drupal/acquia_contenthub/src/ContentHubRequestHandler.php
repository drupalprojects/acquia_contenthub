<?php
/**
 * @file
 * Generic class for handling requests and its exceptions to Acquia Content Hub.
 */

namespace Drupal\acquia_contenthub;

use \Exception;
use \GuzzleHttp\Exception\ConnectException as ConnectException;
use \GuzzleHttp\Exception\RequestException as RequestException;
use \GuzzleHttp\Exception\ServerException as ServerException;
use \GuzzleHttp\Exception\ClientException as ClientException;
use \GuzzleHttp\Exception\BadResponseException as BadResponseException;
use Acquia\ContentHubClient as ContentHubClient;

class ContentHubRequestHandler {
  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHub
   */
  protected $client;

  /**
   * Public constructor.
   *
   * @param string|void $origin
   *   Defines the site origin's UUID.
   */
  public function __construct($origin = NULL) {
    $client_manager = \Drupal::service('acquia_contenthub.client_manager');
    $this->client = $client_manager->getClient();
  }

  /**
   * Returns the Connection to Acquia Content Hub.
   *
   * @return \Acquia\ContentHubClient\ContentHub
   *   The Client Connection to Acquia Content Hub.
   */
  public function getConnection() {
    return $this->client;
  }

  /**
   * Makes an API Call Request to Acquia Content Hub, with exception handling.
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
  public function createRequest($request, $args = array(), $exception_messages = array()) {
    try {
      // Check that we have a valid connection.
      if ($this->client === FALSE) {
        $error = t('This client is NOT registered to Content Hub. Please register first');
        throw new Exception($error);
      }

      // Process each individual request.
      switch ($request) {
        // Case for all API calls with no arguments that do NOT require
        // authentication.
        case 'addWebhook':
        case 'deleteWebhook':
          // This request only requires one argument (webhook_uuid), but we
          // are using the second one to pass the webhook_url.
          if (!isset($args[0])) {
            $error = t('Request %request requires %num argument.', array(
              '%request' => $request,
              '%num' => 1,
            ));
            throw new Exception($error);
          }
          return $this->client->$request($args[0]);
      }
    }
    // Catch Exceptions.
    catch (ServerException $ex) {
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages);
    }
    catch (ConnectException $ex) {
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages);
    }
    catch (ClientException $ex) {
      $response = $ex->getResponse()->json();
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages, $response);
    }
    catch (RequestException $ex) {
      $msg = $this->getExceptionMessage($request, $args, ex, $exception_messages);
    }
    catch (BadResponseException $ex) {
      $response = $ex->getResponse()->json();
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages, $response);
    }
    catch (ServerErrorResponseException $ex) {
      $response = $ex->getResponse()->json();
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages, $response);
    }
    catch (Exception $ex) {
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages);
    }

    // Now show and log the error message.
    if (isset($msg)) {
      if ($msg !== FALSE) {
        $this->loggerFactory->get('acquia_contenthub')->error($msg);
        throw $e;
      }
      else {
        // If the message is FALSE, then there is no error message, which
        // means the request was expecting an exception to be successful.
        return TRUE;
      }
    }

    return FALSE;

  }
}
