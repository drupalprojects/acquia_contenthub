<?php
/**
 * @file
 * Processes Webhooks coming from Content Hub.
 */

namespace Drupal\acquia_contenthub\Controller;

use Acquia\ContentHubClient\ResponseSigner;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response as Response;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactory;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;
use Symfony\Component\HttpFoundation\Request as Request;

/**
 * Controller for Content Hub Imported Entities.
 */
class ContentHubWebhookController extends ControllerBase {

  /**
   * Logger.
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
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * Content Hub Subscription.
   *
   * @var \Drupal\acquia_contenthub\ContentHubSubscription
   */
  protected $contentHubSubscription;

  /**
   * The Drupal Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * WebhooksSettingsForm constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *    The config factory.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubSubscription $contenthub_subscription
   *    The Content Hub Subscription.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory, ClientManagerInterface $client_manager, ContentHubSubscription $contenthub_subscription) {
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->clientManager = $client_manager;
    $this->contentHubSubscription = $contenthub_subscription;
    // Get the content hub config settings.
    $this->config = $this->configFactory->get('acquia_contenthub.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_subscription')
    );
  }

  /**
   * Process a webhook.
   *
   * @return \Acquia\ContentHubClient\ResponseSigner|\Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function receiveWebhook() {
    // Obtain the headers.
    $request = Request::createFromGlobals();
    $webhook = $request->getContent();

    if ($this->validateWebhookSignature($request)) {
      // Notify about the arrival of the webhook request.
      $args = array(
        '@whook' => print_r($webhook, TRUE),
      );
      $message = new FormattableMarkup('Webhook landing: @whook', $args);
      $this->loggerFactory->get('acquia_contenthub')->debug($message);

      if ($webhook = Json::decode($webhook)) {
        // Verification process successful!
        // Now we can process the webhook.
        if (isset($webhook['status'])) {
          switch ($webhook['status']) {
            case 'successful':
              return $this->processWebhook($webhook);

            case 'pending':
              return $this->registerWebhook($webhook);

            case 'shared_secret_regenerated':
              return $this->updateSharedSecret($webhook);

            default:
              // If any other webhook we are not processing then just display
              // the response.
              return new Response('');

          }
        }
      }

    }
    else {
      $ip_address = \Drupal::request()->getClientIp();
      $message = new FormattableMarkup('Webhook [from IP = @IP] rejected (Signatures do not match): @whook', array(
        '@IP' => $ip_address,
        '@whook' => print_r($webhook, TRUE),
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
      return new Response('');
    }

  }

  /**
   * Validates a webhook signature.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Webhook Request.
   *
   * @return bool
   *   TRUE if signature verification passes, FALSE otherwise.
   */
  public function validateWebhookSignature(\Symfony\Component\HttpFoundation\Request $request) {
    $headers = array_map('current', $request->headers->all());
    $webhook = $request->getContent();

    // Quick validation to make sure we are not replaying a request
    // from the past.
    $request_date = isset($headers['date']) ? $headers['date'] : "1970";
    $request_timestamp = strtotime($request_date);
    $timestamp = time();
    // Due to networking delays and mismatched clocks, we are making the request
    // accepting window 60s.
    if (abs($request_timestamp - $timestamp) > 60) {
      $message = new FormattableMarkup('The Webhook request seems that was issued in the past [Request timestamp = @t1, server timestamp = @t2]: rejected: @whook', array(
        '@t1' => $request_timestamp,
        '@t2' => $timestamp,
        '@whook' => print_r($webhook, TRUE),
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
      return FALSE;
    }

    // Reading webhook endpoint:
    $path = $request->getBasePath() . $request->getPathInfo();
    $webhook_url = $this->config->get('webhook_url') ?: $path;
    $url = parse_url($webhook_url);
    $webhook_path = $url['path'];
    $webhook_path .= isset($url['query']) ? '?' . $url['query'] : '';

    $authorization_header = isset($headers['authorization']) ? $headers['authorization'] : '';
    // Reading type of webhook request.
    $webhook_array = Json::decode($webhook);
    $status = $webhook_array['status'];
    $authorization = '';

    // Constructing the message to sign.
    switch ($status) {
      case 'shared_secret_regenerated':
        $this->contentHubSubscription->getSettings();
        $secret_key = $this->contentHubSubscription->getSharedSecret();
        $signature = $this->clientManager->getRequestSignature($request, $secret_key);
        $authorization = 'Acquia Webhook:' . $signature;
        $this->loggerFactory->get('acquia_contenthub')->debug('Received Webhook for shared secret regeneration. Settings updated.');
        break;

      case 'successful':
      case 'processing':
      case 'in-queue':
      case 'failed':
        $secret_key = $this->contentHubSubscription->getSharedSecret();
        $signature = $this->clientManager->getRequestSignature($request, $secret_key);
        $authorization = 'Acquia Webhook:' . $signature;
        break;

      case 'pending':
        $api = $this->config->get('api_key');
        $encryption = (bool) $this->config->get('encryption_key_file');
        if ($encryption) {
          $secret = $this->config->get('secret_key');
          $secret_key = $this->clientManager->cipher()->decrypt($secret);
        }
        else {
          $secret_key = $this->config->get('secret_key');
        }
        $signature = $this->clientManager->getRequestSignature($request, $secret_key);

        $authorization = "Acquia $api:" . $signature;
        break;

    }
    return (bool) ($authorization === $authorization_header);
  }

  /**
   * Enables other modules to process the webhook.
   *
   * @param array $webhook
   *   The webhook sent by the Content Hub.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response Object.
   */
  public function processWebhook($webhook) {
    $assets = isset($webhook['assets']) ? $webhook['assets'] : FALSE;
    if (count($assets) > 0) {
      \Drupal::moduleHandler()->alter('acquia_contenthub_process_webhook', $webhook);
    }
    else {
      $message = new FormattableMarkup('Error processing Webhook (It contains no assets): @whook', array(
        '@whook' => print_r($webhook, TRUE),
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
    }
    return new Response('');
  }

  /**
   * Processing the registration of a webhook.
   *
   * @param array $webhook
   *   The webhook coming from Plexus.
   *
   * @return \Acquia\ContentHubClient\ResponseSigner|\Symfony\Component\HttpFoundation\Response
   *   The REsponse.
   */
  public function registerWebhook($webhook) {
    $uuid = isset($webhook['uuid']) ? $webhook['uuid'] : FALSE;
    $origin = $this->config->get('origin', '');
    $api_key = $this->config->get('api_key', '');

    if ($uuid && $webhook['initiator'] == $origin && $webhook['publickey'] == $api_key) {

      $encryption = (bool) $this->config->get('encryption_key_file', '');
      $secret = $this->config->get('secret_key', '');
      if ($encryption) {
        $secret = $this->clientManager->cipher()->decrypt($secret);
      }

      // Creating a response.
      $response = new ResponseSigner($api_key, $secret);
      $response->setContent('{}');
      $response->setResource('');
      $response->setStatusCode(ResponseSigner::HTTP_OK);
      $response->signWithCustomHeaders(FALSE);
      $response->signResponse();
      return $response;
    }
    else {
      $ip_address = \Drupal::request()->getClientIp();
      $message = new FormattableMarkup('Webhook [from IP = @IP] rejected (initiator and/or publickey do not match local settings): @whook', array(
        '@IP' => $ip_address,
        '@whook' => print_r($webhook, TRUE),
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
      return new Response('');
    }
  }

}
