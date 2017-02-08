<?php
/**
 * @file
 * Export Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Component\Serialization\Json;
use Drupal\acquia_contenthub\ContentHubEntitiesTracking;

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
   * Content Hub Entities Tracking.
   *
   * @var \Drupal\acquia_contenthub\ContentHubEntitiesTracking
   */
  protected $contentHubEntitiesTracking;

  /**
   * Entity Repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Public Constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The HttpKernel.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *   The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubSubscription $contenthub_subscription
   *   The Content Hub Subscription.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   Entity Repository.
   */
  public function __construct(HttpKernelInterface $kernel, ClientManagerInterface $client_manager, ContentHubSubscription $contenthub_subscription, ContentHubEntitiesTracking $contenthub_entities_tracking, EntityRepositoryInterface $entity_repository) {
    $this->kernel = $kernel;
    $this->clientManager = $client_manager;
    $this->contentHubSubscription = $contenthub_subscription;
    $this->contentHubEntitiesTracking = $contenthub_entities_tracking;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel.basic'),
      $container->get('acquia_contenthub.client_manager'),
      $container->get('acquia_contenthub.acquia_contenthub_subscription'),
      $container->get('acquia_contenthub.acquia_contenthub_entities_tracking'),
      $container->get('entity.repository')
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
      $request = $this->contentHubSubscription->hmacWrapper($request, TRUE);

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
    $request_from_contenthub = $this->isRequestFromAcquiaContentHub();
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
                if ($request_from_contenthub) {
                  $this->trackExportedEntity($cdf, TRUE);
                }
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

  /**
   * Resolves whether the current request comes from Acquia Content Hub or not.
   *
   * @return bool
   *   TRUE if request comes from Content Hub, FALSE otherwise.
   */
  public function isRequestFromAcquiaContentHub() {
    $request = Request::createFromGlobals();

    // This function already sits behind an access check to confirm that the
    // request for CDF came from Content Hub, but just in case that access is
    // opened to authenticated users or during development, we are using a
    // condition to prevent false tracking of entities as exported.
    $headers = array_map('current', $request->headers->all());
    if (isset($headers['user-agent']) && strpos($headers['user-agent'], 'Go-http-client') !== FALSE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Save this entity in the Tracking table.
   *
   * @param array $cdf
   *   The entity that has to be tracked as exported entity.
   * @param bool $set_exported
   *   Set the export status to exported in the tracking table.
   */
  public function trackExportedEntity($cdf, $set_exported = FALSE) {
    if ($exported_entity = $this->contentHubEntitiesTracking->loadExportedByUuid($cdf['uuid'])) {
      $exported_entity->setModified($cdf['modified']);
    }
    else {
      // Add a new tracking record with exported status set, and
      // imported status empty.
      $entity = $this->entityRepository->loadEntityByUuid($cdf['type'], $cdf['uuid']);
      $this->contentHubEntitiesTracking->setExportedEntity(
        $cdf['type'],
        $entity->id(),
        $cdf['uuid'],
        $cdf['modified'],
        $this->contentHubEntitiesTracking->getSiteOrigin()
      );
    }

    if ($set_exported) {
      $exported_entity->setExported();
    }

    // Now save the entity.
    $this->contentHubEntitiesTracking->save();
  }

}
