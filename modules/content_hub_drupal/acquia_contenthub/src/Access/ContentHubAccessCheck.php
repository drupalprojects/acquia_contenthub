<?php
/**
 * @file
 * Implements a Content Hub Access Check based on Http HMAC Spec V1.
 *
 * https://github.com/acquia/http-hmac-spec/tree/1.0
 */

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;

class ContentHubAccessCheck implements AccessInterface {

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

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
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\acquia_contenthub\Client\ClientManagerInterface $client_manager
   *    The client manager.
   * @param \Drupal\acquia_contenthub\ContentHubSubscription $contenthub_subscription
   *    The Content Hub Subscription.
   */
  public function __construct(LoggerChannelFactory $logger_factory, ClientManagerInterface $client_manager, ContentHubSubscription $contenthub_subscription) {
    $this->loggerFactory = $logger_factory;
    $this->clientManager = $client_manager;
    $this->contentHubSubscription = $contenthub_subscription;
  }


  /**
   * HTTP HMAC Access Check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return bool
   *   TRUE if granted access, FALSE otherwise.
   */
  public function access(Route $route, Request $request, AccountInterface $account) {
    // Check permissions and combine that with any custom access checking needed. Pass forward
    // parameters from the route and/or request as needed.

    if ($account->hasPermission(('Administer Acquia Content Hub'))) {
      // If this is a logged user with 'Administer Acquia Content Hub'
      // permission then grant access.
      return TRUE;
    }
    else {
      // If this user has no permission, then validate Signature request.
//      $request = Request::createFromGlobals();
      $headers = array_map('current', $request->headers->all());
      $authorization_header = isset($headers['authorization']) ? $headers['authorization'] : '';

      $shared_secret = $this->contentHubSubscription->getSharedSecret();
      $signature = $this->clientManager->getRequestSignature($request, $shared_secret);
      $authorization = 'Acquia ContentHub:' . $signature;

      return (bool) ($authorization === $authorization_header);
    }

    return FALSE;
  }

}