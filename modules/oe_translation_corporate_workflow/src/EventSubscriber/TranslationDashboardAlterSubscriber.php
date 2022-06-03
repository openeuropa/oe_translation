<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

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
   * Creates a new TranslationDashboardAlterSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
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

    // Get the default entity version from context.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getRouteMatch()->getParameter($event->getEntityTypeId());

    // Alter the local translation table.
    if (isset($build['local_translation']['table'])) {
      $this->alterLocalTranslationTable($build, $entity);
    }

    $cache->applyTo($build);
    $event->setBuild($build);
  }

  /**
   * Alters the local translation table.
   *
   * @param array $build
   *   The render array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   */
  protected function alterLocalTranslationTable(array &$build, ContentEntityInterface $entity): void {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $table = &$build['local_translation']['table'];
    $header = [];
    foreach ($table['#header'] as $key => $col) {
      if ($key === 'operations') {
        $header['version'] = $this->t('Version');
        $header['state'] = $this->t('Moderation state');
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
          $cols['version'] = $this->getEntityVersion($revision);
          $cols['state'] = $revision->get('moderation_state')->value;
        }
        $cols[$key] = $value;
      }
      $row['data'] = $cols;
    }
  }

}
