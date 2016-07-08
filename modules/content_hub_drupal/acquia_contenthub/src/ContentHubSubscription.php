<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub/ContentHubSubscription.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Component\Uuid\Uuid;

/**
 * Handles operations on the Acquia Content Hub Subscription.
 */
class ContentHubSubscription {

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
  * The Subscription Settings.
  *
  * @var \Acquia\ContentHubClient\Settings
  */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('acquia_contenthub.client_manager')
    );
  }

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *    The config factory.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ConfigFactory $config_factory, ClientManagerInterface $client_manager) {
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->clientManager = $client_manager;
  }

  /**
   * Obtains the Content Hub Subscription Settings.
   *
   * @return \Acquia\ContentHubClient\Settings|bool
   *   The Settings of the Content Hub Subscription if all is fine, FALSE
   *   otherwise.
   */
  public function getSettings() {
    if ($this->settings = $this->clientManager->createRequest('getSettings')) {
      $shared_secret = $this->settings->getSharedSecret();

      // If encryption is activated, then encrypt the shared secret.
      $config = $this->configFactory->get('acquia_contenthub.admin_settings');
      $encryption = $config->get('encryption_key_file', FALSE);
      if ($encryption) {
        $shared_secret = content_hub_connector_cipher()->encrypt($shared_secret);
      }
      $config->set('shared_secret', $shared_secret);
      return $this->settings;
    }
    return FALSE;
  }

  /**
   * Get Subscription's UUID.
   *
   * @return string
   *   The subscription's UUID.
   */
  public function getUuid() {
    if ($this->settings) {
      return $this->settings->getUuid();
    }
    else {
      if ($settings = $this->clientManager->createRequest('getSettings')) {
        return $settings->getUuid();
      }
      return FALSE;
    }
  }

  /**
   * Obtains the "created" flag for this subscription.
   *
   * @return string
   *   The date when the subscription was created.
   */
  public function getCreated() {
    if ($this->settings) {
      return $this->settings->getCreated();
    }
    else {
      if ($settings = $this->clientManager->createRequest('getSettings')) {
        return $settings->getCreated();
      }
      return FALSE;
    }
  }

  /**
   * Returns the date when this subscription was last modified.
   *
   * @return mixed
   *   The date when the subscription was modified. FALSE otherwise.
   */
  public function getModified() {
    if ($this->settings) {
      return $this->settings->getModified();
    }
    else {
      if ($settings = $this->clientManager->createRequest('getSettings')) {
        return $settings->getModified();
      }
      return FALSE;
    }
  }

  /**
   * Returns all Clients attached to this subscription.
   *
   * @return array|bool
   *   An array of Client's data: (uuid, name) pairs, FALSE otherwise.
   */
  public function getClients() {
    if ($this->settings) {
      return $this->settings->getClients();
    }
    else {
      if ($settings = $this->clientManager->createRequest('getSettings')) {
        return $settings->getClients();
      }
      return FALSE;
    }
  }

  /**
   * Returns the Subscription's Shared Secret, used for Webhook verification.
   *
   * @return bool|string
   *   The shared secret, FALSE otherwise.
   */
  public function getSharedSecret() {
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $encryption = $config->get('shared_secret', FALSE);
    if ($shared_secret = $config->get('shared_secret', FALSE)) {
      $encryption = (bool) $config->get('encryption_key_file', FALSE);
      if ($encryption) {
        $shared_secret = content_hub_connector_cipher()->decrypt($shared_secret);
      }
      return $shared_secret;
    }
    else {
      if ($this->settings) {
        return $this->settings->getSharedSecret();
      }
      else {
        if ($settings = $this->clientManager->createRequest('getSettings')) {
          return $settings->getSharedSecret();
        }
        return FALSE;
      }
    }
  }

  /**
   * Regenerates the Subscription's Shared Secret.
   *
   * @return bool|string
   *   The new shared secret if successful, FALSE otherwise.
   */
  public function regenerateSharedSecret() {
    if ($response = $this->clientManager->createRequest('regenerateSharedSecret')) {
      if (isset($response['success']) && $response['success'] == 1) {
        $this->getSettings();
        return $this->getSharedSecret();
      }
    }
    return FALSE;
  }

  /**
   * Registers a client to Acquia Content Hub.
   *
   * It also sets up the Drupal variables with the client registration info.
   *
   * @param string $client_name
   *   The client name to register.
   *
   * @return bool
   *   TRUE if succeeds, FALSE otherwise.
   */
  public function registerClient($client_name) {
    if ($site = $this->clientManager->createRequest('register', array($client_name))) {
      // Resetting the origin now that we have one.
      $origin = $site['uuid'];

      // Registration successful. Setting up the origin and client name.
      $config = $this->configFactory->get('acquia_contenthub.admin_settings');
      $config->set('origin', $origin);
      $config->set('client_name', $client_name);

      drupal_set_message(t('Successful Client registration with name "@name" (UUID = @uuid)', array(
        '@name' => $client_name,
        '@uuid' => $origin,
      )), 'status');
      $message = new FormattableMarkup('Successful Client registration with name "@name" (UUID = @uuid)', array(
        '@name' => $client_name,
        '@uuid' => $origin,
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);

      return TRUE;
    }
    return FALSE;
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
    if ($site = $this->clientManager->createRequest('getClientByName', array($client_name))) {
      if (isset($site['uuid']) && Uuid::isValid($site['uuid'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Updates the locally stored shared secret.
   *
   * If the locally stored shared does not match the value stored in the Content
   * Hub, then we need to update it.
   */
  public function updateSharedSecret() {
    if ($this->isConnected()) {
      if ($this->getSharedSecret() !== $this->clientManager->createRequest('getSettings')->getSharedSecret()) {
        // If this condition is met, then the locally stored shared secret is
        // outdated. We need to update the value from the Hub.
        $this->getSettings();
        $message = new FormattableMarkup('The site has been recovered from having a stale shared secret, which prevented webhooks verification.');
        $this->loggerFactory->get('acquia_contenthub')->debug($message);
      }
    }
  }

  /**
   * Registers a Webhook to Content Hub.
   *
   * Note that this method only sends the request to register a webhook but
   * it is also required for this endpoint ($webhook_url) to provide an
   * appropriate response to Content Hub when it tries to verify the
   * authenticity of the registration request.
   *
   * @param string $webhook_url
   *   The webhook URL.
   *
   * @return bool
   *   TRUE if successful registration, FALSE otherwise.
   */
  public function registerWebhook($webhook_url) {
    $success = FALSE;
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    if ($webhook = $this->clientManager->createRequest('addWebhook', array($webhook_url))) {
      $config->set('webhook_uuid', $webhook['uuid']);
      $config->set('webhook_url', $webhook['url']);
      drupal_set_message(t('Webhooks have been enabled. This site will now receive updates from Content Hub.'), 'status');
      $success = TRUE;
      $message = new FormattableMarkup('Successful registration of Webhook URL = @URL', array(
        '@URL' => $webhook['url'],
      ));
      $this->loggerFactory->get('acquia_contenthub')->debug($message);
    }
    return $success;
  }

  /**
   * Unregisters a Webhook from Content Hub.
   *
   * @param string $webhook_url
   *   The webhook URL.
   *
   * @return bool
   *   TRUE if successful unregistration, FALSE otherwise.
   */
  public function unregisterWebhook($webhook_url) {
    if ($settings = $this->clientManager->createRequest('getSettings')) {
      if ($webhook = $settings->getWebhook($webhook_url)) {
        $this->clientManager->createRequest('deleteWebhook', array($webhook['uuid'], $webhook['url']));
        drupal_set_message(t('Webhooks have been <b>disabled</b>. This site will no longer receive updates from Content Hub.', array(
          '@URL' => $webhook['url'],
        )), 'warning');
        $config = $this->configFactory->get('acquia_contenthub.admin_settings');
        $config->set('webhook_uuid', 0);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Disconnects the client from the Content Hub.
   */
  public function disconnectClient() {
    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $webhook_register = (bool) $config->get('webhook_uuid');
    $webhook_url = $config->get('webhook_url');
    // Un-register the webhook.
    if ($webhook_register) {
      $this->unregisterWebhook($webhook_url);
    }
    variable_del('content_hub_connector_origin');
    variable_del('content_hub_connector_client_name');
    variable_del('content_hub_connector_hostname');
    variable_del('content_hub_connector_api_key');
    variable_del('content_hub_connector_secret_key');
    variable_del('content_hub_connector_webhook_url');
    variable_del('content_hub_connector_webhook_uuid');
    // Clear the cache for suggested client name after disconnecting the client.
    cache_clear_all("suggested_client_name", "cache");
    return FALSE;
  }

  /**
   * Lists Entities from the Content Hub.
   *
   * Example of how to structure the $options parameter:
   *
   * @param array $options
   *   The options array to search.
   *
   * @codingStandardsIgnoreStart
   *
   * $options = [
   *     'limit'  => 20,
   *     'type'   => 'node',
   *     'origin' => '11111111-1111-1111-1111-111111111111',
   *     'fields' => 'status,title,body,field_tags,description',
   *     'filters' => [
   *         'status' => 1,
   *         'title' => 'New*',
   *         'body' => '/Boston/',
   *     ],
   * ];
   *
   * @codingStandardsIgnoreEnd
   *
   * @return array|bool
   *   The result array or FALSE otherwise.
   */
  public function listEntities(array $options) {
    if ($entities = $this->clientManager->createRequest('listEntities', array($options))) {
      return $entities;
    }
    return FALSE;
  }

  /**
   * Purge ALL Entities in the Content Hub.
   *
   * Warning: This function has to be used with care because it has destructive
   * consequences to all data attached to the current subscription.
   *
   * @return string|bool
   *   Returns the json data of the entities list or FALSE if fails.
   */
  public function purgeEntities() {
    if ($list = $this->clientManager->createRequest('purge')) {
      return $list;
    }
    return FALSE;
  }

}
