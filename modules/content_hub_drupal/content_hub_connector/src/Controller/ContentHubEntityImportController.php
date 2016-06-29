<?php
/**
 * @file
 * Import Entity Controller.
 */

namespace Drupal\content_hub_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\content_hub_connector\EntityManager as EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Acquia\ContentHubClient\Entity as ChEntity;

class ContentHubEntityImportController extends ControllerBase {


  protected $format = 'content_hub_cdf';

  protected $entity_manager;

  protected $serializer;

  const VALID_UUID = '[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}';


  public function __construct(EntityManager $entity_manager, SerializerInterface $serializer) {
    $this->entity_manager = $entity_manager;
    $this->serializer = $serializer;
  }

  /**
   * Implements the static interface create method.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_hub_connector.entity_manager'),
      $container->get('serializer')
    );
  }

  /**
   * Validates the UUID.
   *
   * @param string $uuid
   *   A UUID String.
   *
   * @return bool
   *   TRUE if the string given is a UUID, FALSE otherwise.
   */
  static public function isUuid($uuid) {
    return (bool) preg_match('/^' . self::VALID_UUID . '$/', $uuid);
  }

  public function saveDrupalEntity($uuid) {

    // Checking that the parameter given is a UUID.
    if (!self::isUuid($uuid)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    $ch_entity = $this->entity_manager->loadRemoteEntity($uuid);

    // Import the entity.
    $entity_type = $ch_entity->getType();
    $entity_manager = \Drupal::entityTypeManager();
    $class = \Drupal::entityTypeManager()->getDefinition($entity_type)->getClass();

    try {
      $entity = $this->serializer->deserialize($ch_entity->json(), $class, $this->format);
    }
    catch (UnexpectedValueException $e) {
      $error['error'] = $e->getMessage();
      $content = $this->serializer->serialize($error, 'json');
      return new Response($content, 400, array('Content-Type' => 'json'));
    }

    // Saving the Entity.
    $entity->save();
    $serialized_entity = $this->serializer->normalize($entity, 'json');

    return new JsonResponse($serialized_entity);

  }

}