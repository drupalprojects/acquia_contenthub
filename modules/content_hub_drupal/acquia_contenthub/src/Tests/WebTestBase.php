<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Tests\WebTestBase.
 */

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
  public static $modules = array('node', 'acquia_contenthub');

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
  protected $adminUserPermissions = array(
    'administer acquia content hub',
    'access administration pages',
  );

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
    $this->unauthorizedUser = $this->drupalCreateUser(array('access administration pages'));
    $this->anonymousUser = $this->drupalCreateUser();

    // Get the URL generator.
    $this->urlGenerator = $this->container->get('url_generator');

    // Create a node article type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create a node page type.
    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));
  }

}
