<?php
/**
 * @file
 * Plugin REST Resource for ContentHubFilter.
 */

namespace Drupal\acquia_contenthub_subscriber\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface;

/**
 * Provides a resource to perform CRUD operations on Content Hub Filters.
 *
 * @RestResource(
 *   id = "contenthub_filter",
 *   label = @Translation("Content Hub Filter"),
 *   serialization_class = "Drupal\acquia_contenthub_subscriber\Entity\ContentHubFilter",
 *   uri_paths = {
 *     "canonical" = "/acquia_contenthub/contenthub_filter/{contenthub_filter}",
 *     "http://drupal.org/link-relations/create" = "/acquia_contenthub/contenthub_filter"
 *   }
 * )
 */
class ContentHubFilterResource extends ResourceBase {

  /**
   * A curent user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A instance of entity manager.
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
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface|NULL $contenthub_filter
   *   The Content Hub Filter entity.
   * @param bool $is_new
   *   Validate taken into consideration it is a new entity or an existent one.
   */
  public function validate(ContentHubFilterInterface $contenthub_filter, $is_new = TRUE) {
    $messages = array();
    if (!empty($contenthub_filter->uuid())) {
      if (!Uuid::isValid($contenthub_filter->uuid())) {
        $messages[] = t('The filter has an invalid "uuid" field.');
      }
    }

    if (empty($contenthub_filter->id())) {
      $messages[] = t('The filter has an invalid "id" field.');
    }
    else {
      if (preg_match("/^[a-zA-Z0-9_]*$/", $contenthub_filter->id(), $matches) !== 1) {
        $messages[] = t('The "id" field has to be a "machine_name" (Only small letters, numbers and underscore allowed).');
      }
      // @TODO: Check that the ID is unique making a query to the database.
    }
    if (!isset($contenthub_filter->name)) {
      $messages[] = t('The filter has to have a "name" field.');
    }

    if (!isset($contenthub_filter->author) || $contenthub_filter->author == 0) {
      $messages[] = t('You are trying to create a new filter without a valid session.');
    }

    // @TODO: Validate other fields.

    if (count($messages) > 0) {
      $message = implode("\n", $messages);
      throw new HttpException(422, $message);
    }
  }

  /**
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
    if (!$this->currentUser->hasPermission($permission)) {
      throw new AccessDeniedHttpException();
    }

    $entities = NULL;
    if (!empty($contenthub_filter) && $contenthub_filter !== 'all') {
      $entities = array();
      $entities[] = $contenthub_filter;
    }
    $filters = $this->entityManager->getStorage('contenthub_filter')->loadMultiple($entities);

    if (!empty($filters)) {
      if (count($filters) > 1) {
        return new ResourceResponse(array_values($filters));
      }
      else {
        return new ResourceResponse(reset($filters));
      }
    }
    elseif ($contenthub_filter == 'all') {
      return new ResourceResponse(array());
    }

    throw new NotFoundHttpException(t('No Content Hub Filters were found'));

  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface|NULL $contenthub_filter
   *   The Content Hub Filter.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The Content Hub Filter after it has been saved.
   */
  public function post(ContentHubFilterInterface $contenthub_filter = NULL) {
    $permission = 'Administer Acquia Content Hub';
    if (!$this->currentUser->hasPermission($permission)) {
      throw new AccessDeniedHttpException();
    }

    if ($contenthub_filter == NULL) {
      throw new BadRequestHttpException('No Content Hub Filter content received.');
    }

    // This Filter is owned by the user who created it.
    if (empty($contenthub_filter->author)) {
      $user = \Drupal::currentUser();
      $uid = $user->id();
      $contenthub_filter->author = $uid ?: 1;
    }

    // Verify that we have valid Content Hub Filter Entity.
    $this->validate($contenthub_filter, TRUE);

    // We are ONLY creating new entities through POST requests.
    if (!$contenthub_filter->isNew()) {
      $message = t('Only new entities can be created. Filter "!name" already exists (id = "!id", uuid = "!uuid").', array(
        '!id' => $contenthub_filter->id(),
        '!name' => $contenthub_filter->name,
        '!uuid' => $contenthub_filter->uuid(),
      ));
      throw new BadRequestHttpException($message);
    }

    // Validation has passed, now try to save the entity.
    try {
      $contenthub_filter->save();
      $this->logger->notice('Created entity %type with ID %id.', array('%type' => $contenthub_filter->getEntityTypeId(), '%id' => $contenthub_filter->id()));
      return new ResourceResponse($contenthub_filter);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

  /**
   * Responds to PATCH requests.
   *
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface $original_contenthub_filter
   *   The original Content Hub Filter entity.
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubFilterInterface|NULL $contenthub_filter
   *   The Content Hub Filter entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The Content Hub Filter after it has been saved.
   */
  public function patch(ContentHubFilterInterface $original_contenthub_filter, ContentHubFilterInterface $contenthub_filter = NULL) {
    $permission = 'Administer Acquia Content Hub';
    if (!$this->currentUser->hasPermission($permission)) {
      throw new AccessDeniedHttpException();
    }

    if ($contenthub_filter == NULL) {
      throw new BadRequestHttpException('No Content Hub Filter content received.');
    }

    // Verify that we have valid and existent Content Hub Filter Entity.
    $this->validate($contenthub_filter, FALSE);

    // Save changes.
    // Return.
    return new ResourceResponse($contenthub_filter);

  }

}
