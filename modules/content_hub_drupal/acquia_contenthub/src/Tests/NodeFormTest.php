<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Tests\NodeFormTest.
 */

namespace Drupal\acquia_contenthub\Tests;

/**
 * Test Acquia Content Hub node form.
 *
 * @group acquia_contenthub
 */
class NodeFormTest extends WebTestBase {

  use ContentHubEntityTrait;

  /**
   * The sample article we generate.
   *
   * @var \Drupal\node\NodeInterface $article
   */
  private $article;

  /**
   * Configure and use content hub preview image style.
   */
  public function testNodeForm() {
    $this->drupalLogin($this->adminUser);
    $this->article = $this->drupalCreateNode(array('type' => 'article'));

    // A normal node should not have Acquia Content Hub settings.
    $this->drupalGet('node/' . $this->article->id() . '/edit');
    $this->assertNoText(t('Acquia Content Hub settings'));

    // Convert the node into a Content Hub node.
    $this->convertToContentHubEntity($this->article);

    // A Content Hub node should have Acquia Content Hub settings.
    // Form should have option, and default to "enabled".
    $node_edit_url = 'node/' . $this->article->id() . '/edit';
    $this->drupalGet($node_edit_url);
    $this->assertText(t('Acquia Content Hub settings'));
    $this->assertFieldChecked('edit-acquia-contenthub-auto-update', 'Automatic updates is enabled by default');

    // Disable automatic update.
    $edit = [];
    $edit['acquia_contenthub[auto_update]'] = FALSE;
    $this->drupalPostForm($node_edit_url, $edit, t('Save'));

    // Form should have option, and default to "enabled".
    $this->drupalGet($node_edit_url);
    $this->assertNoFieldChecked('edit-acquia-contenthub-auto-update', 'Automatic updates is enabled by default');
  }

}
