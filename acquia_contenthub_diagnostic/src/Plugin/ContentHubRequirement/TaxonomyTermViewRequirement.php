<?php

namespace Drupal\acquia_contenthub_diagnostic\Plugin\ContentHubRequirement;

use Drupal\acquia_contenthub_diagnostic\ContentHubRequirementBase;
use Drupal\views\Entity\View;

/**
 * Defines a taxonomy term view requirement.
 *
 * @ContentHubRequirement(
 *   id = "taxonomy_term_view",
 *   title = @Translation("Taxonomy term view"),
 * )
 */
class TaxonomyTermViewRequirement extends ContentHubRequirementBase {

  /**
   * {@inheritdoc}
   */
  public function verify() {
    /** @var \Drupal\views\Entity\View|null $view */
    $view = View::load('taxonomy_term');
    if (!($view && $view->status())) {
      return REQUIREMENT_OK;
    }

    $this->setValue($this->t('Enabled'));
    $this->setDescription($this->t('The Taxonomy term (<code>taxonomy_term</code>) view must be disabled or deleted.'));
    return REQUIREMENT_ERROR;
  }

}
