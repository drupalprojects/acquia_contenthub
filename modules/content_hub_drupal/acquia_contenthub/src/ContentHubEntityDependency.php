<?php
/**
 * @file
 * Manages Content Hub Entity Dependencies.
 */

namespace Drupal\acquia_contenthub;

use Drupal\Component\Uuid\Uuid;
use Acquia\ContentHubClient\Attribute;

/**
 * Content Hub Dependency Class.
 */
class ContentHubEntityDependency {

  /**
   * Parent is required for dependent to exist.
   *
   * @var int.
   */
  const RELATIONSHIP_DEPENDENT = 1;

  /**
   * Dependent is independent of parent.
   *
   * @var int.
   */
  const RELATIONSHIP_INDEPENDENT = 2;

  /**
   * The parent ContentHubEntity.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntityDependency
   */
  protected $parent;

  /**
   * The CDF Entity.
   *
   * @var \Acquia\ContentHubClient\Entity
   */
  protected $cdf;

  /**
   * A tracker of all dependencies.
   *
   * @var array
   */
  protected $dependencyChain = array();

  /**
   * The relationship type between parent and dependent.
   *
   * @var int.
   */
  protected $dependencyType;

  /**
   * Public constructor.
   *
   * @param \Acquia\ContentHubClient\Entity $cdf
   *   The Entity CDF.
   *
   * @throws \Exception
   */
  public function __construct(\Acquia\ContentHubClient\Entity $cdf) {
    $this->cdf = $cdf;
    if (in_array($this->cdf->getType(), self::getPostDependencyEntityTypes())) {
      $this->setRelationship(self::RELATIONSHIP_DEPENDENT);
    }
    else {
      $this->setRelationship(self::RELATIONSHIP_INDEPENDENT);
    }
  }

  /**
   * Obtains the Entity's UUID.
   *
   * @return string
   *   The UUID.
   */
  public function getUuid() {
    return $this->cdf->getUuid();
  }

  /**
   * Gets the list of "Entity-dependent" entity types.
   */
  static public function getPostDependencyEntityTypes() {
    // By default "field collections" and "paragraphs" are post-dependencies.
    $post_dependencies = array(
      'field_collection_item' => 'field_collection_item',
      'paragraphs_item' => 'paragraphs_item',
    );
    return $post_dependencies;
  }

  /**
   * Checks whether the current entity is dependent on another one or not.
   *
   * If it is entity dependent, then it needs a parent or host entity.
   *
   * @return bool
   *   TRUE if it is Entity Dependent, FALSE otherwise.
   */
  public function isEntityDependent() {
    return in_array($this->cdf->getType(), $this->getPostDependencyEntityTypes());
  }

  /**
   * Tracks dependencies as a flat chain to combat dependency loops.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $content_hub_entity
   *   An entity to add to the chain.
   *
   * @return $this
   *   The position of the entity in the chain or FALSE.
   */
  public function appendDependencyChain(ContentHubEntityDependency $content_hub_entity) {
    if (!in_array($content_hub_entity->getUuid(), $this->dependencyChain)) {
      $this->dependencyChain[] = $content_hub_entity->getUuid();
    }
    return $this;
  }

  /**
   * Identifies if a dependency exists in the chain.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $content_hub_entity
   *   An entity to check against the chain.
   *
   * @return bool
   *   TRUE if the entity is in the chain, otherwise false.
   */
  public function isInDependencyChain(ContentHubEntityDependency $content_hub_entity) {
    return in_array($content_hub_entity->getUuid(), $this->getDependencyChain());
  }

  /**
   * Returns the dependency chain for the current entity.
   *
   * @return array
   *   The dependency chain.
   */
  public function getDependencyChain() {
    return $this->dependencyChain;
  }

  /**
   * Sets the relationship flag.
   *
   * @param int $type
   *   The Relationship type.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntityDependency
   *   This object.
   *
   * @throws \Exception
   */
  public function setRelationship($type = self::RELATIONSHIP_INDEPENDENT) {
    switch ($type) {
      case self::RELATIONSHIP_INDEPENDENT:
      case self::RELATIONSHIP_DEPENDENT:
        $this->dependencyType = $type;
        break;

      default:
        throw new \Exception("Unknown relationship: $type.");
    }
    return $this;
  }

  /**
   * Obtains the relationship flag.
   */
  public function getRelationship() {
    return $this->dependencyType;
  }

  /**
   * Sets the parent of the dependency.
   *
   * @param \Drupal\acquia_contenthub\ContentHubEntityDependency $parent
   *   The parent ContentHubEntity.
   *
   * @return $this
   *   This Content Hub Entity.
   */
  public function setParent(ContentHubEntityDependency $parent) {
    $this->parent = $parent;
    $this->parent->appendDependencyChain($this);
    return $this;
  }

  /**
   * Returns the Parent Entity.
   *
   * @return \Drupal\acquia_contenthub\ContentHubEntityDependency
   *   The ContentHubEntity parent object.
   */
  public function getParent() {
    return $this->parent;
  }

  /**
   * Obtains a Raw Remote Content Hub Entity.
   *
   * @return \Acquia\ContentHubClient\Entity|bool
   *   Returns a ContentHubClient\Entity, FALSE otherwise.
   */
  public function getRawEntity() {
    return !empty($this->cdf) ? $this->cdf : FALSE;
  }

  /**
   * Returns the Content Hub Entity Type.
   *
   * @return bool|string
   *   The Entity Type or FALSE.
   */
  public function getEntityType() {
    return !empty($this->cdf) ? $this->cdf->getType() : FALSE;
  }

  /**
   * Obtains remote dependencies for this particular entity.
   *
   * @return array
   *   An array or UUIDs
   */
  public function getRemoteDependencies() {
    $dependencies = array();
    // Finding assets (files) dependencies.
    foreach ($this->cdf->getAssets() as $asset) {
      preg_match('#\[(.*)\]#', $asset['replace-token'], $match);
      $uuid = $match[1];
      if (Uuid::isValid($uuid)) {
        // It is a valid UUID => Then it should refer to an entity.
        $dependencies[] = $uuid;
      }
    }

    // Adding this exclude some attributes, which we don't want to take into
    // consideration the dependency information contained on them.
    $excluded_attributes = $this->getExcludedAttributesFromDependencies();

    // Finding attributes (entity references) dependencies.
    foreach ($this->cdf->getAttributes() as $name => $attribute) {
      if (!in_array($name, $excluded_attributes)) {
        $type = $attribute['type'];
        if ($type == Attribute::TYPE_REFERENCE) {
          // Obtaining values for every language.
          $languages = array_keys($attribute['value']);
          foreach ($languages as $lang) {
            $dependencies[] = $attribute['value'][$lang];
          }
        }
        elseif ($type == Attribute::TYPE_ARRAY_REFERENCE) {
          // Obtaining values for every language.
          $languages = array_keys($attribute['value']);
          foreach ($languages as $lang) {
            $dependencies = array_merge($dependencies, $attribute['value'][$lang]);
          }
        }
      }
    }
    return $dependencies;
  }

  /**
   * Excludes attributes from providing dependency information.
   *
   * Provides a list of attributes in which we do not want to take into
   * consideration the dependency information contained on them.
   *
   * @return array
   *   The array of attributes to exclude.
   */
  protected function getExcludedAttributesFromDependencies() {
    return array(
      'author',
      'parent',
      'comments',
      'host_entity',
    );
  }

}
