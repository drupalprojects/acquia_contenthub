<?php
/**
 * @file
 * ContentHubFilter Class.
 */

namespace Drupal\acquia_contenthub_subscriber\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface;
use Drupal\user\Entity\User;

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
    $setting = array(
      'view' => t('View Results'),
      'import' => t('Always Import'),
      'publish' => t('Always Publish'),
    );
    return $setting[$this->publish_setting];
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
    $tags = array();

    // Search Term.
    if (isset($this->search_term)) {
      $tags[] = $this->search_term;
    }

    // <Date From>to<Date-To>.
    if (isset($this->from_date) || isset($this->to_date)) {
      $tags[] ='modified:' . $this->from_date . 'to' . $this->to_date;
    }

    // Building origin condition.
    if (isset($this->source)) {
      $origins = explode(',', $this->source);
      foreach ($origins as $origin) {
        $tags[] = 'origin:' . $origin;
      }
    }

    // Building field_tags condition.
    if (isset($this->tags)) {
      $field_tags = explode(',', $this->tags);
      foreach ($field_tags as $field_tag) {
        $tags[] = 'field_tags:' . $field_tag;
      }
    }

    return implode(',', $tags);
  }
}