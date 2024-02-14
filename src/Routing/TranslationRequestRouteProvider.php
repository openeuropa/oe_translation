<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides Translation Request routes.
 */
class TranslationRequestRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $routes = parent::getRoutes($entity_type);

    $route = new Route($entity_type->getLinkTemplate('preview'));
    $route
      ->addDefaults([
        '_controller' => '\Drupal\oe_translation\Controller\ContentTranslationPreviewController::previewRequest',
        '_title_callback' => '\Drupal\oe_translation\Controller\ContentTranslationPreviewController::previewRequestTitle',
      ])
      ->setRequirement('_permission', 'translate any entity')
      ->setOption('parameters', [
        'oe_translation_request' => ['type' => 'entity:oe_translation_request'],
      ]);

    $route_name = 'entity.oe_translation_request.preview';
    $routes->add($route_name, $route);

    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getCollectionRoute($entity_type)) {
      $route->setRequirement('_permission', 'access translation request overview');
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    // Make the canonical route an admin one.
    $route->setOption('_admin_route', TRUE);
    return $route;
  }

}
