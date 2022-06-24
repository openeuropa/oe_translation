<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Controller for the content translation dashboard.
 */
class ContentTranslationDashboardController extends ControllerBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new ContentTranslationDashboardController.
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
   * Renders the content translation dashboard page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param null|string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   The overview page.
   */
  public function overview(RouteMatchInterface $route_match, string $entity_type_id = NULL): array {
    $build = [];

    $cache = new CacheableMetadata();

    $build['existing_translations'] = [
      '#weight' => 100,
    ];
    $element = &$build['existing_translations'];
    $element['title'] = [
      '#type' => 'inline_template',
      '#template' => "<h3>{{ 'Existing translations for the latest default revision' }}</h3>",
    ];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    $cache->addCacheableDependency($entity);

    $translation_languages = $entity->getTranslationLanguages();

    $header = [
      $this->t('Language'),
      $this->t('Title'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($translation_languages as $language) {
      $translation = $entity->getTranslation($language->getId());
      $row = [
        'data' => [
          'language' => $language->getName(),
          'title' => [
            'data' => [
              '#type' => 'link',
              '#title' => $translation->label(),
              '#url' => $translation->toUrl(),
            ],
          ],
          'operations' => [
            'data' => $this->getTranslationOperationLinks($translation),
          ],
        ],
      ];
      if ($translation->isDefaultTranslation()) {
        $row['class'][] = 'color-success';
      }

      $row['hreflang'] = $language->getId();

      $rows[] = $row;
    }

    $element['table'] = [
      '#theme' => 'table',
      '#attributes' => [
        'class' => ['existing-translations-table'],
      ],
      '#header' => $header,
      '#rows' => $rows,
    ];

    $cache->applyTo($build);

    // Dispatch an event to allow other modules to contribute to the dashboard.
    $entity_type_id = $entity_type_id ?? '';
    $event = new ContentTranslationDashboardAlterEvent($build, $route_match, $entity_type_id);
    $this->eventDispatcher->dispatch(ContentTranslationDashboardAlterEvent::NAME, $event);

    return $event->getBuild();
  }

  /**
   * Title callback for the overview page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param null|string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   The title.
   */
  public function title(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    return [
      '#markup' => $this->t('Translation dashboard for @entity', ['@entity' => $entity->label()]),
    ];
  }

  /**
   * Builds the operations for a given existing entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The entity translation.
   *
   * @return array
   *   The operations.
   */
  protected function getTranslationOperationLinks(ContentEntityInterface $translation): array {
    if ($translation->isDefaultTranslation()) {
      // We don't want operations links on the original language.
      return [];
    }

    $operations = $this->entityTypeManager->getListBuilder($translation->getEntityTypeId())->getOperations($translation);
    if (isset($operations['edit'])) {
      // We do not ever want to edit the entity in the translation language.
      unset($operations['edit']);
    }

    $links = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    if (isset($links['#links']['translate'])) {
      unset($links['#links']['translate']);
    }

    return $links;
  }

}
