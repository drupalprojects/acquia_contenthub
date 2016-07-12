<?php
/**
 * @file
 * Contains Drupal\acquia_contenthub\Form\WebhooksSettingsForm.
 */

namespace Drupal\acquia_contenthub\Form;

use Drupal\acquia_contenthub\Client\ClientManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactory;
use Acquia\ContentHubClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;
use Drupal\Core\Url;

/**
 * Defines the form to register the webhooks.
 */
class WebhooksSettingsForm extends ConfigFormBase {

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Content Hub Subscription.
   *
   * @var \Drupal\acquia_contenthub\ContentHubSubscription
   */
  protected $contentHubSubscription;

  /**
   * WebhooksSettingsForm constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientManager $client_manager
   *   The client manager.
   */
  public function __construct(ConfigFactory $config_factory, ContentHubSubscription $contenthub_subscription) {
    $this->configFactory = $config_factory;
    $this->contentHubSubscription = $contenthub_subscription;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('acquia_contenthub.content_hub_subscription')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub.admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_contenthub.admin_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('acquia_contenthub.admin_settings');
    $form['webhook_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Administer Webhooks'),
      '#collapsible' => TRUE,
      '#description' => t('Manage Acquia Content Hub Webhooks'),
    );

    $webhook_url = Url::fromUri('internal:/content-hub/webhook', array(
      'absolute' => TRUE,
    ));
    $webhook_url = $config->get('webhook_url', $webhook_url);
    $webhook_uuid = $config->get('webhook_uuid', 0);
    $readonly = (bool) $webhook_uuid ? ['readonly' => TRUE] : [];

    if ((bool) $webhook_uuid) {
      $title = t('Receive Webhooks (uuid = %uuid)', array(
        '%uuid' => $webhook_uuid,
      ));
    }
    else {
      $title = t('Receive Webhooks');
    }

    $form['webhook_settings']['webhook_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Acquia Content Hub URL'),
      '#description' => t('Please use a full URL (Ex. http://example.com/webhook). This is the end-point where this site will receive webhooks from Acquia Content Hub.'),
      '#default_value' => $config->get('webhook_url'),
      '#required' => TRUE,
    );

    $form['webhook_settings']['webhook_uuid'] = [
      '#type' => 'checkbox',
      '#title' => $title,
      '#default_value' => (bool) $webhook_uuid,
      '#description' => $this->t('Webhooks must be enabled to receive updates from Acquia Content Hub'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('acquia_contenthub.admin_settings');

    $webhook_url = NULL;
    if ($form_state->hasValue('webhook_url')) {
      $webhook_url = $form_state->getValue('webhook_url');
      $config->set('webhook_url', $form_state->getValue('webhook_url'));
    }

    $webhook_uuid = NULL;
    if ($form_state->hasValue('webhook_uuid')) {
      $webhook_uuid = $form_state->getValue('webhook_uuid');
      $config->set('webhook_uuid', $form_state->getValue('webhook_uuid'));
    }

    $webhook_register = (bool) $form_state->getValue('webhook_uuid');
    $webhook_url = $form_state->getValue('webhook_url');

    // Perform the registration / un-registration.
    if ($webhook_register) {
      return $this->contentHubSubscription->registerWebhook($webhook_url);
    }
    else {
      return $this->contentHubSubscription->unregisterWebhook($webhook_url);
    }

    $config->save();
  }

}
