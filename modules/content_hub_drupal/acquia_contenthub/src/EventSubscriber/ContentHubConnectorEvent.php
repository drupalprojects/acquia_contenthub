<?php

/**
 * @file
 * Acquia Content Hub Event Subscriber.
 */

namespace Drupal\acquia_contenthub\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Acquia Content Hub Event Subscriber.
 */
class ContentHubEvent implements EventSubscriberInterface {

  /**
   * Public Constructor.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The Event Response.
   */
  public function addAccessAllowOriginHeaders(FilterResponseEvent $event) {
    $response = $event->getResponse();
    $request_method = \Drupal::request()->server->get('REQUEST_METHOD');
    $access_request_method = \Drupal::request()->server->get('HTTP_ACCESS_CONTROL_REQUEST_METHOD');
    $access_request_headers = \Drupal::request()->server->get('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
    $config = \Drupal::config('acquia_contenthub.admin_settings');
    $response->headers->set('Access-Control-Allow-Origin', $config->get('ember_app'));
    $response->headers->set('Access-Control-Allow-Credentials', TRUE);

    if ($request_method == 'OPTIONS') {
      $response->headers->set('Access-Control-Allow-Methods', $access_request_method);
      $response->headers->set('Access-Control-Allow-Headers', $access_request_headers);
      if (isset($access_request_method)) {
        if ($access_request_method == 'GET' || $access_request_method == 'POST') {
          $response->headers->set('Access-Control-Allow-Origin', '*');
          $response->headers->set('Access-Control-Allow-Headers', $access_request_headers);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = array('addAccessAllowOriginHeaders');
    return $events;
  }

}
