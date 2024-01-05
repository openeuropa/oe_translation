<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\EntityRevisionInfoInterface;
use Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation dashboard alteration event.
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
        -100,
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
    $cache = CacheableMetadata::createFromRenderArray($build);

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

    // Alter the local translation table.
    if (isset($build['local_translation']['table'])) {
      $this->alterTranslationTable($build['local_translation']['table'], $entity);
    }

    // Alter the remote translation table.
    if (isset($build['remote_translation']['table'])) {
      $this->alterTranslationTable($build['remote_translation']['table'], $entity);
    }

    $cache->applyTo($build);
    $event->setBuild($build);
  }

  /**
   * Alters the local or remote translation table.
   *
   * @param array $table
   *   The table render array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   */
  protected function alterTranslationTable(array &$table, ContentEntityInterface $entity): void {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $header = [];
    foreach ($table['#header'] as $key => $col) {
      if ($key === 'operations') {
        $header['version'] = $this->t('Content version');
      }

      $header[$key] = $col;
    }
    $table['#header'] = $header;

    foreach ($table['#rows'] as &$row) {
      $cols = [];
      $revision_id = $row['data-revision-id'];
      $revision = $storage->loadRevision($revision_id);
      // Load the revision onto which the translation would go and display its
      // version and moderation state. This is for those cases in which the
      // request started from Validated, but we have a Published one meanwhile.
      $revision = $this->entityRevisionInfo->getEntityRevision($revision, 'en');
      foreach ($row['data'] as $key => $value) {
        if ($key == 'operations') {
          $cols['version'] = $this->getEntityVersion($revision) . ' / ' . $revision->get('moderation_state')->value;
        }
        $cols[$key] = $value;
      }
      $row['data'] = $cols;
    }
  }

  /**
   * Alters the existing translation table.
   *
   * In case we have an entity that has a published version and a new version
   * ahead (validated), we need to provide info about both since they may have
   * different synchronised translations.
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
    $build['existing_translations']['title']['#template'] = "<h3>{{ 'Existing translations'|t }}</h3>";

    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if ($entity->get('moderation_state')->value !== 'published') {
      // If we don't yet have a published version, we bail out as we don't
      // need to do anything.
      return;
    }

    $all_languages = $this->languageManager->getLanguages();

    // Load the default version.
    $published_version = $this->getEntityVersion($entity);

    // Load the latest entity version and see if it's validated.
    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value !== 'validated') {
      // It means we have not reached far enough to have a new version so all
      // we have to do is alter the titles of the translations.
      $rows = &$build['existing_translations']['table']['#rows'];
      foreach ($rows as &$row) {
        $langcode = $row['hreflang'];
        $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
        if ($translation && !$translation->isDefaultTranslation()) {
          $row['data']['title'] = $this->getTranslationVersionTitle($translation);
        }
      }

      return;
    }

    $latest_entity_version = $this->getEntityVersion($latest_entity);

    // Create an array of languages across both versions, with info related
    // to each.
    $languages = [];
    $language_names = [];
    foreach ($all_languages as $language) {
      $translation = $entity->hasTranslation($language->getId()) ? $entity->getTranslation($language->getId()) : NULL;
      if ($translation) {
        $title = $translation->isDefaultTranslation() ? $translation->toLink() : $this->getTranslationVersionTitle($translation);
      }
      else {
        $title = [
          '#markup' => $this->t('No translation'),
        ];
      }
      $info = [
        'title' => [
          'data' => $title,
        ],
        'version' => $published_version,
        'operations' => $this->getTranslationOperations($translation, TRUE),
      ];

      $languages[$language->getId()][$published_version] = $info;
      if ($translation && $translation->isDefaultTranslation()) {
        $languages[$language->getId()]['default_language'] = TRUE;
      }
      $language_names[$language->getId()] = $language->getName();
    }

    foreach ($latest_entity->getTranslationLanguages(TRUE) as $language) {
      $translation = $latest_entity->getTranslation($language->getId());
      $title = $translation->isDefaultTranslation() ? $translation->toLink(NULL, 'latest-version') : $this->getTranslationVersionTitle($translation);
      $info = [
        'title' => [
          'data' => $title,
        ],
        'version' => $latest_entity_version,
        'operations' => $this->getTranslationOperations($translation, FALSE),
      ];

      $languages[$language->getId()][$latest_entity_version] = $info;
      $language_names[$language->getId()] = $language->getName();
    }

    // Rebuild the table.
    $header = [
      $this->t('Language'),
      $this->t('@published / published', ['@published' => $published_version]),
      $this->t('Operations'),
      $this->t('@validated / validated', ['@validated' => $latest_entity_version]),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($languages as $langcode => $info) {
      $row = [];
      $row['data']['language'] = $language_names[$langcode];
      $row['data']['title_published'] = isset($info[$published_version]) ? $info[$published_version]['title'] : 'N/A';
      $row['data']['operations_published'] = isset($info[$published_version]) ? ['data' => $info[$published_version]['operations']] : 'N/A';
      $row['data']['title_validated'] = isset($info[$latest_entity_version]) ? $info[$latest_entity_version]['title'] : 'N/A';
      $row['data']['operations_validated'] = isset($info[$latest_entity_version]) ? ['data' => $info[$latest_entity_version]['operations']] : 'N/A';

      $row['hreflang'] = $langcode;
      if (isset($info['default_language'])) {
        $row['class'][] = 'color-success';
      }

      $rows[] = $row;
    }

    $build['existing_translations']['table']['#header'] = $header;
    $build['existing_translations']['table']['#rows'] = $rows;
    $build['existing_translations']['#attached']['library'][] = 'oe_translation_corporate_workflow/tables';
  }

  /**
   * Returns the title of a given translation based on the synced version.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  protected function getTranslationVersionTitle(ContentEntityInterface $translation): TranslatableMarkup {
    $version = $this->getEntityVersion($translation);
    $translation_request = $translation->get('translation_request')->entity;
    if (!$translation_request instanceof TranslationRequestInterface) {
      // We cannot determine.
      return $this->t('Version @version (carried over)', ['@version' => $version]);
    }

    $translated_entity = $translation_request->getContentEntity();
    $translation_version = $this->getEntityVersion($translated_entity);
    if ($translation_version === $version) {
      // If the current translation version is the one onto which the
      // translation was synced, we just have the version.
      return $this->t('Version @version', ['@version' => $version]);
    }

    // Otherwise, we indicate which one was the version it was translated on +
    // the fact that now we are ahead and it was carried over.
    return $this->t('Version @version (carried over to the current version)', ['@version' => $translation_version]);
  }

  /**
   * Prepares operations for a given translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $translation
   *   The translation language for which to get the operations.
   * @param bool $default
   *   If we are creating the links for the default revision.
   *
   * @return array
   *   The operations links.
   */
  protected function getTranslationOperations(ContentEntityInterface $translation = NULL, bool $default): array {
    if (!$translation) {
      return [];
    }

    if (!$default && !$translation instanceof NodeInterface) {
      // We only support the operations for non default revisions for Node
      // entities.
      return [];
    }

    $links = [
      'view' => [
        'title' => $this->t('View'),
        'url' => $default ? $translation->toUrl() : Url::fromRoute('entity.node.revision', [
          'node' => $translation->id(),
          'node_revision' => $translation->getRevisionId(),
        ], ['language' => $translation->language()]),
      ],
    ];

    $delete = $translation->toUrl('delete-form');
    if (!$default) {
      $delete = Url::fromRoute('node.revision_delete_confirm', [
        'node' => $translation->id(),
        'node_revision' => $translation->getRevisionId(),
      ], [
        'language' => $translation->language(),
        'query' => ['destination' => Url::fromRoute('<current>')->toString()],
      ]);
    }
    if ($delete->access() && !$translation->isDefaultTranslation()) {
      // We don't want to present a link to delete the original translation.
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => $delete,
      ];
    }

    return [
      '#type' => 'operations',
      '#links' => $links,
    ];
  }

}
