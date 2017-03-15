<?php

namespace Drupal\acquia_contenthub\Tests;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simpletest\WebTestBase as SimpletestWebTestBase;

/**
 * Provides the base class for web tests for Search API.
 */
abstract class WebTestBase extends SimpletestWebTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = ['node', 'acquia_contenthub'];

  /**
   * An admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * The permissions of the admin user.
   *
   * @var string[]
   */
  protected $adminUserPermissions = [
    'bypass node access',
    'administer acquia content hub',
    'administer content types',
    'access administration pages',
  ];

  /**
   * A user without Acquia Content Hub admin permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * The anonymous user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $anonymousUser;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the users used for the tests.
    $this->adminUser = $this->drupalCreateUser($this->adminUserPermissions);
    $this->unauthorizedUser = $this->drupalCreateUser(['access administration pages']);
    $this->anonymousUser = $this->drupalCreateUser();

    // Get the URL generator.
    $this->urlGenerator = $this->container->get('url_generator');

    // Create a node article type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create a node page type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
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

}
