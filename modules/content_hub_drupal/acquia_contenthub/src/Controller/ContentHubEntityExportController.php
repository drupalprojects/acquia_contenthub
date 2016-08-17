<?php
/**
 * @file
 * Export Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Component\Serialization\Json;
use Acquia\Hmac\RequestSigner;
use Acquia\Hmac\Digest;
use Acquia\Hmac\Request\Symfony;

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
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * Public Constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The HttpKernel.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(HttpKernelInterface $kernel, ConfigFactory $config_factory) {
    $this->kernel = $kernel;
    $this->config = $config_factory->get('acquia_contenthub.admin_settings');
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel.basic'),
      $container->get('config.factory')
    );
  }

  /**
   * Makes an internal HMAC-authenticated request to the site to obtain CDF.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The Entity ID.
   * @return mixed
   */
  public function getEntityCDFByInternalRequest($entity_type, $entity_id) {
    global $base_path;
    $url = Url::fromRoute('acquia_contenthub.entity.' . $entity_type . '.GET.acquia_contenthub_cdf', [
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      $entity_type => $entity_id,
      '_format' => 'acquia_contenthub_cdf',
      'include_references' => 'true',
    ])->toString();
    $url = str_replace($base_path, '/', $url);

    // Transfer current headers into the internal request.
    $curr_request = Request::createFromGlobals();
    $request = Request::create($url);

    // Transfer headers as they came from the original request.
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Date', $curr_request->headers->get('Date'));

    $requestSigner = new RequestSigner(new Digest\Version1('sha256'));
    $shared_secret = $this->config->get('shared_secret');

    $hmacrequest = new Symfony($request);
    $signature = $requestSigner->signRequest($hmacrequest, $shared_secret);
    $request->headers->set('Authorization', 'Acquia ContentHub:' . $signature);

    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    $entity_cdf_json = $response->getContent();
    $bulk_cdf = Json::decode($entity_cdf_json);
    return $bulk_cdf;
  }

  /**
   * Collects all Drupal Entities that needs to be sent to Hub.
   */
  public function getDrupalEntities() {
    global $base_path;
    $normalized = [
      'entities' => [],
    ];
    $entities = $_GET;
    foreach ($entities as $entity => $entity_ids) {
      $ids = explode(",", $entity_ids);
      foreach ($ids as $id) {
        try {
          $url = Url::fromRoute('acquia_contenthub.entity.' . $entity . '.GET.acquia_contenthub_cdf', [
            'entity_type' => $entity,
            'entity_id' => $id,
            $entity => $id,
            '_format' => 'acquia_contenthub_cdf',
            'include_references' => 'true',
          ])->toString();
          $url = str_replace($base_path, '/', $url);
          $request = Request::create($url);
          /** @var \Drupal\Core\Render\HtmlResponse $response */
          $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);
          $entity_cdf_json = $response->getContent();
          $bulk_cdf = Json::decode($entity_cdf_json);

          $something = $this->getEntityCDFByInternalRequest($entity, $id);

//          $bulk_cdf = array_pop($bulk_cdf);
          $bulk_cdf = array_pop($something);
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
