<?php

/**
 * @file
 * Contains Drupal\acquia_contenthub\Form\ContentHubSettingsForm.
 */

namespace Drupal\acquia_contenthub\Form;

use Drupal\acquia_contenthub\Client\ClientManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Acquia\ContentHubClient;
use \GuzzleHttp\Exception\ClientException;
use \GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public function __construct(ClientManager $client_manager) {
    $this->clientManager = $client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $client_manager = \Drupal::service('acquia_contenthub.client_manager');
    return new static($client_manager);
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

    $form['settings']['rewrite_domain'] = array(
      '#type' => 'url',
      '#title' => t('Rewrite domain before sending to Acquia Content Hub.'),
      '#description' => t('Useful when working with a site behind a proxy such as ngrok. Will transform the URL to what you add in here so that Acquia Content Hub knows where to fetch the resource. Eg.: localhost:80/node/1 becomes myexternalsite.someproxy.com/node/1'),
      '#default_value' => $config->get('rewrite_domain'),
      '#required' => FALSE,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('acquia_contenthub.admin_settings');

    /*// Let active plugins save their settings.
    foreach ($this->configurableInstances as $instance) {
    $instance->submitConfigurationForm($form, $form_state);
    }*/

    $hostname = NULL;
    if ($form_state->hasValue('hostname')) {
      $hostname = $form_state->getValue('hostname');
      $config->set('hostname', $form_state->getValue('hostname'));
    }

    $api = NULL;
    if ($form_state->hasValue('api_key')) {
      $api = $form_state->getValue('api_key');
      $config->set('api_key', $form_state->getValue('api_key'));
    }

    $secret = NULL;
    if ($form_state->hasValue('secret_key')) {
      $secret = $form_state->getValue('secret_key');
      $config->set('secret_key', $form_state->getValue('secret_key'));
    }

    if ($form_state->hasValue('rewrite_domain')) {
      $config->set('rewrite_domain', $form_state->getValue('rewrite_domain'));
    }

    if ($form_state->hasValue('client_name')) {
      $config->set('client_name', $form_state->getValue('client_name'));
    }

    if ($form_state->hasValue('origin')) {
      $config->set('origin', $form_state->getValue('origin'));
    }

    // Only reset the secret if it is passed. If encryption is activated,
    // then encrypt it too.
    $encryption = $config->get('encryption_key_file');

    // Encrypting the secret, to save for later use.
    if (!empty($secret) && !empty($encryption)) {
      $encrypted_secret = $this->clientManager->cipher()->encrypt($secret);
      $decrypted_secret = $secret;
    }
    elseif ($secret) {
      $encrypted_secret = $secret;
      $decrypted_secret = $secret;
    }
    else {
      // We need a decrypted secret to make the API call, but sometimes it might
      // not be given.
      // Secret was not provided, try to get it from the variable.
      $secret = $config->get('secret_key');
      $encrypted_secret = $secret;

      if ($secret && !empty($encryption)) {
        $decrypted_secret = $this->clientManager->cipher()->decrypt($secret);
      }
      else {
        $decrypted_secret = $secret;
      }
    }

    $origin = $config->get('origin');
    $client = new ContentHubClient\ContentHub($api, $decrypted_secret, $origin, ['base_url' => $hostname]);

    // Store what's we have set so far before registering.
    $config->save();

    // Content Hub does not support a new registration when we already have a
    // client_name.
    if ($config->get('client_name')) {
      // return;.
    }

    // Get the client name.
    $name = $form_state->getValue('client_name');

    try {
      // This will fail if the name was already registered.
      $site = $client->register($name);
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      if (isset($response)) {
        drupal_set_message(t('Error registering client with name="@name" (Error Code = @error_code: @error_message)', array(
          '@error_code' => $response->getStatusCode(),
          '@name' => $name,
          '@error_message' => $response->getReasonPhrase(),
        )), 'error');
        // @todo inject this with a service
        \Drupal::logger('acquia_contenthub')->error($response->getReasonPhrase());
      }
      return;
    } catch (RequestException $e) {
      // Some error connecting to Content Hub... are your credentials set
      // correctly?
      $message = $e->getMessage();
      drupal_set_message(t("Couldn't get authorization from Content Hub. Are your credentials inserted correctly? The following error was returned: @msg", array(
        '@msg' => $message,
      )), 'error');
      \Drupal::logger('acquia_contenthub')->error($message);
      return;
    }

    // Registration successful. Setting up the origin and other variables.
    $config->set('origin', $site['uuid']);
    $config->set('client_name', $name);

    // Resetting the origin now that we have one.
    $origin = $site['uuid'];
    drupal_set_message(t('Successful Client registration with name "@name" (UUID = @uuid)', array(
      '@name' => $name,
      '@uuid' => $origin,
    )), 'status');

    $config->save();
  }

}
