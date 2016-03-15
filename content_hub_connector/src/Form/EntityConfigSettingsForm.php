<?php
/**
 * @file
 */

namespace Drupal\content_hub_connector\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Defines a form to configure module settings.
 */
class EntityConfigSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'content_hub_connector.entity_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_hub_connector.entity_config'];
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('content_hub_connector.entity_config');
    $form['entity_config'] = array(
      '#type' => 'fieldset',
      '#title' => t('Entity Configuration'),
      '#collapsible' => TRUE,
      '#description' => t('Select the entity types you would like to publish to content hub.'),
    );

    $entity_types = $this->_content_hub_connector_get_entity_types();
    foreach ($entity_types as $type => $bundle) {

      $form['entity_config'][$type] = array(
        '#type' => 'fieldset',
        '#title' => $type,
        '#collapsible' => TRUE,
        '#description' => t('Select the bundles to publish in the content hub.'),
      );
      $hubentities = array();
      foreach ($bundle as $bundle_id => $bundle_name) {
        $hubentities[$bundle_id] = $bundle_name;
      }
      $form['entity_config'][$type]['content_hub_connector_hubentities_' . $type] = array(
        '#type' => 'checkboxes',
        '#options' => $hubentities,
        '#default_value' => $config->get('content_hub_connector_hubentities_' . $type) ?: array(),
      );

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('content_hub_connector.entity_config');
    $entity_types = $this->_content_hub_connector_get_entity_types();
    foreach ($entity_types as $type => $bundle) {
      if ($form_state->hasValue('content_hub_connector_hubentities_' . $type)) {
        $config->set('content_hub_connector_hubentities_' . $type, $form_state->getValue('content_hub_connector_hubentities_' . $type));
      }
    }

    $config->save();
  }

  /**
   * Obtains the list of entity types.
   */
  function _content_hub_connector_get_entity_types() {
    $types = \Drupal::entityManager()->getDefinitions();
    $entity_types = array();
    foreach ($types as $type => $entity) {
      if ($entity instanceof ContentEntityType) {
        $bundles = \Drupal::entityManager()->getBundleInfo($type);
        // Here we need to load all the different bundles?
        if (isset($bundles) && count($bundles) > 0) {
          foreach ($bundles as $key => $bundle) {
            $entity_types[$type][$key] = $bundle['label'];
          }
        }
        else {
          // In cases where there are no bundles, but the entity can be selected.
          $entity_types[$type][$type] = $entity['label'];
        }

      }
    }
    return $entity_types;
  }

}
