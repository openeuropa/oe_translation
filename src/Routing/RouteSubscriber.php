<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Wraps translation routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Run later than \Drupal\tmgmt_content\Routing\TmgmtContentRouteSubscriber.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection as $route) {
      // Swap the content translation overview controller.
      if ($route->getDefault('_controller') == '\Drupal\tmgmt_content\Controller\ContentTranslationControllerOverride::overview') {
        $route->setDefault('_controller', '\Drupal\oe_translation\Controller\ContentTranslationController::overview');
      }

      // Swap the TMGMT content preview controller.
      if ($route->getDefault('_controller') == '\Drupal\tmgmt_content\Controller\ContentTranslationPreviewController::preview') {
        $route->setDefault('_controller', '\Drupal\oe_translation\Controller\ContentPreviewController::preview');
        $route->setDefault('_title_callback', '\Drupal\oe_translation\Controller\ContentPreviewController::title');
      }
    }
  }

}
