<?php

declare(strict_types=1);

namespace Drupal\oe_translation_local\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Drupal\oe_translation_local\Controller\TranslationLocalController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines local translations routes.
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
  #[\ReturnTypeWillChange]
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -211];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $this->addTranslationRequestLocalTranslateRoute($collection);

    // Add the routes to the local translation overview for each entity type.
    $definitions = $this->translatorProviders->getDefinitions();
    foreach ($definitions as $entity_type_id => $definition) {
      if (!$this->translatorProviders->hasLocal($definition)) {
        continue;
      }
      $base_route = $collection->get("entity.$entity_type_id.content_translation_overview");
      if (!$base_route) {
        continue;
      }
      $path = $base_route->getPath() . '/local';
      $route = new Route($path,
        [
          '_controller' => '\Drupal\oe_translation_local\Controller\TranslationLocalController::overview',
          '_title_callback' => '\Drupal\oe_translation_local\Controller\TranslationLocalController::title',
          'entity_type_id' => $entity_type_id,
        ],
        [
          '_permission' => 'translate any entity',
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
      $route_name = "entity.$entity_type_id.local_translation";
      $collection->add($route_name, $route);
    }

    return $collection;
  }

  /**
   * Adds the route to the form to translate a translation request.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The collection.
   */
  protected function addTranslationRequestLocalTranslateRoute(RouteCollection $collection): void {
    $entity_type_id = 'oe_translation_request';
    $route = new Route('/translation-request/{oe_translation_request}/translate-locally');
    $operation = 'local_translation';
    $route
      ->setDefaults([
        '_entity_form' => "{$entity_type_id}.{$operation}",
        '_title_callback' => TranslationLocalController::class . '::translateLocalFormTitle',
      ])
      ->setRequirement('_permission', 'translate any entity')
      ->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ]);

    $route->setOption('_admin_route', TRUE);

    $collection->add("entity.{$entity_type_id}.local_translation", $route);
  }

}
