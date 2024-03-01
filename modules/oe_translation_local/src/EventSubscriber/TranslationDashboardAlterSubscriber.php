<?php

declare(strict_types=1);

namespace Drupal\oe_translation_local\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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

    $this->addLocalTranslationOperation($build, $translation_requests, $current_entity);

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

  /**
   * Adds the link to create a new local translation.
   *
   * @param array $build
   *   The page build.
   * @param array $translation_requests
   *   The existing translation requests.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   */
  protected function addLocalTranslationOperation(array &$build, array $translation_requests, ContentEntityInterface $entity) {
    $started_local_requests = [];
    foreach ($translation_requests as $request) {
      $started_local_requests[] = $request->getTargetLanguageWithStatus()->getLangcode();
    }
    foreach ($build['existing_translations']['table']['#rows'] as $index => &$row) {
      $langcode = $row['hreflang'];
      if (in_array($langcode, $started_local_requests) || $langcode === $entity->getUntranslated()->language()->getId()) {
        // If there is already a started translation request, we don't add any
        // operation.
        continue;
      }

      $url = Url::fromRoute('oe_translation_local.create_local_translation_request', [
        'entity_type' => $entity->getEntityTypeId(),
        'entity' => $entity->getRevisionId(),
        'source' => $entity->getUntranslated()->language()->getId(),
        'target' => $langcode,
      ], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);

      if (!$url->access()) {
        continue;
      }
      $link = [
        'title' => $this->t('Add new local translation'),
        'weight' => -100,
        'url' => $url,
      ];
      if (isset($row['data']['operations']['data']['#links'])) {
        // It means we have already the operations.
        $row['data']['operations']['data']['#links']['add'] = $link;
        continue;
      }

      // Otherwise, start the operations.
      $row['data']['operations']['data'] = [
        '#type' => 'operations',
        '#links' => [
          'add' => $link,
        ],
      ];
    }
  }

}
