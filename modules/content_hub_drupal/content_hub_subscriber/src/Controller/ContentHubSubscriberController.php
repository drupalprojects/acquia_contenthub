<?php
/**
 * @file
 * Contains \Drupal\TODO
 */

namespace Drupal\content_hub_subscriber\Controller;

use Drupal\Core\Controller\ControllerBase;
use \Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ContentHubSubscriberController extends ControllerBase {
  /**
   * Callback for `content-hub-api/post.json` API method.
   */
  public function post_example( Request $request ) {

    // This condition checks the `Content-type` and makes sure to
    // decode JSON string from the request body into array.
    if ( 0 === strpos( $request->headers->get( 'Content-Type' ), 'application/json' ) ) {
      $data = json_decode( $request->getContent(), TRUE );
      $request->request->replace( is_array( $data ) ? $data : [] );
    }

    $node = Node::create([
      'type'        => 'article',
      'title'       => $data['title']
    ]);
    $node->save();
    $response['message'] = "Article created with title - " . $data['title'];
    $response['method'] = 'POST';

    return new JsonResponse( $response );
  }

/**
* Loads the content hub discovery page from an ember app.
*/
  public function content_hub_subscriber_discovery() {
    $config = \Drupal::config('content_hub_connector.admin_settings');
    $ember_endpoint = $config->get('ember_app') . '/entity';

    // Set Client User Agent.
    $module_info = system_get_info('module', 'content_hub_connector');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';
    $client_user_agent = 'AcquiaContentHubConnector/' . $drupal_version . '-' . $module_version;

    $form = array();
    $form['#attached']['library'][] = 'content_hub_subscriber/content_hub_subscriber';
    $form['#attached']['drupalSettings']['content_hub_subscriber']['host'] = $config->get('hostname');
    $form['#attached']['drupalSettings']['content_hub_subscriber']['public_key'] = $config->get('api_key');
    $form['#attached']['drupalSettings']['content_hub_subscriber']['secret_key'] = $config->get('secret_key');
    $form['#attached']['drupalSettings']['content_hub_subscriber']['client'] = $config->get('origin');
    $form['#attached']['drupalSettings']['content_hub_subscriber']['ember_app'] = $ember_endpoint;
    $form['#attached']['drupalSettings']['content_hub_subscriber']['source'] = $config->get('drupal8');
    $form["#attached"]['drupalSettings']['content_hub_subscriber']['client_user_agent'] = $client_user_agent;

    $form['iframe'] = array(
      '#type' => 'markup',
      '#markup' => $this->t('<iframe id="content-hub-ember" src=' . $ember_endpoint . ' width="100%" height="1000px" style="border:0"></iframe>'),
    );

    return $form;
  }
}
