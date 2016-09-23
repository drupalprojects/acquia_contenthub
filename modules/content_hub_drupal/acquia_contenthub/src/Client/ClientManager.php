<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Client\ClientManager.
 */

namespace Drupal\acquia_contenthub\Client;

use Acquia\ContentHubClient\ContentHub;
use \Exception;
use \GuzzleHttp\Exception\ConnectException as ConnectException;
use \GuzzleHttp\Exception\RequestException as RequestException;
use \GuzzleHttp\Exception\ServerException as ServerException;
use \GuzzleHttp\Exception\ClientException as ClientException;
use \GuzzleHttp\Exception\BadResponseException as BadResponseException;
use Drupal\acquia_contenthub\Cipher;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\Request as Request;
use Drupal\Component\Uuid\Uuid;

/**
 * Provides a service for managing pending server tasks.
 */
class ClientManager implements ClientManagerInterface {

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The Acquia Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHub
   */
  protected $client;

  /**
   * The Drupal Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * ClientManager constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory) {
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;

    // Get the content hub config settings.
    $this->config = $this->configFactory->get('acquia_contenthub.admin_settings');

    // Initializing Client.
    $this->setConnection();
  }

  /**
   * Function returns content hub client.
   *
   * @param array $config
   *   Configuration array.
   *
   * @return \Acquia\ContentHubClient\ContentHub
   *   Returns the Content Hub Client
   *
   * @throws \Drupal\acquia_contenthub\ContentHubException
   *   Throws exception when cannot connect to Content Hub.
   */
  protected function setConnection($config = []) {
    $this->client = &drupal_static(__FUNCTION__);
    if (NULL === $this->client) {

      // Find out the module version in use.
      $module_info = system_get_info('module', 'acquia_contenthub');
      $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
      $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';
      $client_user_agent = 'AcquiaContentHub/' . $drupal_version . '-' . $module_version;
      $hostname = $this->config->get('hostname');

      // Override configuration.
      $config = array_merge([
        'base_url' => $hostname,
        'client-user-agent' => $client_user_agent,
      ], $config);

      // Get API information.
      $api = $this->config->get('api_key');
      $secret = $this->config->get('secret_key');
      $client_name = $this->config->get('client_name');
      $origin = $this->config->get('origin');
      $encryption = (bool) $this->config->get('encryption_key_file');

      if ($encryption) {
        $secret = $this->cipher()->decrypt($secret);
      }

      // If any of these variables is empty, then we do NOT have a valid
      // connection.
      if (!Uuid::isValid($origin) || empty($client_name) || empty($hostname) || empty($api) || empty($secret)) {
        return FALSE;
      }

      $this->client = new ContentHub($api, $secret, $origin, $config);
    }
    return $this;
  }

  /**
   * Function returns the Acquia Content Hub client.
   */
  public function getConnection($config = []) {
    return $this->client;
  }

  /**
   * Returns a cipher class for encrypting and decrypting text.
   *
   * @Todo Make this work!
   *
   * @return \Drupal\acquia_contenthub\CipherInterface
   *   The Cipher object to use for encrypting the data.
   */
  public function cipher() {
    // @todo Make sure this injects using proper service injection methods.
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $filepath = $config->get('encryption_key_file');
    $cipher = new Cipher($filepath);
    return $cipher;
  }

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
  public function resetConnection(array $variables, $config = []) {
    $hostname = isset($variables['hostname']) ? $variables['hostname'] : '';;
    $api = isset($variables['api']) ? $variables['api'] : '';

    // We assume that the secret passed to this function is always
    // unencrypted.
    $secret = isset($variables['secret']) ? $variables['secret'] : '';;
    $origin = isset($variables['origin']) ? $variables['origin'] : '';

    $module_info = system_get_info('module', 'acquia_contenthub');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';
    $client_user_agent = 'AcquiaContentHub/' . $drupal_version . '-' . $module_version;

    // Override configuration.
    $config = array_merge([
      'base_url' => $hostname,
      'client-user-agent' => $client_user_agent,
    ], $config);

    $this->client = new ContentHub($api, $secret, $origin, $config);
  }

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
  public static function isConnected($full_check = FALSE) {
    $connection = new static();

    // Always do a quick check.
    if ($connection->getConnection() === FALSE) {
      return FALSE;
    }

    // If a full check is requested, test a connection to Content Hub.
    if ($full_check) {
      // Make a request to Content Hub using current settings to make sure that
      // they do provide a valid connection.
      if ($connection->createRequest('getSettings') === FALSE) {
        return FALSE;
      }
    }

    // If we reached here then client has a valid connection.
    return TRUE;
  }

