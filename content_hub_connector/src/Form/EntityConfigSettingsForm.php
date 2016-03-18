<?php
/**
 * @file
 * Contains Drupal\content_hub_connector\Form\EntityConfigSettingsForm.
 */

namespace Drupal\content_hub_connector\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityDisplayRepository;

/**
 * Defines the form to configure the entity types and bundles to be exported.
 */
class EntityConfigSettingsForm extends ConfigFormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfoManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'content_hub_connector.entity_config';
  }

  /**
   * Constructs an IndexAddFieldsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info_manager, EntityDisplayRepository $entity_display_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfoManager = $entity_type_bundle_info_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_bundle_info_manager = $container->get('entity_type.bundle.info');
    $entity_type_manager = $container->get('entity_type.manager');
    $entity_display_repository = $container->get('entity_display.repository');
    return new static($entity_type_manager, $entity_type_bundle_info_manager, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_hub_connector.entity_config'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = array(
      '#type' => 'item',
      '#description' => t('Select the bundles of the entity types you would like to publish to Acquia Content Hub. <br/><br/><strong>Optional</strong><br/>Choose a view mode for each of the selected bundles to be rendered before sending to Acquia Content Hub. <br/>You can choose the view modes to use for rendering the items of different datasources and bundles. We recommend using a dedicated view mode to make sure that only relevant data (especially no field labels) will be transferred to Content Hub.'),
    );

    $form['entity_config'] = array(
      '#type' => 'fieldgroup',
      '#title' => t('Entity Configuration'),
      '#collapsible' => TRUE,
    );
    // Get all allowed entity types.
    $entity_types = $this->content_hub_connector_get_entity_types();

    $entity_config = $this->config('content_hub_connector.entity_config');
    foreach ($entity_types as $type => $bundle) {
      // @todo Fix this. Total hack to only support explicit content types.
      if ($type != 'node') {
        continue;
      }
      $form['entity_config'][$type] = array(
        '#type' => 'fieldset',
        '#title' => $type,
        '#collapsible' => TRUE,
      );

      $hub_entities = array();
      foreach ($bundle as $bundle_id => $bundle_name) {
        $hub_entities[$bundle_id] = $bundle_name;
      }

      $form['entity_config'][$type]['hubentities_' . $type] = array(
        '#type' => 'checkboxes',
        '#options' => $hub_entities,
        '#default_value' => $entity_config->get('hubentities_' . $type) ?: array(),
      );
      $form['entity_config'][$type]['rendering_config'] = array(
        '#type' => 'fieldgroup',
        '#title' => t('Entity View Modes'),
        '#collapsible' => TRUE,
        '#description' => t('Select the view modes for the bundles you would like to publish to Acquia Content Hub.'),
      );

      foreach ($bundle as $bundle_id => $bundle_name) {
        $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle($type, $bundle_id);
        $entity_label = $this->entityTypeManager->getDefinition($type)->getLabel();
        if (count($view_modes) > 0) {
          $form['entity_config'][$type]['rendering_config_' . $type . '_' . $bundle_id] = array(
            '#type' => 'select',
            '#title' => $this->t('View mode for %entitytype » %bundle', array('%entitytype' => $entity_label, '%bundle' => $hub_entities[$bundle_id])),
            '#options' => $view_modes,
            '#description' => "You can select or deselect any option. Selecting none will avoid rendering that specific content to be included in Acquia Content Hub.",
            '#multiple' => TRUE,
          );
          $previous_rendering_config = $entity_config->get('rendering_config_' . $type . '_' . $bundle_id);
          if (isset($previous_rendering_config)) {
            $form['entity_config'][$type]['rendering_config_' . $type . '_' . $bundle_id]['#default_value'] = $entity_config->get('rendering_config_' . $type . '_' . $bundle_id);
          }
        }
        else {
          $form['entity_config'][$type]['rendering_config_' . $type . '_' . $bundle_id] = array(
            '#type' => 'value',
            '#value' => $view_modes ? key($view_modes) : FALSE,
          );
          $form['entity_config'][$type]['rendering_config']['#description'] = t('No available view modes found to render.');
        }
      }
    }

    $roles = user_role_names();
    $form['role'] = array(
      '#type' => 'select',
      '#title' => $this->t('User role'),
      '#description' => $this->t('Your item will be rendered as seen by a user with the selected role. We recommend to just use "@anonymous" here to prevent data leaking out to unauthorized roles.', array('@anonymous' => $roles[AccountInterface::ANONYMOUS_ROLE])),
      '#options' => $roles,
      '#multiple' => FALSE,
      '#default_value' => $entity_config->get('role') ? $entity_config->get('role') : AccountInterface::ANONYMOUS_ROLE,
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('content_hub_connector.entity_config');
    $entity_types = $this->content_hub_connector_get_entity_types();

    foreach ($entity_types as $type => $bundles) {
      if ($form_state->hasValue('hubentities_' . $type)) {
        $config->set('hubentities_' . $type, $form_state->getValue('hubentities_' . $type));
      }
      foreach ($bundles as $bundle => $label) {
        if ($form_state->hasValue('rendering_config_' . $type . '_' . $bundle)) {
          $config->set('rendering_config_' . $type . '_' . $bundle, $form_state->getValue('rendering_config_' . $type . '_' . $bundle));
        }
      }
    }
    if ($form_state->hasValue('role')) {
      $config->set('role', $form_state->getValue('role'));
    }

    $config->save();
  }

  /**
   * Obtains the list of entity types.
   */
  public function content_hub_connector_get_entity_types() {
    $types = $this->entityTypeManager->getDefinitions();

    $entity_types = array();
    foreach ($types as $type => $entity) {
      // Only support ContentEntityTypes.
      if ($entity instanceof ContentEntityType) {
        $bundles = $this->entityTypeBundleInfoManager->getBundleInfo($type);

        // Here we need to load all the different bundles?
        if (isset($bundles) && count($bundles) > 0) {
          foreach ($bundles as $key => $bundle) {
            $entity_types[$type][$key] = $bundle['label'];
          }
        }
        else {
          // In cases where there are no bundles, but the entity can be
          // selected.
          $entity_types[$type][$type] = $entity['label'];
        }

      }
    }
    return $entity_types;
  }

}
