<?php

/**
 * @file
 * Contains \Drupal\content_hub_connector\Normalizer\ContentEntityViewModesNormalizer.
 */

namespace Drupal\content_hub_connector\Normalizer;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Render\Renderer;

/**
 * Converts a Drupal content entity object's view modes to a Acquia Content Hub CDF array
 * structure.
 */
class ContentEntityViewModesNormalizer extends NormalizerBase {
  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

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
   * Constructs a ContentEntityViewModesNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(EntityManager $entity_manager, Renderer $renderer, ConfigFactory $config_factory) {
    $this->entityManager = $entity_manager;
    $this->renderer = $renderer;
    $this->entityConfig = $config_factory->get('content_hub_connector.entity_config');
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $normalized = array();
    // Exit if format doesn't match.
    if (!$this->checkFormat($format)) {
      return $normalized;
    }

    // Exit if the object is configured not to be rendered.
    $entity_type_id = $object->getEntityTypeId();
    $entity_bundle_id = $object->bundle();
    $object_config = $this->entityConfig->get('entities.' . $entity_type_id . '.' . $entity_bundle_id);
    if (empty($object_config) || empty($object_config['enabled']) || empty($object_config['rendering'])) {
      return $normalized;
    }

    // Normalize.
    $view_modes = $this->entityManager->getViewModes($entity_type_id);
    $view_builder = $this->entityManager->getViewBuilder($entity_type_id);
    foreach ($view_modes as $view_mode_id => $view_mode) {
      if (!in_array($view_mode_id, $object_config['rendering'])) {
        continue;
      }
      $view = $view_builder->view($object, $view_mode_id);
      $html = $this->renderer->renderPlain($view);
      $normalized[$view_mode_id] = array(
        'id' => $view_mode_id,
        'label' => $view_mode['label'],
        'html' => $html,
      );
    }
    return $normalized;
  }

  /**
   * Denormalizes data back into an object of the given class.
   *
   * @param mixed $data data to restore
   * @param string $class the expected class to instantiate
   * @param string $format format the given data was extracted from
   * @param array $context options available to the denormalizer
   *
   * @return object
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    // TODO: Implement denormalize() method.
  }
}
