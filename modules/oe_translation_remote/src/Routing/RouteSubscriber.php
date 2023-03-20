<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Drupal\oe_translation_remote\Form\RemoteTranslationNewForm;
use Drupal\oe_translation_remote\Form\RemoteTranslationReviewForm;
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
    $this->addTranslationRequestReviewRoute($collection);
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
          '_form' => RemoteTranslationNewForm::class,
          '_title_callback' => '\Drupal\oe_translation_remote\Form\RemoteTranslationNewForm::title',
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
      $route_name = "entity.$entity_type_id.remote_translation";
      $collection->add($route_name, $route);
    }
  }

  /**
   * Adds the route to the form to review a remote translation request.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The collection.
   */
  protected function addTranslationRequestReviewRoute(RouteCollection $collection): void {
    $entity_type_id = 'oe_translation_request';
    $route = new Route('/translation-request/{oe_translation_request}/review/{language}');
    $operation = 'remote_translation_review';
    $route
      ->setDefaults([
        '_entity_form' => "{$entity_type_id}.{$operation}",
        '_title_callback' => RemoteTranslationReviewForm::class . '::reviewTranslationFormTitle',
      ])
       // The route to review a given language can be done by a user that can
       // accept or sync a translation.
      ->setRequirement('_permission', 'accept translation request+sync translation request')
      ->setRequirement('_custom_access', RemoteTranslationReviewForm::class . '::access')
      ->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        'language' => ['type' => 'entity:configurable_language'],
      ]);

    $route->setOption('_admin_route', TRUE);

    $collection->add("entity.{$entity_type_id}.remote_translation_review", $route);
  }

}
