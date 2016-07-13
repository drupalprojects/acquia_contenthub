<?php
/**
 * @file
 * Processes Webhooks coming from Content Hub.
 */

namespace Drupal\acquia_contenthub\Controller;

use Acquia\ContentHubClient\ResponseSigner;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
      $container->get('acquia_contenthub.content_hub_subscription')
    );
  }

  public function receiveWebhook() {
    // Obtain the headers.
    $request = Request::createFromGlobals();
    $headers = array_map('current', $request->headers->all());
    $webhook = $request->getContent();

    if ($this->validateWebhookSignature($webhook)) {
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
              $this->processWebhook($webhook);
              break;

            case 'pending':
              $this->registerWebhook($webhook);
              break;

            case 'shared_secret_regenerated':
              $this->updateSharedSecret($webhook);

          }
        }
      }

    }

  }

  /**
   * Enables other modules to process the webhook.
   *
   * @param array $webhook
   *   The webhook sent by the Content Hub.
   */
  public function processWebhook($webhook) {
    $assets = isset($webhook['assets']) ? $webhook['assets'] : FALSE;
    if (count($assets) > 0) {
      \Drupal::moduleHandler()->alter('content_hub_connector_process_webhook', $webhook);
    }
    else {
      $message = new FormattableMarkup('Error processing Webhook (It contains no assets): @whook', array(
        '@whook' => print_r($webhook, TRUE),
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
    }
  }

  public function validateWebhookSignature($webhook) {

  }

  /**
   * Processing the registration of a webhook.
   *
   * @param  array $webhook
   *   The webhook coming from Plexus.
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
      $response->send();
      return $response;
    }
    else {
      $ip_address = \Drupal::request()->getClientIp();
      $message = new FormattableMarkup('Webhook [from IP = @IP] rejected (initiator and/or publickey do not match local settings): @whook', array(
        '@IP' => $ip_address,
        '@whook' => print_r($webhook, TRUE),
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
    }
  }
}
