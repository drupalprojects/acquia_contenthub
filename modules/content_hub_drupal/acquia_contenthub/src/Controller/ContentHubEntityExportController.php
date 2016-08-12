<?php
/**
 * @file
 * Export Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * Public Constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The HttpKernel.
   */
  public function __construct(HttpKernelInterface $kernel) {
    $this->kernel = $kernel;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel.basic')
    );
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