  /**
   * Checks whether the client name given is available in this Subscription.
   *
   * @param string $client_name
   *   The client name to check availability.
   *
   * @return bool
   *   TRUE if available, FALSE otherwise.
   */
  public function isClientNameAvailable($client_name) {
    if ($site = $this->createRequest('getClientByName', array($client_name))) {
      if (isset($site['uuid']) && Uuid::isValid($site['uuid'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Extracts HMAC signature from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request to evaluate signature.
   * @param string $secret_key
   *   The Secret Key.
   *
   * @return string
   *   A base64 encoded string signature.
   */
  public function getRequestSignature(Request $request, $secret_key = '') {
    // Extract signature information from the request.
    $headers = array_map('current', $request->headers->all());
    $http_verb = $request->getMethod();

    // Adding the Request Query string.
    if (NULL !== $qs = $request->getQueryString()) {
      $qs = '?' . $qs;
    }
    $path = $request->getBasePath() . $request->getPathInfo() . $qs;
    $body = $request->getContent();

    // If the headers are not given, then the request is probably not coming
    // from the Content Hub. Replace them for empty string to fail validation.
    $content_type = isset($headers['content-type']) ? $headers['content-type'] : '';
    $date = isset($headers['date']) ? $headers['date'] : '';
    $message_array = array(
      $http_verb,
      md5($body),
      $content_type,
      $date,
      '',
      $path,
    );
    $message = implode("\n", $message_array);
    $s = hash_hmac('sha256', $message, $secret_key, TRUE);
    $signature = base64_encode($s);
    return $signature;
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
      if ($this->getConnection() === FALSE) {
        $error = t('This client is NOT registered to Content Hub. Please register first');
        throw new Exception($error);
      }

      // Process each individual request.
      switch ($request) {
        // Case for all API calls with no arguments that do NOT require
        // authentication.
        case 'ping':
        case 'definition':
          return $this->getConnection()->$request();

        // Case for all API calls with no argument that require authentication.
        case 'getSettings':
        case 'purge':
        case 'regenerateSharedSecret':
          return $this->client->$request();

        // Case for all API calls with 1 argument.
        case 'register':
        case 'getClientByName':
        case 'createEntity':
        case 'createEntities':
        case 'readEntity':
        case 'updateEntities':
        case 'deleteEntity':
        case 'listEntities':
        case 'addWebhook':
        case 'deleteWebhook':
          // This request only requires one argument (webhook_uuid), but we
          // are using the second one to pass the webhook_url.
        case 'searchEntity':
          if (!isset($args[0])) {
            $error = t('Request %request requires %num argument.', array(
              '%request' => $request,
              '%num' => 1,
            ));
            throw new Exception($error);
          }
          return $this->client->$request($args[0]);

        // Case for all API calls with 2 arguments.
        case 'updateEntity':
          if (!isset($args[0]) || !isset($args[1])) {
            $error = t('Request %request requires %num arguments.', array(
              '%request' => $request,
              '%num' => 2,
            ));
            throw new Exception($error);
          }
          return $this->client->$request($args[0], $args[1]);
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
      $response = json_decode($ex->getResponse()->getBody(), TRUE);
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages, $response);
    }
    catch (RequestException $ex) {
      $msg = $this->getExceptionMessage($request, $args, ex, $exception_messages);
    }
    catch (BadResponseException $ex) {
      $response = json_decode($ex->getResponse()->getBody(), TRUE);
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages, $response);
    }
    catch (ServerErrorResponseException $ex) {
      $response = json_decode($ex->getResponse()->getBody(), TRUE);
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages, $response);
    }
    catch (Exception $ex) {
      $msg = $this->getExceptionMessage($request, $args, $ex, $exception_messages);
    }

    // Now show and log the error message.
    if (isset($msg)) {
      if ($msg !== FALSE) {
        $this->loggerFactory->get('acquia_contenthub')->error($msg);
        // Throw $ex;.
      }
      else {
        // If the message is FALSE, then there is no error message, which
        // means the request was expecting an exception to be successful.
        return TRUE;
      }
    }

    return FALSE;

  }

  /**
   * Obtains the appropriate exception message for the selected exception.
   *
   * This is the place to set up exception messages per request.
   *
   * @param string $request
   *   The Request to Plexus, as defined in the content-hub-php library.
   * @param array $args
   *   The Request arguments.
   * @param object $ex
   *   The Exception object.
   * @param array $exception_messages
   *   The array of messages to overwrite, keyed by Exception name.
   * @param object|void $response
   *   The response to the request.
   *
   * @return null|string
   *   The text to write in the messages.
   */
  protected function getExceptionMessage($request, $args, $ex, $exception_messages = array(), $response = NULL) {
    // Obtain the class name.
    $exception = implode('', array_slice(explode('\\', get_class($ex)), -1));

    switch ($exception) {
      case 'ServerException':
        if (isset($exception_messages['ServerException'])) {
          $msg = $exception_messages['ServerException'];
        }
        else {
          $msg = new FormattableMarkup('Could not reach the Content Hub. Please verify your hostname and Credentials. [Error message: @msg]', ['@msg' => $ex->getMessage()]);
        }
        break;

      case 'ConnectException':
        if (isset($exception_messages['ConnectException'])) {
          $msg = $exception_messages['ConnectException'];
        }
        else {
          $msg = new FormattableMarkup('Could not reach the Content Hub. Please verify your hostname URL. [Error message: @msg]', ['@msg' => $ex->getMessage()]);
        }
        break;

      case 'ClientException':
      case 'BadResponseException':
      case 'ServerErrorResponseException':
        if (isset($exception_messages[$exception])) {
          $msg = $exception_messages[$exception];
        }
        else {
          if (isset($response) && isset($response['error'])) {
            // In the case of ClientException there are custom message that need
            // to be set depending on the request.
            $error = $response['error'];
            switch ($request) {
              // Customize the error message per request here.
              case 'register':
                $client_name = $args[0];
                $msg = new FormattableMarkup('Error registering client with name="@name" (Error Code = @error_code: @error_message)',
                  array(
                    '@error_code' => $error['code'],
                    '@name' => $client_name,
                    '@error_message' => $error['message'],
                  ));
                break;

              case 'getClientByName':
                // If status code = 404, then this site name is available.
                $code = $ex->getResponse()->getStatusCode();
                if ($code == 404) {
                  // All good! client name is available!
                  return FALSE;
                }
                else {
                  $msg = new FormattableMarkup('Error trying to connect to the Content Hub" (Error Code = @error_code: @error_message)', array(
                    '@error_code' => $error['code'],
                    '@error_message' => $error['message'],
                  ));
                }
                break;

              case 'addWebhook':
                $webhook_url = $args[0];
                $msg = new FormattableMarkup('There was a problem trying to register Webhook URL = %URL. Please try again. (Error Code = @error_code: @error_message)', array(
                  '%URL' => $webhook_url,
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'deleteWebhook':
                // This function only requires one argument (webhook_uuid), but
                // we are using the second one to pass the webhook_url.
                $webhook_url = isset($args[1]) ? $args[1] : $args[0];
                $msg = new FormattableMarkup('There was a problem trying to <b>unregister</b> Webhook URL = %URL. Please try again. (Error Code = @error_code: @error_message)', array(
                  '%URL' => $webhook_url,
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'purge':
                $msg = new FormattableMarkup('Error purging entities from the Content Hub [Error Code = @error_code: @error_message]', array(
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'readEntity':
                $uuid = $args[0];
                $msg = new FormattableMarkup('Error reading entity with UUID="@uuid" from Content Hub (Error Code = @error_code: @error_message)', array(
                  '@error_code' => $error['code'],
                  '@uuid' => $uuid,
                  '@error_message' => $error['message'],
                ));
                break;

              case 'createEntity':
                $msg = new FormattableMarkup('Error trying to create an entity in Content Hub (Error Code = @error_code: @error_message)', array(
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'createEntities':
                $msg = new FormattableMarkup('Error trying to create entities in Content Hub (Error Code = @error_code: @error_message)', array(
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'updateEntity':
                $uuid = $args[1];
                $msg = new FormattableMarkup('Error trying to update an entity with UUID="@uuid" in Content Hub (Error Code = @error_code: @error_message)', array(
                  '@uuid' => $uuid,
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'updateEntities':
                $msg = new FormattableMarkup('Error trying to update some entities in Content Hub (Error Code = @error_code: @error_message)', array(
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'deleteEntity':
                $uuid = $args[0];
                $msg = new FormattableMarkup('Error trying to delete entity with UUID="@uuid" in Content Hub (Error Code = @error_code: @error_message)', array(
                  '@uuid' => $uuid,
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              case 'searchEntity':
                $msg = new FormattableMarkup('Error trying to make a search query to Content Hub. Are your credentials inserted correctly? (Error Code = @error_code: @error_message)', array(
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
                break;

              default:
                $msg = new FormattableMarkup('Error trying to connect to the Content Hub" (Error Code = @error_code: @error_message)', array(
                  '@error_code' => $error['code'],
                  '@error_message' => $error['message'],
                ));
            }

          }
          else {
            $msg = new FormattableMarkup('Error trying to connect to the Content Hub (Error Message = @error_message)', array(
              '@error_message' => $ex->getMessage(),
            ));
          }
        }
        break;

      case 'RequestException':
        if (isset($exception_messages['RequestException'])) {
          $msg = $exception_messages['RequestException'];
        }
        else {
          switch ($request) {
            // Customize the error message per request here.
            case 'register':
              $client_name = $args[0];
              $msg = new FormattableMarkup('Could not get authorization from Content Hub to register client @name. Are your credentials inserted correctly? (Error message = @error_message)', array(
                '@name' => $client_name,
                '@error_message' => $ex->getMessage(),
              ));
              break;

            case 'createEntity':
              $msg = new FormattableMarkup('Error trying to create an entity in Content Hub (Error Message: @error_message)', array(
                '@error_message' => $ex->getMessage(),
              ));
              break;

            case 'createEntities':
              $msg = new FormattableMarkup('Error trying to create entities in Content Hub (Error Message = @error_message)', array(
                '@error_message' => $ex->getMessage(),
              ));
              break;

            case 'updateEntity':
              $uuid = $args[1];
              $msg = new FormattableMarkup('Error trying to update entity with UUID="@uuid" in Content Hub (Error Message = @error_message)', array(
                '@uuid' => $uuid,
                '@error_message' => $ex->getMessage(),
              ));
              break;

            case 'updateEntities':
              $msg = new FormattableMarkup('Error trying to update some entities in Content Hub (Error Message = @error_message)', array(
                '@error_message' => $ex->getMessage(),
              ));
              break;

            case 'deleteEntity':
              $uuid = $args[0];
              $msg = new FormattableMarkup('Error trying to delete entity with UUID="@uuid" in Content Hub (Error Message = @error_message)', array(
                '@uuid' => $uuid,
                '@error_message' => $ex->getMessage(),
              ));
              break;

            case 'searchEntity':
              $msg = new FormattableMarkup('Error trying to make a search query to Content Hub. Are your credentials inserted correctly? (Error Message = @error_message)', array(
                '@error_message' => $ex->getMessage(),
              ));
              break;

            default:
              $msg = new FormattableMarkup('Error trying to connect to the Content Hub. Are your credentials inserted correctly? (Error Message = @error_message)', array(
                '@error_message' => $ex->getMessage(),
              ));
          }
        }
        break;

      case 'Exception':
        if (isset($exception_messages['Exception'])) {
          $msg = $exception_messages['Exception'];
        }
        else {
          $msg = new FormattableMarkup('Error trying to connect to the Content Hub (Error Message = @error_message)', array(
            '@error_message' => $ex->getMessage(),
          ));
        }
        break;

    }

    return $msg;
  }

  /**
   * Executes an elasticsearch query.
   *
   * @param array $query
   *   Search query.
   *
   * @return mixed
   *   Returns elasticSearch query response hits.
   */
  public function executeQuery(array $query) {
    if ($query_response = $this->createRequest('searchEntity', array($query))) {
      return $query_response['hits'];
    }
    return FALSE;
  }

  /**
   * Helper function to build elasticsearch query for terms using AND operator.
   *
   * @param string $search_term
   *   Search term.
   *
   * @return mixed
   *   Returns query result.
   */
  public function getFilters($search_term) {
    if ($search_term) {
      $items = array_map('trim', explode(',', $search_term));
      $last_item = array_pop($items);

      $query['query'] = array(
        'query_string' => array(
          'query' => $last_item,
          'default_operator' => 'and',
        ),
      );
      $query['_source'] = TRUE;
      $query['highlight'] = array(
        'fields' => array(
          '*' => new \stdClass(),
        ),
      );
      $result = $this->executeQuery($query);
      return $result ? $result['hits'] : FALSE;
    }
  }

  /**
   * Builds elasticsearch query to get filters name for auto suggestions.
   *
   * @param string $search_term
   *   Given search term.
   *
   * @return mixed
   *   Returns query result.
   */
  public function getReferenceFilters($search_term) {
    if ($search_term) {

      $match[] = array('match' => array('_all' => $search_term));

      $query['query']['filtered']['query']['bool']['must'] = $match;
      $query['query']['filtered']['query']['bool']['must_not']['term']['data.type'] = 'taxonomy_term';
      $query['_source'] = TRUE;
      $query['highlight'] = array(
        'fields' => array(
          '*' => new \stdClass(),
        ),
      );
      $result = $this->executeQuery($query);

      return $result ? $result['hits'] : FALSE;
    }
  }

  /**
   * Builds Search query for given search terms.
   *
   * @param array $typed_terms
   *   Entered terms array.
   * @param string $webhook_uuid
   *   Webhook Uuid.
   * @param string $type
   *   Module Type to identify, which query needs to be executed.
   * @param array $options
   *   An associative array of options for this query, including:
   *   - count: number of items per page.
   *   - start: defines the offset to start from.
   *
   * @return int|mixed
   *   Returns query result.
   */
  public function getSearchResponse(array $typed_terms, $webhook_uuid = '', $type = '', $options = array()) {
    $origins = '';
    foreach ($typed_terms as $typed_term) {
      if ($typed_term['filter'] !== '_all') {
        if ($typed_term['filter'] == 'modified') {
          $dates = explode('to', $typed_term['value']);
          $from = isset($dates[0]) ? trim($dates[0]) : '';
          $to = isset($dates[1]) ? trim($dates[1]) : '';
          if (!empty($from)) {
            $query['filter']['range']['data.modified']['gte'] = $from;
          }
          if (!empty($to)) {
            $query['filter']['range']['data.modified']['lte'] = $to;
          }
          $query['filter']['range']['data.modified']['time_zone'] = '+1:00';
        }
        elseif ($typed_term['filter'] == 'origin') {
          $origins .= $typed_term['value'] . ',';
        }
        // Retrieve results for any language.
        else {
          $match[] = array(
            'multi_match' => array(
              'query' => $typed_term['value'],
              'fields' => array('data.attributes.' . $typed_term['filter'] . '.value.*'),
            ),
          );
        }
      }
      else {
        $array_ref = $this->getReferenceDocs($typed_term['value']);
        if (is_array($array_ref)) {
          $tags = implode(', ', $array_ref);
        }
        if ($tags) {
          $match[] = array('match' => array($typed_term['filter'] => "*" . $typed_term['value'] . "*" . ',' . $tags));
        }
        else {
          $match[] = array(
            'match' => array(
              $typed_term['filter'] => array(
                "query" => "*" . $typed_term['value'] . "*" ,
                "operator" => "and",
              ),
            ),
          );
        }
      }
    }

    if (isset($match)) {
      $query['query']['filtered']['query']['bool']['must'] = $match;
    }
    if (!empty($origins)) {
      $match[] = array('match' => array('data.origin' => $origins));
      $query['query']['filtered']['query']['bool']['must'] = $match;
    }
    $query['query']['filtered']['filter']['term']['data.type'] = 'node';
    $query['size'] = !empty($options['count']) ? $options['count'] : 10;
    $query['from'] = !empty($options['start']) ? $options['start'] : 0;
    $query['highlight'] = array(
      'fields' => array(
        '*' => new \stdClass(),
      ),
    );
    if (!empty($options['sort']) && strtolower($options['sort']) !== 'relevance') {
      $query['sort']['data.modified'] = strtolower($options['sort']);
    }
    switch ($type) {
      case 'content_hub':
        if (isset($webhook_uuid)) {
          $query['query']['filtered']['filter']['term']['_id'] = $webhook_uuid;
        }
    }
    return $this->executeQuery($query);
  }

  /**
   * Helper function to get Uuids of referenced documents.
   *
   * @param string $str_val
   *   String value.
   *
   * @return array
   *   Reference terms Uuid array.
   */
  public function getReferenceDocs($str_val) {
    $ref_uuid = array();
    $ref_result = $this->getFilters($str_val);
    if ($ref_result) {
      foreach ($ref_result as $rows) {
        $ref_uuid[] = $rows['_id'];
      }
    }
    return $ref_uuid;
  }

  /**
   * Helper function to parse the given string with filters.
   *
   * @param string $str_val
   *   The string that needs to be parsed for querying elasticsearch.
   * @param string $webhook_uuid
   *   The Webhook Uuid.
   * @param string $type
   *   Module Type to identify, which query needs to be executed.
   * @param array $options
   *   An associative array of options for this query, including:
   *   - count: number of items per page.
   *   - start: defines the offset to start from.
   *
   * @return int|mixed
   *   Returns query response.
   */
  public function parseSearchString($str_val, $webhook_uuid = '', $type = '', $options = array()) {
    if ($str_val) {
      $search_terms = $this->drupal_explode_tags($str_val);
      foreach ($search_terms as $search_term) {
        $check_for_filter = preg_match('/[:]/', $search_term);
        if ($check_for_filter) {
          list($filter, $value) = explode(':', $search_term);
          $typed_terms[] = array(
            'filter' => $filter,
            'value' => $value,
          );
        }
        else {
          $typed_terms[] = array(
            'filter' => '_all',
            'value' => $search_term,
          );
        }
      }

      return $this->getSearchResponse($typed_terms, $webhook_uuid, $type, $options);
    }
  }

  /**
   * Builds tags list and executes query for a given webhook uuid.
   *
   * @param string $tags
   *   List of tags separated by comma.
   * @param string $webhook_uuid
   *   Webhook Uuid.
   * @param string $type
   *   Module Type to identify, which query needs to be executed.
   *
   * @return bool
   *   Returns query result.
   */
  public function buildTagsQuery($tags, $webhook_uuid, $type = '') {
    $result = $this->parseSearchString($tags, $webhook_uuid, $type);
    if ($result & !empty($result['total'])) {
      return $result['total'];
    }
    return 0;
  }

  /**
   * Builds elasticsearch query to retrieve data in reverse chronological order.
   *
   * @param array $options
   *   An associative array of options for this query, including:
   *   - count: number of items per page.
   *   - start: defines the offset to start from.
   *
   * @return mixed
   *   Returns query result.
   */
  public function buildChronologicalQuery($options = array()) {

    $query['query']['match_all'] = new \stdClass();
    $query['sort']['data.modified'] = 'desc';
    if (!empty($options['sort']) && strtolower($options['sort']) !== 'relevance') {
      $query['sort']['data.modified'] = strtolower($options['sort']);
    }
    $query['filter']['term']['data.type'] = 'node';
    $query['size'] = !empty($options['count']) ? $options['count'] : 10;
    $query['from'] = !empty($options['start']) ? $options['start'] : 0;
    $result = $this->executeQuery($query);

    return $result;
  }

  // @TODO: This should go away
  protected function drupal_explode_tags($tags) {
    // This regexp allows the following types of user input:
    // this, "somecompany, llc", "and ""this"" w,o.rks", foo bar
    $regexp = '%(?:^|,\ *)("(?>[^"]*)(?>""[^"]* )*"|(?: [^",]*))%x';
    preg_match_all($regexp, $tags, $matches);
    $typed_tags = array_unique($matches[1]);

    $tags = array();
    foreach ($typed_tags as $tag) {
      // If a user has escaped a term (to demonstrate that it is a group,
      // or includes a comma or quote character), we remove the escape
      // formatting so to save the term into the database as the user intends.
      $tag = trim(str_replace('""', '"', preg_replace('/^"(.*)"$/', '\1', $tag)));
      if ($tag != "") {
        $tags[] = $tag;
      }
    }

    return $tags;
  }
}
