<?php
/**
 * @file
 * Implements a Content Hub Access Check based on Http HMAC Spec V1.
 *
 * Https://github.com/acquia/http-hmac-spec/tree/1.0
 */

namespace Drupal\acquia_contenthub\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\acquia_contenthub\Client\ClientManagerInterface;
use Drupal\acquia_contenthub\ContentHubSubscription;

/**
 * Implements permission to prevent unauthorized access to Entity CDF.
 */
class ContentHubAccess implements AccessInterface {

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
   * Checks access to Entity CDF.
   *
   * Only grants access to logged in users with 'Administer Acquia Content Hub'
   * permission or if the request verifies its HMAC signature.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return bool
   *   TRUE if granted access, FALSE otherwise.
   */
  public function access(Route $route, Request $request, AccountInterface $account) {
    // Check permissions and combine that with any custom access checking
    // needed. Pass forward parameters from the route and/or request as needed.
    if ($account->hasPermission(('administer acquia content hub'))) {
      // If this is a logged in user with 'Administer Acquia Content Hub'
      // permission then grant access.
      return AccessResult::allowed();
    }
    else {
      if (empty($this->clientManager->isConnected())) {
        return AccessResult::forbidden('Acquia Content Hub Client not connected.');
      }
      // If this user has no permission, then validate Request Signature.
      $headers = array_map('current', $request->headers->all());
      $authorization_header = isset($headers['authorization']) ? $headers['authorization'] : '';
      $shared_secret = $this->contentHubSubscription->getSharedSecret();
      $signature = $this->clientManager->getRequestSignature($request, $shared_secret);
      $authorization = 'Acquia ContentHub:' . $signature;

      // Only allow access if the Signature validates.
      return AccessResult::allowedIf($authorization === $authorization_header);
    }
  }

}
