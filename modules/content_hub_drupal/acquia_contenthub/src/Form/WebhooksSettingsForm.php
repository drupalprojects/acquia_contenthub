<?php
/**
 * @file
 * Contains Drupal\acquia_contenthub\Form\WebhooksSettingsForm.
 */

namespace Drupal\acquia_contenthub\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;

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
   * @param \Drupal\core\Config\ConfigFactory $config_factory
   *   The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubSubscription $contenthub_subscription
   *   The content hub subscription.
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
      $container->get('acquia_contenthub.acquia_contenthub_subscription')
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
    $config = $this->configFactory->getEditable('acquia_contenthub.admin_settings');
    $form['webhook_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Administer Webhooks'),
      '#collapsible' => TRUE,
      '#description' => t('Manage Acquia Content Hub Webhooks'),
    );
    if ($config->get('webhook_url')) {
      $webhook_url = $config->get('webhook_url');
    }
    else {
      $webhook_url = Url::fromUri('internal:/acquia-contenthub/webhook', array(
        'absolute' => TRUE,
      ))->toString();
    }
    $webhook_uuid = $config->get('webhook_uuid');

    // Ask service about webhooks.
    $webhooks = $this->contentHubSubscription->getSettings()->getWebhooks();

    // Match $remote_uuid via $webhook_url from service response.
    $remote_uuid = NULL;
    foreach ($webhooks as $webhook) {
      if ($webhook['url'] == $webhook_url) {
        $remote_uuid = $webhook['uuid'];
        break;
      }
    }

    // Fix local state if it does not match service state.
    if ($remote_uuid != $webhook_uuid) {
      $config->set('webhook_uuid', $remote_uuid);
      $config->set('webhook_url', $webhook_url);
      $config->save();
      $webhook_uuid = $remote_uuid;
    }

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
      '#description' => t('Please use a full URL (Ex. http://example.com/acquia-contenthub/webhook). This is the end-point where this site will receive webhooks from Acquia Content Hub.'),
      '#default_value' => $webhook_url,
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!UrlHelper::isValid($form_state->getValue('webhook_url'), TRUE)) {
      return $form_state->setErrorByName('webhook_url', $this->t('This is not a valid URL. Please insert it again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $webhook_url = NULL;
    if ($form_state->hasValue('webhook_url')) {
      $webhook_url = $form_state->getValue('webhook_url');
    }

    $webhook_register = (bool) $form_state->getValue('webhook_uuid');

    // Establish current uuid from config.
    $config = $this->configFactory->getEditable('acquia_contenthub.admin_settings');
    $webhook_uuid = $config->get('webhook_uuid');

    // User clicked Submit, but url is already registered.
    if ($webhook_register && $webhook_uuid) {
      drupal_set_message('No change in webhook status was taken.', 'warning');
    }
    // User wants to register and is currently not registered.
    elseif ($webhook_register) {
      $success = $this->contentHubSubscription->registerWebhook($webhook_url);
      if (!$success) {
        drupal_set_message('There was a problem trying to register this webhook.', 'error');
      }
    }
    // User wants to unregister thier webhook.
    else {
      $success = $this->contentHubSubscription->unregisterWebhook($webhook_url);
      if (!$success) {
        drupal_set_message('There was a problem trying to unregister this webhook.', 'error');
      }
    }

  }

}
