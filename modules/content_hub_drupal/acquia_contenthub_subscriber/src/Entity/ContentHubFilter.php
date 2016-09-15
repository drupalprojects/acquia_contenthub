<?php
/**
 * @file
 * ContentHubFilter Class.
 */

namespace Drupal\acquia_contenthub_subscriber\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface;

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
}