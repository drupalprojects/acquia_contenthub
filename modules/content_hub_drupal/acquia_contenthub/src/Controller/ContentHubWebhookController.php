<?php
/**
 * @file
 * Processes Webhooks coming from Content Hub.
 */

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

  public function receiveWebhook($webhook) {
    // Obtain the headers.
    $request = Request::createFromGlobals();
    $headers = array_map('current', $request->headers->all());

  }

}
