<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Routing;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_translation\Controller\JobItemAbortController;
use Symfony\Component\Routing\Route;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ContentTranslationRouteSubscriber object.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ContentTranslationManagerInterface $content_translation_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->contentTranslationManager = $content_translation_manager;
    $this->entityTypeManager = $entity_type_manager;
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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
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

      // Swap the TMGMT Job Item abort form handler to a controller.
      $route = $collection->get('entity.tmgmt_job_item.abort_form');
      if ($route instanceof Route) {
        $route->setDefaults(
          [
            '_controller' => JobItemAbortController::class . '::form',
          ]
        );
      }

      // Alter the routes to provide a custom access check to the regular
      // Drupal translation management routes.
      foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
        /** @var \Drupal\oe_translation\OeTranslationHandler $handler */
        $handler = $this->entityTypeManager->getHandler($entity_type_id, 'oe_translation');
        $supported_translators = $handler->getSupportedTranslators();
        if (empty($supported_translators)) {
          // If there are no supported translators for this entity type, we
          // do not want to prevent access and allow Drupal core translation
          // to be possible.
          continue;
        }

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

      // Deny access to any user to the sources and cart route.
      $names = ['tmgmt.source_overview_default', 'tmgmt.cart'];
      foreach ($names as $name) {
        $route = $collection->get($name);
        if ($route) {
          $route->setRequirement('_access', 'FALSE');
        }
      }
    }
  }

}
