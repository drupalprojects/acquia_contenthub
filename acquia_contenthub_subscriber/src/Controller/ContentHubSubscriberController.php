<?php

namespace Drupal\acquia_contenthub_subscriber\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Content Hub Discovery page.
 */
class ContentHubSubscriberController extends ControllerBase {

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Csrf Token Generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfTokenGenerator;

  /**
   * ContentHubSubscriberController constructor.
   *
   * @param \Drupal\core\Config\ConfigFactoryInterface $config_factory
   *   The client manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator
   *   The csrf token generator.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, CsrfTokenGenerator $csrf_token_generator) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->csrfTokenGenerator = $csrf_token_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    /** @var LanguageManagerInterface $language_manager */
    $language_manager = $container->get('language_manager');
    /** @var CsrfTokenGenerator $csrf_token_generator */
    $csrf_token_generator = $container->get('csrf_token');

    return new static(
      $config_factory,
      $language_manager,
      $csrf_token_generator
    );
  }

  /**
   * Loads the content hub discovery page from an ember app.
   */
  public function loadDiscovery() {
    // Get the session token.
    $token = $this->csrfTokenGenerator->get('rest');

    // Get the cookie.
    $request = Request::createFromGlobals();
    $cookie_header = session_name() . '=' . current($request->cookies->all());

    $config = $this->configFactory->get('acquia_contenthub.admin_settings');
    $ember_endpoint = $config->get('ember_app') ?: $GLOBALS['base_url'] . '/' . drupal_get_path('module', 'acquia_contenthub_subscriber') . '/ember/index.html';

    // Set Client User Agent.
    $module_info = system_get_info('module', 'acquia_contenthub');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';
    $client_user_agent = 'AcquiaContentHub/' . $drupal_version . '-' . $module_version;

    $import_endpoint = $config->get('import_endpoint') ? $config->get('import_endpoint') : $GLOBALS['base_url'] . '/acquia-contenthub/';
    $saved_filters_endpoint = $config->get('saved_filters_endpoint') ? $config->get('saved_filters_endpoint') : $GLOBALS['base_url'] . '/acquia_contenthub/contenthub_filter/';

    $languages_supported = array_keys($this->languageManager->getLanguages(LanguageInterface::STATE_ALL));
    // We move default language to the top of the array.
    // Refer: CHMS-994.
    $default_language_id = $this->languageManager->getDefaultLanguage()->getId();
    $i = array_search($default_language_id, $languages_supported);
    unset($languages_supported[$i]);
    array_unshift($languages_supported, $default_language_id);

    $form = [];
    $form['#attached']['library'][] = 'acquia_contenthub_subscriber/acquia_contenthub_subscriber';
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['host'] = $config->get('hostname');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['public_key'] = $config->get('api_key');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['secret_key'] = $config->get('secret_key');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['client'] = $config->get('origin');
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['ember_app'] = $ember_endpoint;
    $form['#attached']['drupalSettings']['acquia_contenthub_subscriber']['source'] = $config->get('drupal8');
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['client_user_agent'] = $client_user_agent;
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['import_endpoint'] = $import_endpoint;
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['saved_filters_endpoint'] = $saved_filters_endpoint;
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['token'] = $token;
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['cookie'] = $cookie_header;
    $form["#attached"]['drupalSettings']['acquia_contenthub_subscriber']['languages_supported_by_subscriber'] = $languages_supported;

    if (empty($config->get('origin'))) {
      drupal_set_message($this->t('Acquia Content Hub must be configured to view any content. Please contact your administrator.'), 'warning');
    }
    // Only load iframe when ember_endpoint is set.
    elseif (!$ember_endpoint) {
      drupal_set_message($this->t('Please configure your ember application by setting up config variable ember_app.'), 'warning');
    }
    else {
      $form['iframe'] = [
        '#type' => 'markup',
        '#markup' => Markup::create('<iframe id="acquia-contenthub-ember" src=' . $ember_endpoint . ' width="100%" height="1000px" style="border:0"></iframe>'),
      ];
    }

    return $form;
  }

}
