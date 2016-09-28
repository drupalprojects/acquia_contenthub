<?php
/**
 * @file
 * ContentHubFilter Form.
 */

namespace Drupal\acquia_contenthub_subscriber\Form;

use Drupal\Core\Entity\EntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Prepares the form for input Content Hub Filters.
 */
class ContentHubFilterForm extends EntityForm {

  /**
   * Public Constructor.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $contenthub_filter = $this->entity;

    // Change page title for the edit operation.
    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Content Hub Filter: @name', array('@name' => $contenthub_filter->name));
    }

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $contenthub_filter->label(),
      '#description' => $this->t("Content Hub Filter Name."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $contenthub_filter->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
        'source' => array('name'),
      ),
      '#disabled' => !$contenthub_filter->isNew(),
    );

    // The Publish Setting.
    $form['publish_setting'] = array(
      '#type' => 'select',
      '#title' => $this->t('Publish Setting'),
      '#options' => array(
        'none' => 'None',
        'import' => 'Always Import',
        'publish' => 'Always Publish',
      ),
      '#default_value' => isset($contenthub_filter->publish_setting) ? $contenthub_filter->publish_setting : 'none',
      '#maxlength' => 255,
      '#description' => $this->t("Sets the Publish setting for this filter."),
      '#required' => TRUE,
    );

    // The Search Term.
    $form['search_term'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Search Term'),
      '#maxlength' => 255,
      '#default_value' => $contenthub_filter->search_term,
      '#description' => $this->t("The search term."),
    );

    // The From Date.
    $form['from_date'] = array(
      '#type' => 'date',
      '#title' => $this->t('Date From'),
      '#default_value' => $contenthub_filter->from_date,
      '#date_date_format' => 'm-d-Y',
      '#description' => $this->t("Date starting from"),
    );

    // The To Date.
    $form['to_date'] = array(
      '#type' => 'date',
      '#title' => $this->t('Date To'),
      '#date_date_format' => 'm-d-Y',
      '#default_value' => $contenthub_filter->to_date,
      '#description' => $this->t("Date until"),
    );

    // The Source.
    $form['source'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Source'),
      '#maxlength' => 255,
      '#default_value' => $contenthub_filter->source,
      '#description' => $this->t("Source"),
    );

    // The Tags.
    $form['tags'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#maxlength' => 255,
      '#default_value' => $contenthub_filter->tags,
      '#description' => $this->t("Tags"),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $contenthub_filter = $this->entity;

    // This Filter is owned by the user who created it.
    if (empty($contenthub_filter->author)) {
      $user = \Drupal::currentUser();
      $contenthub_filter->author = $user->id();
    }

    // Save the filter.
    $status = $contenthub_filter->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label Content Hub Filter.', array(
        '%label' => $contenthub_filter->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label Content Hub Filter was not saved.', array(
        '%label' => $contenthub_filter->label(),
      )));
    }

    $form_state->setRedirect('entity.contenthub_filter.collection');
  }

  /**
   * Checks whether this entity exists or not.
   */
  public function exist($id) {
    $entity = $this->entityQuery->get('contenthub_filter')
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
