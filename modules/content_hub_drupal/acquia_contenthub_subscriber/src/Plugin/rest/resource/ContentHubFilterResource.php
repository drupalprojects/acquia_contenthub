<?php
/**
 * @file
 * Plugin REST Resource for ContentHubFilter.
 */

namespace Drupal\acquia_contenthub_subscriber\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Psr\Log\LoggerInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\acquia_contenthub_subscriber\Entity\ContentHubFilter;

/**
 * Provides a resource to perform CRUD operations on Content Hub Filters.
 *
 * @RestResource(
 *   id = "Content Hub Filter",
 *   label = @Translation("Content Hub Filter"),
 *   uri_paths = {
 *     "canonical" = "/acquia_contenthub/contenthub_filter/{contenthub_filter}",
 *     "http://drupal.org/link-relations/create" = "/acquia_contenthub/contenthub_filter"
 *   }
 * )
 */
class ContentHubFilterResource extends ResourceBase {

  /**
   *  A curent user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   *  A instance of entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityManagerInterface $entity_manager, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityManager = $entity_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Validates input from user.
   *
   * @param object $contenthub_filter
   *   The Content Hub Filter object.
   */
  public function validate($contenthub_filter) {
    $messages = array();
    if (isset($contenthub_filter->uuid) && !Uuid::isValid($contenthub_filter->uuid)) {
      $messages[] = t('The filter has an invalid "uuid" field.');
    }
    if (!isset($contenthub_filter->id)) {
      $messages[] = t('The filter has an invalid "id" field.');
    }
    else {
      if (preg_match("/^[a-zA-Z0-9_]*$/", $contenthub_filter->id, $matches) !== 0) {
        $messages[] = t('The "id" field has to be a "machine_name" (Only small letters, numbers and underscore allowed).');
      }
      // @TODO: Check that the ID is unique making a query to the database.
    }
    if (!isset($contenthub_filter->name)) {
      $messages[] = t('The filter has to have a "name" field.');
    }


    if (count($messages) > 0) {
      $message = implode("\n", $messages);
      throw new HttpException(422, $message);
    }
  }

  /*
   * Responds to GET requests.
   *
   * Returns a list of filters.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing a list of filters.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($contenthub_filter = NULL) {
    $permission = 'Administer Acquia Content Hub';
    if(!$this->currentUser->hasPermission($permission)) {
      throw new AccessDeniedHttpException();
    }

    $entities = NULL;
    if (!empty($contenthub_filter) && $contenthub_filter !== 'all') {
      $entities = array();
      $entities[] = $contenthub_filter;
    }
    $filters = $this->entityManager->getStorage('contenthub_filter')->loadMultiple($entities);

    if (!empty($filters)) {
      $filters = count($filters) > 1 ? $filters : reset($filters);
      return new ResourceResponse($filters);
    }
    elseif ($contenthub_filter == 'all') {
      return new ResourceResponse(array());
    }

    throw new NotFoundHttpException(t('No Content Hub Filters were found'));

  }

  public function post($contenthub_filter = NULL) {
    $permission = 'Administer Acquia Content Hub';
    if(!$this->currentUser->hasPermission($permission)) {
      throw new AccessDeniedHttpException();
    }

    if ($contenthub_filter == NULL) {
      throw new BadRequestHttpException('No Content Hub Filter content received.');
    }

    // Verify that it is a Content Hub Filter Entity.
    $this->validate($contenthub_filter);

    // Validation has passed, now try to save the entity.
    // $entity = new ContentHubFilter($contenthub_filter, 'contenthub_filter');

    return new ResourceResponse($contenthub_filter);

  }

}