<?php

declare(strict_types=1);

namespace Drupal\oe_translation_local\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent;
use Drupal\oe_translation_local\TranslationRequestLocal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation dashboard alteration event.
 */
class TranslationDashboardAlterSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

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
    return [ContentTranslationDashboardAlterEvent::NAME => 'alterDashboard'];
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

    $build['local_translation'] = [
      '#weight' => -100,
    ];
    $element = &$build['local_translation'];
    $element['title'] = [
      '#type' => 'inline_template',
      '#template' => "<h3>{{ 'Started local translations' }}</h3>",
    ];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $current_entity */
    $current_entity = $event->getRouteMatch()->getParameter($event->getEntityTypeId());
    /** @var \Drupal\oe_translation\TranslationRequestStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('oe_translation_request');

    /** @var \Drupal\oe_translation_local\TranslationRequestLocal[] $translation_requests */
    $translation_requests = $storage->getTranslationRequestsForEntity($current_entity, 'local');
    $translation_requests = array_filter($translation_requests, function (TranslationRequestLocal $translation_request) {
      return $translation_request->getTargetLanguageWithStatus()->getStatus() !== TranslationRequestLocal::STATUS_LANGUAGE_SYNCHRONISED;
    });

    $cache->addCacheTags(['oe_translation_request_list']);
    if (!$translation_requests) {
      $element[] = [
        '#markup' => $this->t('There are no open local translation requests'),
      ];
      $cache->applyTo($build);
      $event->setBuild($build);
      return;
    }

    $header = [
      'language' => $this->t('Language'),
      'status' => $this->t('Request status'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];
    foreach ($translation_requests as $translation_request) {
      $entity = $translation_request->getContentEntity();
      $language = $this->languageManager->getLanguage($translation_request->getTargetLanguageWithStatus()->getLangcode());
      $row = [
        'language' => $language->getName(),
        'status' => $translation_request->getTargetLanguageWithStatus()->getStatus(),
        'operations' => [
          'data' => $translation_request->getOperationsLinks(),
        ],
      ];

      $rows[] = [
        'data' => $row,
        'hreflang' => $language->getId(),
        'data-revision-id' => $entity->getRevisionId(),
      ];
    }

    $element['table'] = [
      '#theme' => 'table',
      '#attributes' => [
        'class' => ['ongoing-local-translation-requests-table'],
      ],
      '#header' => $header,
      '#rows' => $rows,
    ];

    $cache->applyTo($build);
    $event->setBuild($build);
  }

}
