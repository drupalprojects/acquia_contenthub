<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\IntegrationTest.
 */

namespace Drupal\content_hub_connector\Tests;
use Drupal\node\NodeInterface;

/**
 * Tests the overall functionality of the Content Hub Connector module.
 *
 * @group content_hub_connector
 */
class IntegrationTest extends WebTestBase {

  /**
   * @var \Drupal\node\NodeInterface $article
   *
   * The sample article we generate
   */
  protected $article;

  /**
   * @var \Drupal\node\NodeInterface $article
   *
   * The sample page we generate
   */
  protected $page;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests various operations via the Content Hub's Connector admin UI.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);

    $this->createSampleContent();

    $this->configureContentHubContentTypes('node', array('article'));
    $this->checkCdfOutput($this->article);

    $this->enableViewModeFor('node', 'article', 'teaser');
    $this->checkCdfOutput($this->article, 'teaser');
  }

  /**
   * Create some basic sample content so that we can later verify if the CDF
   */
  public function createSampleContent() {
    // Add two articles and a page.
    $this->article = $this->drupalCreateNode(array('type' => 'article'));
    $this->page = $this->drupalCreateNode(array('type' => 'page'));
  }

  /**
   * @param string $entity_type
   * @param array $bundles
   */
  public function configureContentHubContentTypes($entity_type, array $bundles) {
    $this->drupalGet('admin/config/services/content-hub/configuration');
    $this->assertResponse(200);

    $edit = array();
    foreach ($bundles as $bundle) {
      $edit['entities[' . $entity_type . '][' . $bundle . '][enabled]'] = TRUE;
    }

    $this->drupalPostForm(NULL, $edit, $this->t('Save configuration'));
    $this->assertResponse(200);

    $this->drupalGet('admin/config/services/content-hub/configuration');
    $this->assertResponse(200);

  }

  /**
   * @param \Drupal\node\NodeInterface $entity
   * @param string $bundle
   * @param string|null $view_mode
   */
  public function checkCdfOutput(NodeInterface $entity, $view_mode = NULL) {
    $this->drupalGet($entity->getEntityTypeId() . '/' . $this->article->id(), array('query' => array('_format' => 'content_hub_cdf')));
    $this->assertResponse(200);
  }

  /**
   * @param string $entity_type
   * @param string $bundle
   * @param string $view_mode
   */
  public function enableViewModeFor($entity_type, $bundle, $view_mode) {
    $this->drupalGet('admin/config/services/content-hub/configuration');
    $this->assertResponse(200);

    $edit = array(
      'entities[' . $entity_type . '][' . $bundle . '][rendering][]' => array($view_mode),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save configuration'));
    $this->assertResponse(200);

    $this->drupalGet('admin/config/services/content-hub/configuration');
    $this->assertResponse(200);
  }
}
