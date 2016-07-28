<?php

namespace Drupal\acquia_contenthub\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\rest\Plugin\Type\ResourcePluginManager;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\ContentEntityType;

/**
 * Subscriber for REST-style routes.
 */
class ResourceRoutes extends RouteSubscriberBase {

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The plugin manager for REST plugins.
   *
   * @var \Drupal\rest\Plugin\Type\ResourcePluginManager
   */
  protected $manager;

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
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\rest\Plugin\Type\ResourcePluginManager $manager
   *   The resource plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory holding resource settings.
   */
  public function __construct(ResourcePluginManager $manager, ConfigFactoryInterface $config, EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info_manager ) {
    $this->config = $config;
    $this->manager = $manager;
    $this->entityTypeManager = $entity_manager;
    $this->entityTypeBundleInfoManager = $entity_type_bundle_info_manager;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   * @return array
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Possible entity types: do
    // var_dump(array_keys($this->manager->getDefinitions())); and then drush cr
    // It will list all entity types supported by REST.
    // @todo -> iterate over possible types and only support the ContentEntity
    // derivatives.
    $excluded_types = array(
      'comment' => 'comment',
      'user' => 'user',
      'contact_message' => 'contact_message',
      'shortcut' => 'shortcut',
      'menu_link_content' => 'menu_link_content',
      'user' => 'user',
    );

    $entity_types = $this->getEntityTypes();
    //ResourcePluginManager $manager

    $resources = $this->manager->getDefinitions();
    // Filter out the ones we do not support.
    foreach ($excluded_types as $entity_type) {
      unset($resources['entity:' . $entity_type]);
    }

    /*// @todo do this smarter and narrow it down more
    foreach ($entity_types as $entity_type) {
      foreach ($resources as $resource) {
        if (in_array('entity:' . $entity_type, $resources)) {
          $accepted_resources['entity:' . $entity_type] = $resource;
        }
      }
    }*/

    // Iterate over all enabled resource plugins.
    foreach ($resources as $id => $enabled_methods) {
      $plugin = $this->manager->getInstance(array('id' => $id));

      /* @var \Symfony\Component\Routing\Route $route */
      foreach ($plugin->routes() as $name => $route) {
        // @todo: Are multiple methods possible here?
        $methods = $route->getMethods();

        // Only expose routes where the method is GET
        if ($methods[0] != "GET") {
          continue;
        }
        $route->setRequirement('_format', 'acquia_contenthub_cdf');
        $route->setRequirement('_access', 'TRUE');
        $collection->add("acquia_contenthub.content_hub_cdf.$name", $route);
      }
    }
  }

  /**
   * Obtains the list of entity types.
   */
  public function getEntityTypes() {
    $types = $this->entityTypeManager->getDefinitions();

    $entity_types = array();
    foreach ($types as $type => $entity) {
      // We only support content entity types at the moment, since config
      // entities don't implement \Drupal\Core\TypedData\ComplexDataInterface.
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
          $entity_types[$type][$type] = $entity->getLabel();
        }
      }
    }
    return $entity_types;
  }
}
