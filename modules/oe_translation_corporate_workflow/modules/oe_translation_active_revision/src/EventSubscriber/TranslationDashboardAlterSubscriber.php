<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\oe_translation\EntityRevisionInfoInterface;
use Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent;
use Drupal\oe_translation_active_revision\LanguageRevisionMapping;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation dashboard alteration event.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class TranslationDashboardAlterSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use CorporateWorkflowTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The entity revision info service.
   *
   * @var \Drupal\oe_translation\EntityRevisionInfoInterface
   */
  protected $entityRevisionInfo;

  /**
   * Creates a new TranslationDashboardAlterSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information service.
   * @param \Drupal\oe_translation\EntityRevisionInfoInterface $entityRevisionInfo
   *   The entity revision info service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, ModerationInformationInterface $moderationInformation, EntityRevisionInfoInterface $entityRevisionInfo) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->moderationInformation = $moderationInformation;
    $this->entityRevisionInfo = $entityRevisionInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ContentTranslationDashboardAlterEvent::NAME => [
        'alterDashboard',
        -200,
      ],
    ];
  }

  /**
   * Alters the dashboard to add local translation data.
   *
   * @param \Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent $event
   *   The event.
   */
  public function alterDashboard(ContentTranslationDashboardAlterEvent $event) {
    $build = $event->getBuild();

    // Get the latest published entity version from context.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getRouteMatch()->getParameter($event->getEntityTypeId());
    $entity = $this->entityTypeManager->getStorage($event->getEntityTypeId())->load($entity->id());

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return;
    }

    // Alter the existing translations table.
    $this->alterExistingTranslationsTable($build, $entity);
    $event->setBuild($build);
  }

  /**
   * Alters the existing translation table.
   *
   * Add new operations and information about each language translation.
   *
   * @param array $build
   *   The render array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function alterExistingTranslationsTable(array &$build, ContentEntityInterface $entity): void {
    $cache = CacheableMetadata::createFromRenderArray($build);
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    if ($entity->get('moderation_state')->value !== 'published') {
      // If we don't yet have a published version, we bail out as we don't
      // need to do anything.
      return;
    }

    $is_latest_validated = FALSE;

    // Load the latest entity version and see if it's validated.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_entity */
    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value === 'validated') {
      // In case we have a validated version, it means we have 2 parallel
      // major versions in play so the alteration needs to take into account
      // the fact that we display info about both the currently published
      // version and the next, validated major version.
      $is_latest_validated = TRUE;
    }

    $rows = &$build['existing_translations']['table']['#rows'];
    foreach ($rows as &$row) {
      $langcode = $row['hreflang'];
      if ($entity->hasTranslation($langcode) && $entity->getTranslation($langcode)->isDefaultTranslation()) {
        // We don't need any alterations for the original language.
        continue;
      }

      /** @var \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping */
      $mapping = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getLangcodeMapping($langcode, $entity, $cache);

      if ($is_latest_validated) {
        // This will result in a new table header for the operations, so we need
        // to add it.
        if (!isset($build['existing_translations']['table']['#header']['mapping_operations'])) {
          $build['existing_translations']['table']['#header']['mapping_operations'] = $this->t('Mapping operations');
        }
        $this->alterExistingTranslationsTableRowMultiVersion($row, $entity, $latest_entity, $langcode, $mapping, $cache);
        continue;
      }

      $this->alterExistingTranslationsTableRowSingleVersion($row, $entity, $langcode, $mapping, $cache);
    }

    $cache->applyTo($build);
  }

  /**
   * Alters the row of the existing translation table.
   *
   * This handles the case in which we only have a single major version (the
   * published one).
   *
   * @param array $row
   *   The table row.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The row langcode.
   * @param \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping
   *   The mapping for this langcode.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache data.
   */
  protected function alterExistingTranslationsTableRowSingleVersion(array &$row, ContentEntityInterface $entity, string $langcode, LanguageRevisionMapping $mapping, CacheableMetadata $cache): void {
    $row['data']['operations']['data'] = $this->getOperationsForSingleVersion($entity, $langcode, $mapping);

    if ($mapping->isMappedToNull()) {
      $row['data']['title'] = [
        'data' => [
          '#markup' => $this->t('Mapped to "hidden" (translation hidden)'),
        ],
      ];

      return;
    }

    $mapped_revision = $mapping->getEntity();
    if ($mapped_revision) {
      $mapped_version = $this->getEntityVersion($mapped_revision);
      $row['data']['title'] = [
        'data' => [
          '#markup' => $this->t('Mapped to version @version', ['@version' => $mapped_version]),
        ],
      ];
    }
  }

  /**
   * Alters the row of the existing translation table.
   *
   * This handles the case in which we have two major versions in parallel: the
   * published one and the next one which is validated.
   *
   * @param array $row
   *   The table row.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $latest_entity
   *   Latest entity.
   * @param string $langcode
   *   The row langcode.
   * @param \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping
   *   The mapping for this langcode.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache data.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function alterExistingTranslationsTableRowMultiVersion(array &$row, ContentEntityInterface $entity, ContentEntityInterface $latest_entity, string $langcode, LanguageRevisionMapping $mapping, CacheableMetadata $cache): void {
    // Determine the title columns we need to alter depending on the potential
    // mapping scope.
    $cols = ['title_published'];
    if ($mapping->getScope() === LanguageWithEntityRevisionItem::SCOPE_BOTH) {
      $cols[] = 'title_validated';
    }

    // Add the operations.
    $this->addRowOperationsForMultiVersionRow($row, $entity, $latest_entity, $langcode, $mapping);

    if ($mapping->isMappedToNull()) {
      foreach ($cols as $col) {
        // Check that it actually has a translation that is being hidden before
        // altering the label.
        if ($col === 'title_published' && $entity->hasTranslation($langcode)) {
          $row['data'][$col] = [
            'data' => [
              '#markup' => $this->t('Mapped to "hidden" (translation hidden)'),
            ],
          ];
        }
        if ($col === 'title_validated' && $latest_entity->hasTranslation($langcode)) {
          $row['data'][$col] = [
            'data' => [
              '#markup' => $this->t('Mapped to "hidden" (translation hidden)'),
            ],
          ];
        }
      }

      return;
    }

    $mapped_revision = $mapping->getEntity();
    if ($mapped_revision) {
      $mapped_version = $this->getEntityVersion($mapped_revision);
      $current_version = $this->getEntityVersion($entity);
      foreach ($cols as $col) {
        if ($col === 'title_published' && $current_version === $mapped_version) {
          // Even if there IS a mapping in the database, if the published
          // version is mapped to itself, we don't want to confuse the user
          // but instead just leave the default label that says what version it
          // is. The mapping in fact doesn't change anything for the Published
          // version.
          continue;
        }
        $row['data'][$col] = [
          'data' => [
            '#markup' => $this->t('Mapped to version @version', ['@version' => $mapped_version]),
          ],
        ];
      }
    }
  }

  /**
   * Adds the operations to the multi version row.
   *
   * @param array $row
   *   The table row.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $latest_entity
   *   Latest entity.
   * @param string $langcode
   *   The row langcode.
   * @param \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping
   *   The mapping for this langcode.
   */
  protected function addRowOperationsForMultiVersionRow(array &$row, ContentEntityInterface $entity, ContentEntityInterface $latest_entity, string $langcode, LanguageRevisionMapping $mapping): void {
    $operations = $this->getOperationsForSingleVersion($entity, $langcode, $mapping);
    // We don't need the View operation here as well.
    unset($operations['#links']['view']);
    if (isset($operations['#links']['delete'])) {
      unset($operations['#links']['delete']);
    }
    $row['data']['mapping_operations']['data'] = $operations;

    if (!isset($row['data']['operations_published']['data']['#links'])) {
      // Not all languages have operations so start the array as empty.
      $row['data']['operations_published'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => [],
        ],
      ];
    }

    $operations_published = &$row['data']['operations_published']['data']['#links'];
    if (isset($operations_published['delete'])) {
      // Kill the Delete operation because the user won't have access to delete
      // the published translation while there is a forward revision anyway.
      unset($operations_published['delete']);
    }

    if (!isset($row['data']['operations_validated']['data']['#links'])) {
      // Not all languages have operations so start the array as empty.
      $row['data']['operations_validated'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => [],
        ],
      ];
    }
    // Update the label of the Delete link.
    $operations_validated = &$row['data']['operations_validated']['data']['#links'];
    if (isset($operations_validated['delete'])) {
      $operations_validated['delete']['title'] = $this->t('Delete translation');
      // However, if we have a mapping, remove the Delete link because for the
      // user it makes no sense to have it there while it's mapped.
      if ($mapping->isMapped() && $mapping->getScope() === LanguageWithEntityRevisionItem::SCOPE_BOTH) {
        unset($operations_validated['delete']);
      }
    }
  }

  /**
   * Returns the operations for a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The langcode.
   * @param \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping
   *   The determined mapping.
   *
   * @return array
   *   The operations.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function getOperationsForSingleVersion(ContentEntityInterface $entity, string $langcode, LanguageRevisionMapping $mapping): array {
    $links = [];

    $active_revision = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity($entity->getEntityTypeId(), $entity->id());

    // If there is a translation for this language, we need a link to View it.
    if ($entity->hasTranslation($langcode)) {
      $translation = $entity->getTranslation($langcode);
      $links['view'] = [
        'title' => $this->t('View'),
        'url' => $translation->toUrl(),
      ];

      $delete_url = $translation->toUrl('delete-form');
      if ((!$active_revision || !$mapping->isMapped()) && $delete_url->access()) {
        $links['delete'] = [
          'title' => $this->t('Delete translation'),
          'url' => $translation->toUrl('delete-form'),
        ];
      }
    }

    // Prepare the link to hide the translation (map to null).
    $map_to_null = Url::fromRoute('oe_translation_active_revision.map_to_null', [
      'langcode' => $langcode,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);

    if (!$active_revision) {
      // It means we don't yet have an active revision entity for this entity.
      $add_mapping = Url::fromRoute('oe_translation_active_revision.mapping_create', [
        'langcode' => $langcode,
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);

      if ($add_mapping->access()) {
        $links['update_mapping'] = [
          'title' => $this->t('Add mapping'),
          'url' => $add_mapping,
        ];
      }

      if ($map_to_null->access()) {
        $links['map_to_null'] = [
          'title' => $this->t('Map to "hidden" (hide translation)'),
          'url' => $map_to_null,
        ];
      }

      return [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    // Remove mapping URL.
    $remove_mapping = Url::fromRoute('oe_translation_active_revision.mapping_removal_confirmation', [
      'active_revision' => $active_revision->id(),
      'langcode' => $langcode,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);

    $update_mapping = Url::fromRoute('oe_translation_active_revision.mapping_update', [
      'active_revision' => $active_revision->id(),
      'langcode' => $langcode,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);

    // The case in which we are mapped to nothing, we have the option to add
    // a mapping to some other version or to remove the mapping.
    if ($mapping->isMappedToNull()) {
      // Add mapping.
      if ($update_mapping->access()) {
        $links['update_mapping'] = [
          'title' => $this->t('Map to version'),
          'url' => $update_mapping,
        ];
      }

      if ($remove_mapping->access()) {
        $links['remove_mapping'] = [
          'title' => $this->t('Remove mapping'),
          'url' => $remove_mapping,
        ];
      }
    }

    // The case in which we have a mapping to a version, we have the option to
    // change the version or to remove this mapping.
    if ($mapping->isMapped() && $mapping->getEntity()) {
      if ($remove_mapping->access()) {
        $links['remove_mapping'] = [
          'title' => $this->t('Remove mapping'),
          'url' => $remove_mapping,
        ];
      }

      // Update mapping.
      if ($update_mapping->access()) {
        $links['update_mapping'] = [
          'title' => $this->t('Update mapping'),
          'url' => $update_mapping,
        ];
      }

      if ($map_to_null->access()) {
        $links['map_to_null'] = [
          'title' => $this->t('Map to "hidden" (hide translation)'),
          'url' => $map_to_null,
        ];
      }
    }

    // The case in which we don't yet have any mapping for a language but we do
    // have an active revision entity, we can add a new mapping.
    if (!$mapping->isMapped()) {
      if ($update_mapping->access()) {
        $links['update_mapping'] = [
          'title' => $this->t('Add mapping'),
          'url' => $update_mapping,
        ];
      }

      if ($map_to_null->access()) {
        $links['map_to_null'] = [
          'title' => $this->t('Map to "hidden" (hide translation)'),
          'url' => $map_to_null,
        ];
      }
    }

    return [
      '#type' => 'operations',
      '#links' => $links,
    ];

  }

}
