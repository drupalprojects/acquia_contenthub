<?php

/**
 * @file
 * Contains \Drupal\system\EventSubscriber\ConfigCacheTag.
 */

namespace Drupal\acquia_contenthub\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber invalidating cache tags when system config objects are saved.
 */
class ConfigCacheTag implements EventSubscriberInterface {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Constructs a ConfigCacheTag object.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * Invalidate cache tags when particular system config objects are saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event) {
    // Changing the entity_config config object needs to invalidate all caches
    // that went through the rest module. Since we cannot do cache bubble-up
    // from within the normalizer and the fact that we do know that every
    // request through the rest system is tagged with config:rest.settings we
    // are clearing anything that has anything to do with rest. This is
    // sub-optimal but a config change does not happen that frequently and is
    // therefor an acceptable solution.
    if ($event->getConfig()->getName() === 'acquia_contenthub.entity_config') {
      $this->cacheTagsInvalidator->invalidateTags(['config:rest.settings']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
