<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\EntityRevisionInfoInterface;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Drupal\oe_translation_local\Event\TranslationLocalControllerAlterEvent;
use Drupal\oe_translation_local\Form\LocalTranslationRequestForm;
use Drupal\oe_translation_local\TranslationRequestLocal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the local translation overview alteration event.
 */
class LocalTranslationOverviewAlterSubscriber implements EventSubscriberInterface {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity revision info service.
   *
   * @var \Drupal\oe_translation\EntityRevisionInfoInterface
   */
  protected $entityRevisionInfo;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Creates a new LocalTranslationOverviewAlterSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\oe_translation\EntityRevisionInfoInterface $entityRevisionInfo
   *   The entity revision info service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, MessengerInterface $messenger, EntityRevisionInfoInterface $entityRevisionInfo, ModerationInformationInterface $moderationInformation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->messenger = $messenger;
    $this->entityRevisionInfo = $entityRevisionInfo;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [TranslationLocalControllerAlterEvent::NAME => 'alterOverview'];
  }

  /**
   * Alters the dashboard to add local translation data.
   *
   * @param \Drupal\oe_translation_local\Event\TranslationLocalControllerAlterEvent $event
   *   The event.
   */
  public function alterOverview(TranslationLocalControllerAlterEvent $event) {
    $build = $event->getBuild();
    $cache = CacheableMetadata::createFromRenderArray($build);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getRouteMatch()->getParameter($event->getEntityTypeId());

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return;
    }

    // We need to print some message explaining to the user what they are
    // translating in terms of version.
    $state = $entity->get('moderation_state')->value;

    // If we are looking at the default revision but it is not in a state that
    // the corporate workflow would mark it as such, it means the entity doesn't
    // yet have a default revision.
    if ($entity->isDefaultRevision() && !in_array($state, [
      'validated',
      'published',
    ])) {
      $this->messenger->addWarning($this->t('This content cannot be translated yet as it does not have a Validated nor Published major version.'));
    }

    // If the loaded entity is the default revision but not the latest, it means
    // the entity may have some drafts created after a published (default)
    // revision. So we need to inform the user. But there are two cases.
    if ($entity->isDefaultRevision() && !$entity->isLatestRevision()) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $latest_revision = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
      $version = $this->getEntityVersion($entity);

      if ($latest_revision->get('moderation_state')->value === 'validated') {
        $latest_revision_version = $this->getEntityVersion($latest_revision);
        // If the latest version is validated, it means we can make translations
        // also of this revision.
        $this->messenger->addWarning($this->t('Your content has a new version (<em>@new_version</em>) ahead of the currently published one (<em>@published_version</em>). This means you can perform translations on both.', [
          '@new_version' => $latest_revision_version,
          '@published_version' => $version,
        ]));
        $this->messenger->addWarning($this->t('Be aware that any translations you change or add to the published version will not move upstream to the new version.'));
        $this->alterBuildForParallelTranslations($entity, $latest_revision, $build);
      }
      else {
        // Otherwise, we just inform the user of the version they are
        // translating.
        $this->messenger->addWarning($this->t('Your content has revisions that are ahead of the latest published version.'));
        $this->messenger->addWarning($this->t('However, you are now translating the latest published version of your content: <em>@version</em>, titled @title.', [
          '@version' => $version,
          '@title' => $entity->toLink()->toString(),
        ]));
      }
    }

    $this->alterOperationsForPublishedRevisions($build, $entity, $cache);

    $cache->applyTo($build);
    $event->setBuild($build);
  }

  /**
   * Alter the build in the cases where we need to parallel translate.
   *
   * We should translate both the last published revision and the current
   * validated one.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $published
   *   The published entity (default revision).
   * @param \Drupal\Core\Entity\ContentEntityInterface $validated
   *   The validated entity (latest revision).
   * @param array $build
   *   The build to alter.
   */
  protected function alterBuildForParallelTranslations(ContentEntityInterface $published, ContentEntityInterface $validated, array &$build): void {
    $cache = CacheableMetadata::createFromRenderArray($build);
    $published_version = $this->getEntityVersion($published);
    $validated_version = $this->getEntityVersion($validated);
    $table = &$build['local_translation_overview'];
    $table['#header'][1] = $published_version;
    $table['#header'][] = $validated_version;
    foreach ($table['#rows'] as &$row) {
      // Add the version data attribute to the original columns (published
      // version).
      $row['data'][1]['data-version'] = $published_version;

      // Create corresponding operation links for the validated version as well
      // that either have the revision ID parameter for the creation link, or
      // have the correct translation request edit URL.
      $langcode = $row['hreflang'];
      $existing_requests = $this->getLocalTranslationRequests($validated, $langcode);
      if (!$existing_requests) {
        $column = [
          'data' => [
            '#type' => 'operations',
            '#links' => [],
          ],
          'data-version' => $validated_version,
        ];
        // If we don't have existing requests, we just add a create link.
        $link = LocalTranslationRequestForm::getCreateOperationLink($validated, $langcode, $cache);
        if ($link) {
          $column['data']['#links']['create'] = $link;
        }

        $row['data'][] = $column;
        continue;
      }

      // If we do have an existing request, we need to use its own operation
      // links.
      /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
      $translation_request = reset($existing_requests);
      $links = $translation_request->getOperationsLinks();
      $cache->addCacheableDependency(CacheableMetadata::createFromRenderArray($links));
      $row['data'][] = [
        'data' => $links,
        'data-version' => $validated_version,
      ];
    }

    $cache->applyTo($build);
  }

  /**
   * Alters the operation links for published revisions.
   *
   * If we have a translation started from a validated state but the user
   * has published it in the meantime, the core functionality will allow us
   * to make a new translation request for the published version. But we
   * should not allow it because upon saving the one started from validated,
   * it will be saved onto the published one instead already.
   *
   * @param array $build
   *   The build.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being translated.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cacheable metadata.
   */
  protected function alterOperationsForPublishedRevisions(array &$build, ContentEntityInterface $entity, CacheableMetadata $cache): void {
    $state = $entity->get('moderation_state')->value;
    if ($state !== 'published') {
      // We bail out if we are not even on a published version.
      return;
    }

    if (!$entity->hasField('version') || $entity->get('version')->isEmpty()) {
      return;
    }

    // Query for the revisions in the same major and minor as the currently
    // published revision. This will yield the validated and published
    // (current) one.
    $results = $this->queryRevisionsInSameMajorAndMinor($entity);
    if (count($results) !== 2) {
      // There should be two results: the validated revision and the published
      // one.
      return;
    }

    // The validated one should be the first.
    $revision_id = key($results);
    $validated = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($revision_id);

    $table = &$build['local_translation_overview'];
    foreach ($table['#rows'] as &$row) {
      if (!isset($row['data'][1]['data']['#links']['create'])) {
        continue;
      }

      $langcode = $row['hreflang'];
      $existing_requests = $this->getLocalTranslationRequests($validated, $langcode);
      if (!$existing_requests) {
        continue;
      }
      // If we have existing requests, we need to kill the create link and
      // replace it with an edit one to that which exists for the validated
      // revision.
      $translation_request = reset($existing_requests);
      $links = $translation_request->getOperationsLinks();
      $row['data'][1]['data'] = $links;
    }
  }

  /**
   * Returns the local translation requests for this entity revision.
   *
   * It only includes the ones that have the give language as target and that
   * are not synced already. If they are synced, their job is officially done.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The target langcode.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface[]
   *   Translation requests for this entity revision.
   */
  protected function getLocalTranslationRequests(ContentEntityInterface $entity, string $langcode): array {
    /** @var \Drupal\oe_translation\TranslationRequestStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('oe_translation_request');
    $translation_requests = $storage->getTranslationRequestsForEntityRevision($entity, 'local');
    return array_filter($translation_requests, function (TranslationRequestLocal $translation_request) use ($langcode) {
      $target = $translation_request->getTargetLanguageWithStatus();
      return $langcode === $target->getLangcode() && $target->getStatus() !== TranslationRequestLocal::STATUS_LANGUAGE_SYNCHRONISED;
    });
  }

}
