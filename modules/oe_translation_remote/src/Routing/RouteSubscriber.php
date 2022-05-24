<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines remote translations routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The translation providers service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translatorProviders;

  /**
   * {@inheritdoc}
   */
  public function __construct(TranslatorProvidersInterface $translatorProviders) {
    $this->translatorProviders = $translatorProviders;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -211];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $definitions = $this->translatorProviders->getDefinitions();
    foreach ($definitions as $entity_type_id => $definition) {
      if (!$this->translatorProviders->hasRemote($definition)) {
        continue;
      }
      $base_route = $collection->get("entity.$entity_type_id.content_translation_overview");
      if (!$base_route) {
        continue;
      }
      $path = $base_route->getPath() . '/remote';
      $route = new Route($path,
        [
          '_controller' => '\Drupal\oe_translation_remote\Controller\TranslationRemoteController::overview',
          'entity_type_id' => $entity_type_id,
        ],
        [
          // @todo To update correct permission.
          '_entity_access' => $entity_type_id . '.update',
        ],
        [
          'parameters' => [
            $entity_type_id => [
              'type' => 'entity:' . $entity_type_id,
            ],
          ],
          '_admin_route' => TRUE,
        ]
      );
      $route_name = "entity.$entity_type_id.remote_translation";
      $collection->add($route_name, $route);
    }
    return $collection;
  }

}
