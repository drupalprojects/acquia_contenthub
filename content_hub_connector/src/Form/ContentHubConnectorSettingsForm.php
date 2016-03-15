<?php
/**
 * @file
 */

namespace Drupal\content_hub_connector\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Acquia\ContentHubClient;
use \GuzzleHttp\Exception\ClientException;
use \GuzzleHttp\Exception\RequestException;

/**
 * Defines a form to configure module settings.
 */
class ContentHubConnectorSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'content_hub_connector.admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_hub_connector.admin_settings'];
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('content_hub_connector.admin_settings');
    $form['conn_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Connection Settings'),
      '#collapsible' => TRUE,
      '#description' => t('Settings for connection to Content Hub'),
    );

    $form['conn_settings']['content_hub_connector_hostname'] = array(
      '#type' => 'textfield',
      '#title' => t('Content Hub Connector Hostname'),
      '#description' => t('The hostname of the content hub connector api, e.g. http://localhost:5000'),
      '#default_value' => $config->get('content_hub_connector_hostname'),
      '#required' => TRUE,
    );

    $form['conn_settings']['content_hub_connector_api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('API Key'),
      '#default_value' => $config->get('content_hub_connector_api_key'),
      '#required' => TRUE,
    );

    $form['conn_settings']['content_hub_connector_secret_key'] = array(
      '#type' => 'password',
      '#title' => t('Secret Key'),
    );

    $client_name = $config->get('content_hub_connector_client_name');
    $readonly = empty($client_name) ? [] : ['readonly' => TRUE];

    $form['conn_settings']['content_hub_connector_client_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Name'),
      '#default_value' => $client_name,
      '#required' => TRUE,
      '#description' => t('The name of this client site cannot be changed once set.'),
      '#attributes' => $readonly,
    );

    $form['conn_settings']['content_hub_connector_origin'] = array(
      '#type' => 'item',
      '#title' => t("Site's Origin UUID"),
      '#markup' => $config->get('content_hub_connector_origin', 'Client NOT registered.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('content_hub_connector.admin_settings');
    /*// Let active plugins save their settings.
    foreach ($this->configurableInstances as $instance) {
    $instance->submitConfigurationForm($form, $form_state);
    }*/

    if ($form_state->hasValue('content_hub_connector_hostname')) {
      $hostname = $form_state->getValue('content_hub_connector_hostname');
      $config->set('content_hub_connector_hostname', $form_state->getValue('content_hub_connector_hostname'));
    }
    if ($form_state->hasValue('content_hub_connector_api_key')) {
      $api = $form_state->getValue('content_hub_connector_api_key');
      $config->set('content_hub_connector_api_key', $form_state->getValue('content_hub_connector_api_key'));
    }
    if ($form_state->hasValue('content_hub_connector_secret_key')) {
      $secret = $form_state->getValue('content_hub_connector_secret_key');
      $config->set('content_hub_connector_secret_key', $form_state->getValue('content_hub_connector_secret_key'));
    }
    if ($form_state->hasValue('content_hub_connector_client_name')) {
      $config->set('content_hub_connector_client_name', $form_state->getValue('content_hub_connector_client_name'));
    }
    if ($form_state->hasValue('content_hub_connector_origin')) {
      $config->set('content_hub_connector_origin', $form_state->getValue('content_hub_connector_origin'));
    }

    // Only reset the secret if it is passed. If encryption is activated,
    // then encrypt it too.
    $encryption = $config->get('content_hub_connector_encryption_key_file', '');

    // Encrypting the secret, to save for later use.
    if ($secret && !empty($encryption)) {
      $encrypted_secret = content_hub_connector_cipher()->encrypt($secret);
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
      $secret = $config->get('content_hub_connector_secret_key', '');
      $encrypted_secret = $secret;

      if ($secret && !empty($encryption)) {
        $decrypted_secret = content_hub_connector_cipher()->decrypt($secret);
      }
      else {
        $decrypted_secret = $secret;
      }
    }

    $origin = $config->get('content_hub_connector_origin', '');

    $client = new ContentHubClient\ContentHub($api, $decrypted_secret, $origin, ['base_url' => $hostname]);

    if (empty($origin)) {
      $name = $form_state->getValue('content_hub_connector_client_name');

      // Register Client.
      try {
        $site = $client->register($name);

        // Registration successful. Setting up the origin and other variables.
        $config->set('content_hub_connector_origin', $site['uuid']);
        $config->set('content_hub_connector_client_name', $name);

        // Resetting the origin now that we have one.
        $origin = $site['uuid'];
        drupal_set_message(t('Successful Client registration with name "@name" (UUID = @uuid)', array(
          '@name' => $name,
          '@uuid' => $origin,
        )), 'status');
      }
      catch (ClientException $ex) {
        $response = $ex->getResponse()->json();
        if (isset($response) && $error = $response['error']) {
          drupal_set_message(t('Error registering client with name="@name" (Error Code = @error_code: @error_message)', array(
            '@error_code' => $error['code'],
            '@name' => $name,
            '@error_message' => $error['message'],
          )), 'error');
          \Drupal::logger('content_hub_connector')->error($error['message']);
        }
      } catch (RequestException $ex) {
        // Some error connecting to Content Hub... are your credentials set
        // correctly?
        $msg = $ex->getMessage();
        form_set_error('content_hub_connector_secret_key', t("Couldn't get authorization from Content Hub. Are your credentials inserted correctly? The following error was returned: @msg", array(
          '@msg' => $msg,
        )));
      }
    }

    // We are always able to change these variables.
    $config->set('content_hub_connector_hostname', $hostname);
    $config->set('content_hub_connector_api_key', $api);
    $config->set('content_hub_connector_secret_key', $encrypted_secret);
    $config->save();
  }

}
