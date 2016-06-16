<?php

/**
 * @file
 */

namespace Drupal\content_hub_subscriber\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\Component\Utility\Unicode;

class ContentHubSubscriberEvent implements EventSubscriberInterface {
  public function addAccessAllowOriginHeaders(FilterResponseEvent $event) {
    $response = $event->getResponse();
    $request_method = \Drupal::request()->server->get('REQUEST_METHOD');
    $access_request_method = \Drupal::request()->server->get('HTTP_ACCESS_CONTROL_REQUEST_METHOD');
    $access_request_headers = \Drupal::request()->server->get('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
    $config = \Drupal::config('content_hub_connector.admin_settings');
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
