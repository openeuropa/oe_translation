<?php

declare(strict_types = 1);

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
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getCollectionRoute($entity_type)) {
      $route->setRequirement('_permission', 'access translation request overview');
      return $route;
    }
  }

  public function getRoutes(EntityTypeInterface $entity_type) {
    $routes = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();
    $route = new Route('/translation-request/{oe_translation_request}/translate-locally');
    $operation = 'local_translation';
    $route
      ->setDefaults([
        '_entity_form' => "{$entity_type_id}.{$operation}",
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::editTitle',
      ])
      ->setRequirement('_entity_access', "{$entity_type_id}.update")
      ->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ]);

    // Entity types with serial IDs can specify this in their route
    // requirements, improving the matching process.
    if ($this->getEntityTypeIdKeyType($entity_type) === 'integer') {
      $route->setRequirement($entity_type_id, '\d+');
    }
    $route->setOption('_admin_route', TRUE);

    $routes->add("entity.{$entity_type_id}.local_translation", $route);
    return $routes;
  }
}
