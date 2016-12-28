<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Tests\IntegrationTest.
 */

namespace Drupal\acquia_contenthub\Tests;
use Drupal\node\NodeInterface;

/**
 * Tests the overall functionality of the Acquia Content Hub module.
 *
 * @group acquia_contenthub
 */
class IntegrationTest extends WebTestBase {

  /**
   * The sample article we generate.
   *
   * @var \Drupal\node\NodeInterface $article
   */
  protected $article;

  /**
   * The sample page we generate.
   *
   * @var \Drupal\node\NodeInterface $article
   */
  protected $page;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests various operations via the Acquia Content Hub admin UI.
   */
  public function testFramework() {
    // Enable dumpHeaders when you are having caching issues.
    $this->dumpHeaders = TRUE;
    $this->drupalLogin($this->adminUser);

    // Create sample content.
    $this->createSampleContent();

    // Configure Acquia Content Hub for article nodes with view modes.
    $this->configureContentHubContentTypes('node', array('article'));
    $this->checkCdfOutput($this->article);

    // Enable view-modes for article nodes.
    $this->enableViewModeFor('node', 'article', 'teaser');
    $this->checkCdfOutput($this->article, 'teaser');

    $this->ConfigureAndUsePreviewImageStyle();
  }

  /**
   * Create some basic sample content so that we can later verify if the CDF.
   */
  public function createSampleContent() {
    // Add one article and a page.
    $this->article = $this->drupalCreateNode(array('type' => 'article'));
    $this->page = $this->drupalCreateNode(array('type' => 'page'));
  }

  /**
   * Configures Content types to be exported to Content Hub.
   *
   * @param string $entity_type
   *   The entity type the bundles belong to.
   * @param array $bundles
   *   The bundles to enable.
   */
  public function configureContentHubContentTypes($entity_type, array $bundles) {
    $this->drupalGet('admin/config/services/acquia-contenthub/configuration');
    $this->assertResponse(200);

    $edit = array();
    foreach ($bundles as $bundle) {
      $edit['entities[' . $entity_type . '][' . $bundle . '][enable_index]'] = 1;
    }

    $this->drupalPostForm(NULL, $edit, $this->t('Save configuration'));
    $this->assertResponse(200);

    $this->drupalGet('admin/config/services/acquia-contenthub/configuration');
    $this->assertResponse(200);
  }

  /**
   * Ensures the CDF output is what we expect it to be.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity to be used.
   * @param string|null $view_mode
   *   The view mode to check in the CDF.
   */
  public function checkCdfOutput(NodeInterface $entity, $view_mode = NULL) {
    $output = $this->drupalGetJSON($entity->getEntityTypeId() . '/' . $this->article->id(), array('query' => array('_format' => 'acquia_contenthub_cdf')));
    $this->assertResponse(200);
    if (!empty($view_mode)) {
      $this->assertTrue(isset($output['entities']['0']['metadata']), 'Metadata is present');
      $this->assertTrue(isset($output['entities']['0']['metadata']['view_modes'][$view_mode]), t('View mode %view_mode is present', array('%view_mode' => $view_mode)));
    }
    else {
      $this->assertFalse(isset($output['entities']['0']['metadata']), 'Metadata is not present');
    }
  }

  /**
   * Enables a view mode to be rendered in CDF.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $view_mode
   *   The view mode to enable.
   */
  public function enableViewModeFor($entity_type, $bundle, $view_mode) {
    $this->drupalGet('admin/config/services/acquia-contenthub/configuration');
    $this->assertResponse(200);

    $edit = array(
      'entities[' . $entity_type . '][' . $bundle . '][enable_viewmodes]' => TRUE,
      'entities[' . $entity_type . '][' . $bundle . '][rendering][]' => array($view_mode),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save configuration'));
    $this->assertResponse(200);

    $this->drupalGet('admin/config/services/acquia-contenthub/configuration');
    $this->assertResponse(200);
  }

  /**
   * Configure and use content hub preview image style.
   */
  public function configureAndUsePreviewImageStyle() {
    $this->drupalGet('admin/config/services/acquia-contenthub/configuration');
    $this->assertRaw('admin/structure/types/manage/article#edit-acquia-contenthub', 'Preview image shortcut links exist on the page.');

    $this->drupalGet('admin/structure/types/manage/article');
    $this->assertText(t('Acquia Content Hub'));
  }

}
