<?php

/**
 * @file
 * Contains \Drupal\acquia_contenthub\Normalizer\ContentEntityViewModesExtractor.
 */

namespace Drupal\acquia_contenthub\Normalizer;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcher;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Extracts the rendered view modes from a given ContentEntity Object.
 */
class ContentEntityViewModesExtractor implements ContentEntityViewModesExtractorInterface {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   * The current_user service used by this plugin.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The entity config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $entityConfig;

  /**
   * The Basic HTTP Kernel to make requests.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $kernel;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcher
   */
  protected $accountSwitcher;

  /**
   * Constructs a ContentEntityViewModesExtractor object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current session user.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The renderer.
   * @param \Drupal\Core\Session\AccountSwitcher $account_switcher
   *   The Account Switcher Service.
   */
  public function __construct(AccountProxyInterface $current_user, ConfigFactory $config_factory, EntityDisplayRepository $entity_display_repository, EntityTypeManager $entity_type_manager, Renderer $renderer, HttpKernelInterface $kernel, AccountSwitcher $account_switcher) {
    $this->currentUser = $current_user;
    $this->entityConfig = $config_factory->get('acquia_contenthub.entity_config');
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->kernel = $kernel;
    $this->accountSwitcher = $account_switcher;
  }

  /**
   * Checks whether the given class is supported for normalization.
   *
   * @param mixed $data
   *   Data to normalize.
   *
   * @return bool
   *   TRUE if is child of supported class.
   */
  private function isChildOfSupportedClass($data) {
    // If we aren't dealing with an object that is not supported return
    // now.
    if (!is_object($data)) {
      return FALSE;
    }
    $supported = (array) $this->supportedInterfaceOrClass;

    return (bool) array_filter($supported, function($name) use ($data) {
      return $data instanceof $name;
    });
  }

  /**
   * Renders all the view modes that are configured to be rendered.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $object
   *   The Content Entity object.
   *
   * @return array|null
   *   The normalized array.
   */
  public function getRenderedViewModes(ContentEntityInterface $object) {
    $normalized = [];

    // Exit if the class does not support normalizing to the given format.
    if (!$this->isChildOfSupportedClass($object)) {
      return NULL;
    }

    // Exit if the object is configured not to be rendered.
    $entity_type_id = $object->getEntityTypeId();
    $entity_bundle_id = $object->bundle();
    $config = $this->entityConfig->get('entities.' . $entity_type_id . '.' . $entity_bundle_id);
    if (!isset($config['enable_viewmodes']) && !isset($config['rendering'])) {
      return NULL;
    }
    // Normalize.
    $view_modes = $this->entityDisplayRepository->getViewModes($entity_type_id);

    foreach ($view_modes as $view_mode_id => $view_mode) {
      if (!in_array($view_mode_id, $config['rendering'])) {
        continue;
      }
      // Generate our URL where the isolated rendered view mode lives.
      // This is the best way to really make sure the content in Content Hub
      // and the content shown to any user is 100% the same.
      $url = Url::fromRoute('acquia_contenthub.content_entity_display.entity', [
        'entity_type' => $object->getEntityTypeId(),
        'entity_id' => $object->id(),
        'view_mode_name' => $view_mode_id,
      ])->toString();
      $request = Request::create($url);

      /** @var \Drupal\Core\Render\HtmlResponse $response */
      $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);

      $normalized[$view_mode_id] = [
        'id' => $view_mode_id,
        'label' => $view_mode['label'],
        'url' => $url,
        'html' => $response->getContent(),
      ];
    }

    return $normalized;
  }

  /**
   * Renders all the view modes that are configured to be rendered.
   *
   * In this method we also switch to an anonymous user because we only want
   * to see what the Anonymous user's see.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $object
   *   The Content Entity Object.
   * @param string $view_mode
   *   The request view mode identifier.
   *
   * @see https://www.drupal.org/node/218104
   *
   * @return array
   *   The render array for the complete page, as minimal as possible.
   */
  public function getViewModeMinimalHtml(ContentEntityInterface $object, $view_mode) {
    // Switch to anonymous user for rendering as configured role.
    $this->accountSwitcher->switchTo(new \Drupal\Core\Session\AnonymousUserSession());
    $build = $this->entityTypeManager->getViewBuilder($object->getEntityTypeId())
      ->view($object, $view_mode);
    // Add our cacheableDependency. If this config changes, clear the render
    // cache.
    $this->renderer->addCacheableDependency($build, $this->entityConfig);
    // Wrap our view mode in the most minimal HTML possible.
    $html = $this->getMinimalHtml($build);
    // Restore user account.
    $this->accountSwitcher->switchBack();
    return $html;
  }

  /**
   * Renders a given render array in minimal HTML.
   *
   * Minimal HTML is in this case defined as:
   * - valid HTML
   * - <head> only containing CSS and JS
   * - <body> only containing the passed in content plus footer JS
   * - i.e. no meta tags, no title, no theme CSS …
   *
   *
   * Renders a HTML response with a hardcoded HTML template (i.e. no theme
   * involved), optimized for the purposes of Content Hub, with only the
   * absolutely minimal HTML required.
   *
   * Only $body still goes through the theme system, because it is rendered
   * using Render API, which itself calls the theme system, and hence uses the
   * active theme.
   *
   * @param array $body
   *   A render array.
   *
   * @return array
   *   The render array for the complete page, as minimal as possible.
   */
  protected function getMinimalHtml(array $body) {
    // Attachments to render the CSS, header JS and footer JS.
    // @see \Drupal\Core\Render\HtmlResponseSubscriber
    $html_attachments = [];
    $types = [
      'styles' => 'css',
      'scripts' => 'js',
      'scripts_bottom' => 'js-bottom',
    ];
    $placeholder_token = Crypt::randomBytesBase64(55);
    foreach ($types as $type => $placeholder_name) {
      $placeholder = '<' . $placeholder_name . '-placeholder token="' . $placeholder_token . '">';
      $html_attachments['html_response_attachment_placeholders'][$type] = $placeholder;
    }

    // Hardcoded equivalent of core/modules/system/templates/html.html.twig.
    $html_top = <<<HTML
<!DOCTYPE html>
<html>
  <head>
    <css-placeholder token="$placeholder_token">
    <js-placeholder token="$placeholder_token">
  </head>
  <body>
HTML;
    $html_bottom = <<<HTML
    <js-bottom-placeholder token="$placeholder_token">
  </body>
</html>
HTML;

    // Render array representing the entire HTML to be rendered.
    $html = [
      '#prefix' => Markup::create($html_top),
      'body' => $body,
      '#suffix' => Markup::create($html_bottom),
      '#attached' => $html_attachments,
    ];

    // Render the render array.
    $this->renderer->renderRoot($html);

    return $html;
  }

}
