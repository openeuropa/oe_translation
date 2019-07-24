<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Routing;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Wraps translation routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * Constructs a ContentTranslationRouteSubscriber object.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   */
  public function __construct(ContentTranslationManagerInterface $content_translation_manager) {
    $this->contentTranslationManager = $content_translation_manager;
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

      // Alter the routes to provide a custom access check to the regular
      // Drupal translation management routes.
      foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
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
      }
    }
  }

}
