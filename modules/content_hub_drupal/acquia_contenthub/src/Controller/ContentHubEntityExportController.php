<?php
/**
 * @file
 * Export Entity Controller.
 */

namespace Drupal\acquia_contenthub\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acquia_contenthub\EntityManager as EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\acquia_contenthub\ContentHubImportedEntities;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Component\Uuid\Uuid;
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
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The Content Hub Entity Manager.
   *
   * @var \Drupal\acquia_contenthub\EntityManager
   */
  protected $entityManager;

  /**
   * The Serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The Content Hub Imported Entities.
   *
   * @var \Drupal\acquia_contenthub\ContentHubImportedEntities
   */
  protected $contentHubImportedEntities;

  /**
   * The Basic HTTP Kernel to make requests.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $kernel;

  /**
   * Public Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The Logger Factory.
   * @param \Drupal\acquia_contenthub\EntityManager $entity_manager
   *   The Acquia Content Hub Entity Manager.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The Serializer.
   * @param \Drupal\acquia_contenthub\ContentHubImportedEntities $acquia_contenthub_imported_entities
   *   The Content Hub Imported Entities Service.
   */
  public function __construct(LoggerChannelFactory $logger_factory, EntityManager $entity_manager, SerializerInterface $serializer, ContentHubImportedEntities $acquia_contenthub_imported_entities, HttpKernelInterface $kernel) {
    $this->loggerFactory = $logger_factory;
    $this->entityManager = $entity_manager;
    $this->serializer = $serializer;
    $this->contentHubImportedEntities = $acquia_contenthub_imported_entities;
    $this->kernel = $kernel;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('acquia_contenthub.entity_manager'),
      $container->get('serializer'),
      $container->get('acquia_contenthub.acquia_contenthub_imported_entities'),
      $container->get('http_kernel.basic')
    );
  }

  /**
   * Collects all Drupal Entities that needs to be sent to Hub.
   */
  public function getDrupalEntities() {
    global $base_path;
    $normalized = [
      'entities' => []
    ];
    $entities = $_GET;
    foreach($entities as $entity => $entity_ids) {
      $ids = explode(",", $entity_ids);
      foreach($ids as $id) {
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

        } catch (\Exception $e) {
          // do nothing, route does not exist.
        }
      }
    }
    return JsonResponse::create($normalized);
  }

}
