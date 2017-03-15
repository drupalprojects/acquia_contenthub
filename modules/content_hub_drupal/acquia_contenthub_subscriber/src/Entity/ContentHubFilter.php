<?php

namespace Drupal\acquia_contenthub_subscriber\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface;
use Drupal\user\Entity\User;
use DateTime;

/**
 * Defines the ContentHubFilter entity.
 *
 * @ConfigEntityType(
 *   id = "contenthub_filter",
 *   label = @Translation("ContentHubFilter"),
 *   handlers = {
 *     "list_builder" = "Drupal\acquia_contenthub_subscriber\Controller\ContentHubFilterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\acquia_contenthub_subscriber\Form\ContentHubFilterForm",
 *       "edit" = "Drupal\acquia_contenthub_subscriber\Form\ContentHubFilterForm",
 *       "delete" = "Drupal\acquia_contenthub_subscriber\Form\ContentHubFilterDeleteForm",
 *     }
 *   },
 *   config_prefix = "contenthub_filter",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/contenthub_filter/{contenthub_filter}",
 *     "delete-form" = "/admin/config/system/contenthub_filter/{contenthub_filter}/delete",
 *   }
 * )
 */
class ContentHubFilter extends ConfigEntityBase implements ContentHubFilterInterface {

  /**
   * The ContentHubFilter ID.
   *
   * @var string
   */
  public $id;

  /**
   * The ContentHubFilter label.
   *
   * @var string
   */
  public $name;

  /**
   * The Publish setting.
   *
   * @var string
   */
  public $publish_setting;

  /**
   * The Search term.
   *
   * @var string
   */
  public $search_term;

  /**
   * The From Date.
   *
   * @var string
   */
  public $from_date;

  /**
   * The To Date.
   *
   * @var string
   */
  public $to_date;

  /**
   * The Source.
   *
   * @var string
   */
  public $source;

  /**
   * The Tags.
   *
   * @var string
   */
  public $tags;

  /**
   * The Author or the user UID who created the filter.
   *
   * @var int
   */
  public $author;

  /**
   * Returns the human-readable publish_setting.
   *
   * @return string
   *   The human-readable publish_setting.
   */
  public function getPublishSetting() {
    $setting = [
      'none' => t('None'),
      'import' => t('Always Import'),
      'publish' => t('Always Publish'),
    ];
    return $setting[$this->publish_setting];
  }

  /**
   * Returns the Publish status for this particular filter.
   *
   * This is the status flag to be saved on node entities.
   *
   * @return int|bool
   *   0 if Unpublished status, 1 for Publish status, FALSE otherwise.
   */
  public function getPublishStatus() {
    $status = [
      'none' => FALSE,
      'import' => 0,
      'publish' => 1,
    ];
    return $status[$this->publish_setting];
  }

  /**
   * Returns the Author name (User account name).
   *
   * @return string
   *   The user account name.
   */
  public function getAuthor() {
    $user = User::load($this->author);
    return $user->getAccountName();
  }

  /**
   * Gets the Conditions to match in a webhook.
   */
  public function getConditions() {
    $tags = [];

    // Search Term.
    if (!empty($this->search_term)) {
      $tags[] = $this->search_term;
    }

    // <Date From>to<Date-To>.
    if (!empty($this->from_date) || !empty($this->to_date)) {
      $tags[] = 'modified:' . $this->from_date . 'to' . $this->to_date;
    }

    // Building origin condition.
    if (!empty($this->source)) {
      $origins = explode(',', $this->source);
      foreach ($origins as $origin) {
        $tags[] = 'origin:' . $origin;
      }
    }

    // Building field_tags condition.
    if (!empty($this->tags)) {
      $field_tags = explode(',', $this->tags);
      foreach ($field_tags as $field_tag) {
        $tags[] = 'field_tags:' . $field_tag;
      }
    }

    return implode(',', $tags);
  }

  /**
   * Change Date format from "m-d-Y" to "Y-m-d".
   */
  public function changeDateFormatMonthDayYear2YearMonthDay() {
    if (!empty($this->from_date)) {
      if ($from_date = DateTime::createFromFormat('m-d-Y', $this->from_date)) {
        $this->from_date = $from_date->format('Y-m-d');
      }
    }
    if (!empty($this->to_date)) {
      if ($to_date = DateTime::createFromFormat('m-d-Y', $this->to_date)) {
        $this->to_date = $to_date->format('Y-m-d');
      }
    }
    return $this;
  }

  /**
   * Change Date format from "Y-m-d" to "m-d-Y".
   */
  public function changeDateFormatYearMonthDay2MonthDayYear() {
    if (!empty($this->from_date)) {
      if ($from_date = DateTime::createFromFormat('Y-m-d', $this->from_date)) {
        $this->from_date = $from_date->format('m-d-Y');
      }
    }
    if (!empty($this->to_date)) {
      if ($to_date = DateTime::createFromFormat('Y-m-d', $this->to_date)) {
        $this->to_date = $to_date->format('m-d-Y');
      }
    }
    return $this;
  }

  /**
   * Update values of the original entity to the one submitted by REST.
   *
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface $contenthub_filter_original
   *   The original content hub filter.
   *
   * @return \Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface
   *   The updated content hub filter.
   */
  public function updateValues(ContentHubFilterInterface $contenthub_filter_original) {
    // The following are the only fields that we allow to change through PATCH.
    $replaceable_fields = [
      'name',
      'publish_setting',
      'search_term',
      'from_date',
      'to_date',
      'source',
      'tags',
    ];

    foreach ($this->_restSubmittedFields as $field) {
      if (in_array($field, $replaceable_fields)) {
        $contenthub_filter_original->{$field} = $this->{$field};
      }
    }
    return $contenthub_filter_original;
  }

}
