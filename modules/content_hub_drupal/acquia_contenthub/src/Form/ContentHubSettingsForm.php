<?php

/**
 * @file
 * Contains Drupal\acquia_contenthub\Form\ContentHubSettingsForm.
 */

namespace Drupal\acquia_contenthub\Form;

use Drupal\acquia_contenthub\Client\ClientManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Uuid\Uuid;
use Drupal\acquia_contenthub\ContentHubSubscription;

/**
 * Defines the form to configure the Content Hub connection settings.
 */
class ContentHubSettingsForm extends ConfigFormBase {

  /**
   * The entity manager.
   *
   * @var |Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * Content Hub Subscription.
   *
   * @var \Drupal\acquia_contenthub\ContentHubSubscription
   */
  protected $contentHubSubscription;

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
   * ContentHubSettingsForm constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientManager $client_manager
   *   The client manager.
   */
  public function __construct(ClientManager $client_manager, ContentHubSubscription $contenthub_subscription) {
    $this->clientManager = $client_manager;
    $this->contentHubSubscription = $contenthub_subscription;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_subscription')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('acquia_contenthub.admin_settings');
    $form['settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Connection Settings'),
      '#collapsible' => TRUE,
      '#description' => t('Settings for connection to Acquia Content Hub'),
    );

    $form['settings']['hostname'] = array(
      '#type' => 'textfield',
      '#title' => t('Acquia Content Hub Hostname'),
      '#description' => t('The hostname of the Acquia Content Hub API, e.g. http://localhost:5000'),
      '#default_value' => $config->get('hostname'),
      '#required' => TRUE,
    );

    $form['settings']['api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    );

    $form['settings']['secret_key'] = array(
      '#type' => 'password',
      '#title' => t('Secret Key'),
      '#default_value' => $config->get('secret_key'),
    );

    $client_name = $config->get('client_name');
    $readonly = empty($client_name) ? [] : ['readonly' => TRUE];

    $form['settings']['client_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Name'),
      '#default_value' => $client_name,
      '#required' => TRUE,
      '#description' => t('A unique client name by which Acquia Content Hub will identify this site. The name of this client site cannot be changed once set.'),
      '#attributes' => $readonly,
    );

    $form['settings']['origin'] = array(
      '#type' => 'item',
      '#title' => t("Site's Origin UUID"),
      '#markup' => $config->get('origin'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $hostname = NULL;
    if (UrlHelper::isValid($form_state->getValue('hostname'), TRUE)) {
      $hostname = $form_state->getValue('hostname');
    }
    else {
      return $form_state->setErrorByName('hostname', $this->t('This is not a valid URL. Please insert it again.'));
    }

    $api = NULL;
    // Important. This should never validate if it is an UUID. Lift 3 does not
    // use UUIDs for the api_key but they are valid for Content Hub.
    if ($form_state->getValue('api_key')) {
      $api = $form_state->getValue('api_key');
    }
    else {
      return $form_state->setErrorByName('api_key', $this->t('Please insert an API Key.'));
    }

    $secret = NULL;
    if ($form_state->hasValue('secret_key')) {
      $secret = $form_state->getValue('secret_key');
    }
    else {
      return $form_state->setErrorByName('secret_key', $this->t('Please insert a Secret Key.'));
    }

    if ($form_state->hasValue('client_name')) {
      $client_name = $form_state->getValue('client_name');
    }
    else {
      return $form_state->setErrorByName('client_name', $this->t('Please insert a Client Name.'));
    }

    if (Uuid::isValid($form_state->getValue('origin'))) {
      $origin = $form_state->getValue('origin');
    }
    else {
      $origin = '';
    }

    // Validate that the client name does not exist yet.
    $this->clientManager->resetConnection([
      'hostname' => $hostname,
      'api' => $api,
      'secret' => $secret,
      'origin' => $origin,
    ]);

    if ($this->clientManager->isClientNameAvailable($client_name) === FALSE) {
      $message = $this->t('The client name "%name" is already being used. Please insert another one.', array(
        '%name' => $client_name,
      ));
      return $form_state->setErrorByName('client_name', $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // We assume here all inserted values have passed validation.
    // First Register the site to Content Hub.
    $client_name = $form_state->getValue('client_name');

    if ($this->contentHubSubscription->registerClient($client_name)) {
      // Registration was successful. Save the rest of the values.
      // Get the admin config.
      $config = $this->config('acquia_contenthub.admin_settings');
      $hostname = NULL;
      if ($form_state->hasValue('hostname')) {
        $config->set('hostname', $form_state->getValue('hostname'));
      }

      $api = NULL;
      if ($form_state->hasValue('api_key')) {
        $config->set('api_key', $form_state->getValue('api_key'));
      }

      $secret = NULL;
      if ($form_state->hasValue('secret_key')) {
        $secret = $form_state->getValue('secret_key');

        // Only reset the secret if it is passed. If encryption is activated,
        // then encrypt it too.
        $encryption = $config->get('encryption_key_file');

        // Encrypting the secret, to save for later use.
        if (!empty($secret) && !empty($encryption)) {
          $encrypted_secret = $this->clientManager->cipher()->encrypt($secret);
        }
        elseif ($secret) {
          // Not encryption was provided.
          $encrypted_secret = $secret;
        }
        $config->set('secret_key', $encrypted_secret);
      }

      $config->save();

    }
    else {
      // Get the admin config.
      $config = $this->config('acquia_contenthub.admin_settings');

      // Call drupal_get_messages, to override the dsm. Otherwise,
      // on save it will show two messages.
      drupal_get_messages();
      if (!empty($config->get('origin'))) {
        drupal_set_message(t('Client is already registered with Acquia Content Hub.'), 'error');
      }
      else {
        drupal_set_message(t('There is a problem connecting to Acquia Content Hub. Please ensure that your hostname and credentials are correct.'), 'error');
      }
    }

  }

}
