<?php

/**
 * @file
 * Acquia Content Hub Default Rule Action.
 */

namespace Drupal\acquia_contenthub\Plugin\RulesAction;

use Drupal\rules\Core\RulesActionBase;

/**
 * Provides a 'custom action' action.
 *
 * @RulesAction(
 *   id = "acquia_contenthub_action_webhook_landing",
 *   label = @Translation("Receiving an Entity in a webhook from the Content Hub"),
 *   category = @Translation("Content Hub"),
 *   context = {
 *     "webhook" = @ContextDefinition("webhook",
 *       label = @Translation("Content Hub webhook")
 *     ),
 *     "handler" = @ContextDefinition("list",
 *       label = @Translation("Handler")
 *     )
 *   }
 * )
 */
class ContentHubEntityAction extends RulesActionBase {

}
