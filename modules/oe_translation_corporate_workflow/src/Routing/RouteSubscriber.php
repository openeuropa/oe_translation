<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Drupal\oe_translation_corporate_workflow\Controller\TranslationLocalController;
use Drupal\oe_translation_corporate_workflow\Form\NodeRevisionTranslationDeleteForm;
use Drupal\oe_translation_corporate_workflow\Form\RemoteTranslationNewForm;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribes to the route event.
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

    foreach ($this->translatorProviders->getDefinitions() as $entity_type_id => $definition) {
      $route = $collection->get("entity.$entity_type_id.remote_translation");
      if ($route) {
        $defaults = $route->getDefaults();
        $defaults['_form'] = RemoteTranslationNewForm::class;
        $route->setDefaults($defaults);
      }
    }

    // Switch out the node revision delete confirm route.
    $route = $collection->get('node.revision_delete_confirm');
    if ($route) {
      $defaults = $route->getDefaults();
      $defaults['_form'] = NodeRevisionTranslationDeleteForm::class;
      $route->setDefaults($defaults);
    }

    return $collection;
  }

}
