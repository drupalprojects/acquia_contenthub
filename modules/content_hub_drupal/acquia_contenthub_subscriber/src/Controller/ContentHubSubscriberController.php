<?php
/**
 * @file
 * Contains \Drupal\acquia_contenthub_subscriber\Controller\ContentHubSubscriberController.
 */

namespace Drupal\acquia_contenthub_subscriber\Controller;

use Drupal\Core\Controller\ControllerBase;
use \Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for Content Hub Discovery page.
 */
class ContentHubSubscriberController extends ControllerBase {
  /**
   * Callback for `acquia-contenthub-api/post.json` API method.
   */
  public function postExample(Request $request) {
    // This condition checks the `Content-type` and makes sure to
    // decode JSON string from the request body into array.
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
      $data = json_decode($request->getContent(), TRUE);
      $request->request->replace(is_array($data) ? $data : []);
    }

    $node = Node::create([
      'type'        => 'article',
      'title'       => $data['title'],
    ]);
    $node->save();
    $response['message'] = "Article created with title - " . $data['title'];
    $response['method'] = 'POST';

    return new JsonResponse($response);
  }

  /**
   * Loads the content hub discovery page from an ember app.
   */
  public function loadDiscovery() {
    $config = \Drupal::config('acquia_contenthub.admin_settings');
    $ember_endpoint = $config->get('ember_app') ?: $GLOBALS['base_url'] . '/' . drupal_get_path('module', 'acquia_contenthub_subscriber') . '/ember';

    // Set Client User Agent.
    $module_info = system_get_info('module', 'acquia_contenthub');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';
    $client_user_agent = 'AcquiaContentHub/' . $drupal_version . '-' . $module_version;

    $form = array();
    $form['#attached']['library'][] = 'acquia_contenthub_subscriber/acquia_contenthub_subscriber';
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['host'] = $config->get('hostname');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['public_key'] = $config->get('api_key');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['secret_key'] = $config->get('secret_key');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['client'] = $config->get('origin');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['ember_app'] = $ember_endpoint;
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['source'] = $config->get('drupal8');
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['client_user_agent'] = $client_user_agent;

    if (empty($config->get('origin'))) {
      drupal_set_message(t('Acquia Content Hub must be configured to view any content. Please contact your administrator.'), 'warning');
    }
    // Only load iframe when ember_endpoint is set.
    elseif (!$ember_endpoint) {
      drupal_set_message(t('Please configure your ember application by setting up config variable ember_app.'), 'warning');
    }
    else {
      $form['iframe'] = array(
        '#type' => 'markup',
        '#markup' => \Drupal\Core\Render\Markup::create('<iframe id="acquia-contenthub-ember" src=' . $ember_endpoint . ' width="100%" height="1000px" style="border:0"></iframe>'),
      );
    }

    return $form;
  }

}
