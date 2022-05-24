<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Wraps translation routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The content translation provider service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translationProviders;

  /**
   * Constructs a ContentTranslationRouteSubscriber object.
   *
   * @param \Drupal\oe_translation\TranslatorProvidersInterface $translator_providers
   *   The content translation provider service.
   */
  public function __construct(TranslatorProvidersInterface $translator_providers) {
    $this->translationProviders = $translator_providers;
  }

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
    $entity_types = $this->translationProviders->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      // Change the access requirements on the Drupal core routes.
      if ($entity_type->hasLinkTemplate('drupal:content-translation-add')) {
        $route_name = "entity.$entity_type_id.content_translation_add";
        $route = $collection->get($route_name);
        $route->setRequirement('_access_oe_translation', 'create');
        $collection->add($route_name, $route);
      }

      if ($entity_type->hasLinkTemplate('drupal:content-translation-edit')) {
        $route_name = "entity.$entity_type_id.content_translation_edit";
        $route = $collection->get($route_name);
        $route->setRequirement('_access_oe_translation', 'update');
        $collection->add($route_name, $route);
      }

      if ($entity_type->hasLinkTemplate('drupal:content-translation-delete')) {
        $route_name = "entity.$entity_type_id.content_translation_delete";
        $route = $collection->get($route_name);
        $route->setRequirement('_access_oe_translation', 'delete');
        $collection->add($route_name, $route);
      }

      // Change the controller on the main overview page.
      if ($entity_type->hasLinkTemplate('drupal:content-translation-overview')) {
        $route_name = "entity.$entity_type_id.content_translation_overview";
        $route = $collection->get($route_name);
        $route->setDefault('_controller', '\Drupal\oe_translation\Controller\ContentTranslationDashboardController::overview');
        $route->setDefault('_title_callback', '\Drupal\oe_translation\Controller\ContentTranslationDashboardController::title');
        $collection->add($route_name, $route);
      }
    }
  }

}
