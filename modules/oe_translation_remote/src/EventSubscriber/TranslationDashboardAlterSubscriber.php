<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
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
   * The remote translation provider manager.
   *
   * @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager
   */
  protected $providerManager;

  /**
   * Creates a new TranslationDashboardAlterSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager $providerManager
   *   The remote translation provider manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RemoteTranslationProviderManager $providerManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->providerManager = $providerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [ContentTranslationDashboardAlterEvent::NAME => 'alterDashboard'];
  }

  /**
   * Alters the dashboard to add remote translation data.
   *
   * @param \Drupal\oe_translation\Event\ContentTranslationDashboardAlterEvent $event
   *   The event.
   */
  public function alterDashboard(ContentTranslationDashboardAlterEvent $event) {
    $build = $event->getBuild();
    $cache = CacheableMetadata::createFromRenderArray($build);

    $build['remote_translation'] = [
      '#weight' => -100,
    ];
    $element = &$build['remote_translation'];
    $element['title'] = [
      '#type' => 'inline_template',
      '#template' => "<h3>{{ 'Ongoing remote translation requests' }}</h3>",
    ];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $current_entity */
    $current_entity = $event->getRouteMatch()->getParameter($event->getEntityTypeId());

    // Get all the translation requests that are not synced for all revisions.
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface[] $translation_requests */
    $translation_requests = $this->providerManager->getExistingTranslationRequests($current_entity, FALSE);

    $cache->addCacheTags(['oe_translation_request_list']);
    if (!$translation_requests) {
      $element[] = [
        '#markup' => $this->t('There are no ongoing remote translation requests'),
      ];
      $cache->applyTo($build);
      $event->setBuild($build);
      return;
    }

    $header = [
      'translator' => $this->t('Translator'),
      'status' => $this->t('Status'),
      'title' => $this->t('Title'),
      'revision_id' => $this->t('Revision ID'),
      'default_revision' => $this->t('Default revision'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];
    foreach ($translation_requests as $translation_request) {
      $entity = $translation_request->getContentEntity();
      $row = [
        'translator' => $translation_request->getTranslatorProvider()->label(),
        'status' => $translation_request->getRequestStatus(),
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $entity->label(),
            '#url' => $entity->toUrl('revision'),
          ],
        ],
        'revision_id' => $entity->getRevisionId(),
        'default_revision' => $entity->isDefaultRevision() ? $this->t('Yes') : $this->t('No'),
        'operations' => [
          'data' => $translation_request->getOperationsLinks(),
        ],
      ];

      $rows[] = [
        'data' => $row,
        'data-revision-id' => $entity->getRevisionId(),
        'class' => $translation_request->getRequestStatus() === TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED ? ['color-error'] : [],
      ];
    }

    $element['table'] = [
      '#theme' => 'table',
      '#attributes' => [
        'class' => ['ongoing-remote-translation-requests-table'],
      ],
      '#header' => $header,
      '#rows' => $rows,
    ];

    $cache->applyTo($build);
    $event->setBuild($build);
  }

}
