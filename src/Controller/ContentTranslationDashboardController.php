<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Controller for the content translation dashboard.
 */
class ContentTranslationDashboardController extends ContentTranslationController {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The translator providers service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translatorProviders;

  /**
   * Constructs a new ContentTranslationDashboardController.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   A content translation manager instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\oe_translation\TranslatorProvidersInterface $translatorProviders
   *   The translator providers service.
   */
  public function __construct(ContentTranslationManagerInterface $manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, LanguageManagerInterface $language_manager, TranslatorProvidersInterface $translatorProviders) {
    parent::__construct($manager, $entity_field_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->languageManager = $language_manager;
    $this->translatorProviders = $translatorProviders;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('language_manager'),
      $container->get('oe_translation.translator_providers')
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
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);

    if (!$this->translatorProviders->hasLocal($entity->getEntityType())) {
      // If the current entity doesn't have local translations enabled (but
      // uses the core system instead), we don't want to alter the list. We only
      // allow others to alter the build.
      $build = parent::overview($route_match, $entity_type_id);
      $entity_type_id = $entity_type_id ?? '';
      $event = new ContentTranslationDashboardAlterEvent($build, $route_match, $entity_type_id);
      $this->eventDispatcher->dispatch($event, ContentTranslationDashboardAlterEvent::NAME);

      return $event->getBuild();
    }

    $build = [];

    $cache = new CacheableMetadata();

    $build['existing_translations'] = [
      '#weight' => 100,
    ];
    $element = &$build['existing_translations'];
    $element['title'] = [
      '#type' => 'inline_template',
      '#template' => "<h3>{{ 'Existing translations for the latest default revision'|t }}</h3>",
    ];

    // By default, content translation loads the latest revision but we want
    // here to show the translations available on the latest default revision.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity->id());

    $cache->addCacheableDependency($entity);

    $translation_languages = $this->languageManager->getLanguages();

    $header = [
      $this->t('Language'),
      $this->t('Title'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($translation_languages as $language) {
      $translation = $entity->hasTranslation($language->getId()) ? $entity->getTranslation($language->getId()) : NULL;
      $row = [
        'data' => [
          'language' => $language->getName(),
        ],
      ];

      if ($translation) {
        $row['data']['title'] = [
          'data' => [
            '#type' => 'link',
            '#title' => $translation->label(),
            '#url' => $translation->toUrl(),
          ],
        ];
      }
      else {
        $row['data']['title'] = [
          'data' => [
            '#markup' => $this->t('No translation'),
          ],
        ];
      }

      $row['data']['operations'] = [
        'data' => $this->getTranslationOperationLinks($translation),
      ];

      if ($translation && $translation->isDefaultTranslation()) {
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
    $this->eventDispatcher->dispatch($event, ContentTranslationDashboardAlterEvent::NAME);

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
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $translation
   *   The entity translation.
   *
   * @return array
   *   The operations.
   */
  protected function getTranslationOperationLinks(?ContentEntityInterface $translation = NULL): array {
    if (!$translation) {
      // It means we don't yet have a translation.
      return [];
    }

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
