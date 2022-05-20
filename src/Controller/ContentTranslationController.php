<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Overrides the core content translation controller.
 *
 * Allows translator plugins to make alterations.
 */
class ContentTranslationController extends ControllerBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new ContentTranslationController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * Renders the content translation overview page.
   *
   * This will default to the dashboard.
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = [
      '#markup' => $this->t('The translation dashboard to come.'),
    ];

    $entity_type_id = $entity_type_id ?? '';
    $event = new ContentTranslationOverviewAlterEvent($build, $route_match, $entity_type_id);
    $this->eventDispatcher->dispatch(ContentTranslationOverviewAlterEvent::NAME, $event);

    return $event->getBuild();
  }

}
