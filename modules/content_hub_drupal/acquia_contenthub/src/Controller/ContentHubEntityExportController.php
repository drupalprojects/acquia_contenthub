<?php
/**
 * @file
 * Export Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Component\Serialization\Json;

/**
 * Controller for Content Hub Export Entities using bulk upload.
 */
class ContentHubEntityExportController extends ControllerBase {

  protected $format = 'acquia_contenthub_cdf';

  /**
   * The Basic HTTP Kernel to make requests.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $kernel;

  /**
   * Content Hub Client Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientManager
   */
  protected $clientManager;

  /**
   * Content Hub Subscription.
   *
   * @var \Drupal\acquia_contenthub\ContentHubSubscription
   */
  protected $contentHubSubscription;

  /**
   * Public Constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The HttpKernel.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubSubscription $contenthub_subscription
   *    The Content Hub Subscription.
   */
  public function __construct(HttpKernelInterface $kernel, ClientManagerInterface $client_manager, ContentHubSubscription $contenthub_subscription) {
    $this->kernel = $kernel;
    $this->clientManager = $client_manager;
    $this->contentHubSubscription = $contenthub_subscription;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel.basic'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_subscription')
    );
  }

  /**
   * Makes an internal HMAC-authenticated request to the site to obtain CDF.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The Entity ID.
   *
   * @return array
   *   The CDF array.
   */
  public function getEntityCdfByInternalRequest($entity_type, $entity_id) {
    global $base_path;
    try {
      $url = Url::fromRoute('acquia_contenthub.entity.' . $entity_type . '.GET.acquia_contenthub_cdf', [
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        $entity_type => $entity_id,
        '_format' => 'acquia_contenthub_cdf',
        'include_references' => 'true',
      ])->toString();
      $url = str_replace($base_path, '/', $url);

      // Creating an internal HMAC-signed request.
      $request = Request::create($url);
      $request->headers->set('Content-Type', 'application/json');
      $request->headers->set('Date', gmdate('D, d M Y H:i:s T'));
      $shared_secret = $this->contentHubSubscription->getSharedSecret();
      $signature = $this->clientManager->getRequestSignature($request, $shared_secret);
      $request->headers->set('Authorization', 'Acquia ContentHub:' . $signature);

      /** @var \Drupal\Core\Render\HtmlResponse $response */
      $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);
      $entity_cdf_json = $response->getContent();
      $bulk_cdf = Json::decode($entity_cdf_json);
    }
    catch (\Exception $e) {
      // Do nothing, route does not exist.
      $bulk_cdf = array();
    }
    return $bulk_cdf;
  }

  /**
   * Collects all Drupal Entities that needs to be sent to Hub.
   */
  public function getDrupalEntities() {
    $normalized = [
      'entities' => [],
    ];
    $entities = $_GET;
    foreach ($entities as $entity => $entity_ids) {
      $ids = explode(",", $entity_ids);
      foreach ($ids as $id) {
        try {
          $bulk_cdf = $this->getEntityCDFByInternalRequest($entity, $id);
          $bulk_cdf = array_pop($bulk_cdf);
          if (is_array($bulk_cdf)) {
            foreach ($bulk_cdf as $cdf) {
              $uuids = array_column($normalized['entities'], 'uuid');
              if (!in_array($cdf['uuid'], $uuids)) {
                $normalized['entities'][] = $cdf;
              }
            }
          }

        }
        catch (\Exception $e) {
          // Do nothing, route does not exist.
        }
      }
    }
    return JsonResponse::create($normalized);
  }

}
