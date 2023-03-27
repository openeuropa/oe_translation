<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   * Creates a new TranslationDashboardAlterSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, ModerationInformationInterface $moderationInformation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->moderationInformation = $moderationInformation;
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

    // Load the default version.
    $published_version = $this->getEntityVersion($entity);

    // Load the latest entity version and see if it's validated.
    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value !== 'validated') {
      // It means we have not reached far enough to have a new version.
      return;
    }
    $latest_entity_version = $this->getEntityVersion($latest_entity);

    // Create an array of languages across both versions, with info reglated
    // to each.
    $languages = [];
    $language_names = [];
    foreach ($entity->getTranslationLanguages(TRUE) as $language) {
      $translation = $entity->getTranslation($language->getId());
      $info = [
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $translation->label(),
            '#url' => $translation->toUrl(),
          ],
        ],
        'version' => $published_version,
        'operations' => $this->getTranslationOperations($translation, $build),
      ];

      $languages[$language->getId()][$published_version] = $info;
      if ($translation->isDefaultTranslation()) {
        $languages[$language->getId()]['default_language'] = TRUE;
      }
      $language_names[$language->getId()] = $language->getName();
    }

    foreach ($latest_entity->getTranslationLanguages(TRUE) as $language) {
      $translation = $latest_entity->getTranslation($language->getId());
      $info = [
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $translation->label(),
            '#url' => $translation->toUrl(),
          ],
        ],
        'version' => $latest_entity_version,
        // Only the published version can have operations.
        'operations' => 'N/A',
      ];

      $languages[$language->getId()][$latest_entity_version] = $info;
      $language_names[$language->getId()] = $language->getName();
    }

    // Rebuild the table.
    $header = [
      $this->t('Language'),
      $this->t('Title @published', ['@published' => $published_version]),
      $this->t('Title @validated', ['@validated' => $latest_entity_version]),
      $this->t('Operations @published', ['@published' => $published_version]),
    ];

    $rows = [];
    foreach ($languages as $langcode => $info) {
      $row['data'] = [];
      $row['data']['language'] = $language_names[$langcode];
      $row['data']['title_published'] = isset($info[$published_version]) ? $info[$published_version]['title'] : 'N/A';
      $row['data']['title_validated'] = isset($info[$latest_entity_version]) ? $info[$latest_entity_version]['title'] : 'N/A';
      $row['data']['operations'] = isset($info[$published_version]) ? $info[$published_version]['operations'] : 'N/A';

      $row['hreflang'] = $langcode;
      if (isset($info['default_language'])) {
        $row['class'][] = 'color-success';
      }

      $rows[] = $row;
    }

    $build['existing_translations']['table']['#header'] = $header;
    $build['existing_translations']['table']['#rows'] = $rows;
  }

  /**
   * Fishes out the operations links from the existing table.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation language for which to get the operations.
   * @param array $build
   *   The build array.
   *
   * @return array
   *   The operations links.
   */
  protected function getTranslationOperations(ContentEntityInterface $translation, array $build): array {
    foreach ($build['existing_translations']['table']['#rows'] as $key => $row) {
      if ($row['hreflang'] === $translation->language()->getId()) {
        return $row['data']['operations'];
      }
    }
  }

}
