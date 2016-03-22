<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesExtractor.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\Renderer;

/**
 * Extracts the rendered view modes from a given ContentEntity Object.
 */
class ContentEntityViewModesExtractor {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The entity config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $entityConfig;

  /**
   * Constructs a ContentEntityViewModesExtractor object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactory $config_factory, EntityDisplayRepository $entity_display_repository, EntityTypeManager $entity_type_manager, Renderer $renderer, AssetResolverInterface $asset_resolver, AssetCollectionRendererInterface $css_collection_renderer, AssetCollectionRendererInterface $js_collection_renderer) {
    $this->entityConfig = $config_factory->get('content_hub_connector.entity_config');
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->assetResolver = $asset_resolver;
    $this->cssCollectionRenderer = $css_collection_renderer;
    $this->jsCollectionRenderer = $js_collection_renderer;
  }

  /**
   * Checks whether the given class is supported for normalization.
   *
   * @param mixed $data
   *   Data to normalize.
   *
   * @return bool
   *   TRUE if is child of supported class.
   */
  public function isChildOfSupportedClass($data) {
    // If we aren't dealing with an object that is not supported return
    // now.
    if (!is_object($data)) {
      return FALSE;
    }

    $supported = (array) $this->supportedInterfaceOrClass;

    return (bool) array_filter($supported, function($name) use ($data) {
      return $data instanceof $name;
    });
  }

  /**
   * Normalizes an object into a set of arrays/scalars.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $object
   *   Object to normalize. Due to the constraints of the class, we know that
   *   the object will be of the ContentEntityInterface type.
   *
   * @return array|null
   *   Returns the extracted view modes or null if the given object is not
   *   supported or if it was not configured in the Content Hub settings.
   */
  public function getRenderedViewModes(ContentEntityInterface $object) {
    $normalized = array();

    // Exit if the class does not support normalizing to the given format.
    if (!$this->isChildOfSupportedClass($object)) {
      return NULL;
    }

    // Exit if the object is configured not to be rendered.
    $entity_type_id = $object->getEntityTypeId();
    $entity_bundle_id = $object->bundle();
    $object_config = $this->entityConfig->get('entities.' . $entity_type_id . '.' . $entity_bundle_id);
    if (empty($object_config) || empty($object_config['enabled']) || empty($object_config['rendering'])) {
      return NULL;
    }

    // Normalize.
    $view_modes = $this->entityDisplayRepository->getViewModes($entity_type_id);
    $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);
    foreach ($view_modes as $view_mode_id => $view_mode) {
      if (!in_array($view_mode_id, $object_config['rendering'])) {
        continue;
      }
      $render_array = $view_builder->view($object, $view_mode_id);
      $html['body'] = $this->renderer->renderRoot($render_array);
      $all_assets = $this->gatherAssetMarkup($render_array);
      $html += $this->renderAssets($all_assets);
      $normalized[$view_mode_id] = array(
        'id' => $view_mode_id,
        'label' => $view_mode['label'],
        'html' => $html,
      );
    }
    return $normalized;
  }

  /**
   * Renders an array of assets.
   *
   * @param array $all_assets
   *   An array of all unrendered assets keyed by type.
   *
   * @return array
   *   An array of all rendered assets keyed by type.
   */
  private function renderAssets(array $all_assets) {
    $renderer_assets = [];
    foreach ($all_assets as $asset_type => $assets) {
      $renderer_assets[$asset_type] = [];
      foreach ($assets as $asset) {
        $renderer_assets[$asset_type][] = $this->renderer->renderRoot($asset);
      }
    }
    return $renderer_assets;
  }

  /**
   * Gathers the markup for each type of asset.
   *
   * @param array $render_array
   *   The render array for the entity.
   *
   * @return array
   *   An array of rendered assets. See self::getRenderedEntity() for the keys.
   */
  private function gatherAssetMarkup(array $render_array) {
    $assets = AttachedAssets::createFromRenderArray($render_array);
    // Render the asset collections.
    $css_assets = $this->assetResolver->getCssAssets($assets, FALSE);
    $all_assets['styles'] = $this->cssCollectionRenderer->render($css_assets);
    list($js_assets_header, $js_assets_footer) = $this->assetResolver->getJsAssets($assets, FALSE);
    $all_assets['scripts'] = $this->jsCollectionRenderer->render($js_assets_header);
    $all_assets['scripts_bottom'] = $this->jsCollectionRenderer->render($js_assets_footer);
    return $all_assets;
  }

}
