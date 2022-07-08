<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow\Routing;

use Drupal\oe_translation_corporate_workflow\Controller\TranslationLocalController;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribes to the route event.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.oe_translation_request.local_translation');
    if ($route) {
      $defaults = $route->getDefaults();
      $defaults['_title_callback'] = TranslationLocalController::class . '::translateLocalFormTitle';
      $route->setDefaults($defaults);
    }

    return $collection;
  }

}
