<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow\Form;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\EntityRevisionInfoInterface;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Drupal\oe_translation_remote\Form\RemoteTranslationNewForm as RemoteTranslationNewFormOriginal;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for starting a new remote translation request.
 *
 * This is an override of the original form to handle corporate workflow
 * related aspects.
 */
class RemoteTranslationNewForm extends RemoteTranslationNewFormOriginal {

  use CorporateWorkflowTranslationTrait;

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
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RemoteTranslationProviderManager $providerManager, AccountInterface $account, ModerationInformationInterface $moderationInformation, EntityRevisionInfoInterface $entityRevisionInfo) {
    parent::__construct($entityTypeManager, $providerManager, $account);
    $this->entityTypeManager = $entityTypeManager;
    $this->providerManager = $providerManager;
    $this->account = $account;
    $this->moderationInformation = $moderationInformation;
    $this->entityRevisionInfo = $entityRevisionInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager'),
      $container->get('current_user'),
      $container->get('content_moderation.moderation_information'),
      $container->get('oe_translation.entity_revision_info')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Ensure the content is in the proper moderation state for starting.
   * Also ensure that we don't have already a translation request started on
   * the same version (but previous revision).
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function createNewRequestAccess(ContentEntityInterface $entity): AccessResultInterface {
    $access = parent::createNewRequestAccess($entity);
    if (!$access->isAllowed()) {
      // If it's already not allowed, we just not allow it.
      return $access;
    }

    $cache = CacheableMetadata::createFromObject($access);
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return $access;
    }

    $state = $entity->get('moderation_state')->value;
    if (!in_array($state, ['validated', 'published'])) {
      return AccessResult::forbidden()->setReason($this->t('This content cannot be translated yet as it does not have a Validated nor Published major version.'))->addCacheableDependency($cache);
    }

    if ($entity->isDefaultRevision() && !$entity->isLatestRevision()) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $latest_revision = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
      $version = $this->getEntityVersion($entity);

      // In case we have forward revisions on top of a version, the user cannot
      // make new translation requests unless all active ones have returned.
      $statuses = [
        TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
        TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED,
        TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
      ];
      $requests = $this->providerManager->getExistingTranslationRequests($entity, FALSE, $statuses);
      if ($requests) {
        return AccessResult::forbidden()->setReason($this->t('No translation requests can be made until all translations from the previous request have arrived.'))->addCacheableDependency($cache);
      }

      if ($latest_revision->get('moderation_state')->value === 'validated') {
        // In this case it means we have new revisions on top of a published
        // version which been validated and turned into a new major version.
        // So the user can make new translations of the new major version only
        // if the existing requests (if any) have been all translated by the
        // provider already (doesn't matter if they got synced). It also means
        // they cannot make translation requests for the previous, published,
        // version.
        $latest_revision_version = $this->getEntityVersion($latest_revision);
        $this->messenger()->addWarning($this->t('Your content has a new version (<em>@new_version</em>) ahead of the currently published one (<em>@published_version</em>). This means that new translation requests will be made for the new version.', [
          '@new_version' => $latest_revision_version,
          '@published_version' => $version,
        ]));

        return $access;
      }
      else {
        // In this case it means we have new revisions on top of a published
        // version but which have not yet been validated to turn into a new
        // major version. So the user cannot make new translations on this
        // forward revision, but can make translations on the published version,
        // only if the existing requests (if any) have been all translated by
        // the provider already (doesn't matter if they got synced).
        $statuses = [
          TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
          TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED,
          TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
        ];
        $requests = $this->providerManager->getExistingTranslationRequests($entity, FALSE, $statuses);
        if ($requests) {
          $this->messenger()->addWarning($this->t('No translation requests can be made until all translations from the previous request have arrived.'));
          return AccessResult::forbidden()->setReason($this->t('No translation requests can be made until all translations from the previous request have arrived.'))->addCacheableDependency($cache);
        }

        // If there are no active requests, the user can make translations
        // on the default, published revision.
        $this->messenger()->addWarning($this->t('Your content has revisions that are ahead of the latest published version.'));
        $this->messenger()->addWarning($this->t('However, you are now translating the latest published version of your content: <em>@version</em>, titled @title.', [
          '@version' => $version,
          '@title' => $entity->toLink()->toString(),
        ]));
        return $access;
      }
    }

    // In case we have a published version but with an ongoing translation
    // request started in the validated version, we must also prevent the
    // creation of a new one. Only updates can be made.
    return $this->createNewRequestAccessForPublished($entity, $access);
  }

  /**
   * {@inheritdoc}
   *
   * Handle cases in which the default revision is published and we need to
   * return potential existing requests from the previous (validated) revision.
   */
  protected function getExistingTranslationRequests(ContentEntityInterface $entity): array {
    $requests = parent::getExistingTranslationRequests($entity);
    if ($requests) {
      // It means we have already for the current revision, so we don't need to
      // bother.
      return $requests;
    }

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return $requests;
    }

    $state = $entity->get('moderation_state')->value;
    if ($state !== 'published') {
      return $requests;
    }

    // If we have a published revision, check to see also if its validated
    // revision maybe has requests as the translation could have started from
    // that stage. We can treat those requests as being the same as for the
    // published revision.
    $results = $this->queryRevisionsInSameMajorAndMinor($entity);
    if (count($results) === 2) {
      $revision_id = key($results);
      $validated = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($revision_id);
      $requests = parent::getExistingTranslationRequests($validated);
    }

    return $requests;
  }

  /**
   * {@inheritdoc}
   *
   * Add some more information to the existing requests table.
   */
  protected function buildExistingRequestsForm(array $form, FormStateInterface $form_state, ContentEntityInterface $entity, array $requests): array {
    $form = parent::buildExistingRequestsForm($form, $form_state, $entity, $requests);

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return $form;
    }

    if (count($requests) === 1) {
      $request = reset($requests);
      $revision = $request->getContentEntity();
      $form['title']['#context']['version'] = $this->getEntityVersion($revision);
      $form['title']['#context']['state'] = $revision->get('moderation_state')->value;
      $form['title']['#template'] = "{% trans %}<h3>Ongoing remote translation request via <em>{{ translator }}</em> for version <em>{{ version }}</em> in moderation state <em>{{ state }}</em>.</h3>{% endtrans %}";
      return $form;
    }

    $table = &$form['table'];
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
      $request = $requests[$row['data-translation-request']];
      $revision = $request->getContentEntity();
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

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * In case we have a validated revision on top of a published - hence a new
   * version - use that one for the new translation requests.
   */
  protected function getRequestEntityRevision(ContentEntityInterface $entity): ContentEntityInterface {
    if ($entity->isDefaultRevision() && !$entity->isLatestRevision()) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $latest_revision = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));

      if ($latest_revision->get('moderation_state')->value === 'validated') {
        return $latest_revision;
      }
    }

    return $entity;
  }

  /**
   * Handles the access in case we have a published version - latest revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The currently granted access result.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function createNewRequestAccessForPublished(ContentEntityInterface $entity, AccessResultInterface $access): AccessResultInterface {
    $state = $entity->get('moderation_state')->value;
    if ($state !== 'published') {
      // We bail out if we are not even on a published version.
      return $access;
    }

    if (!$entity->hasField('version') || $entity->get('version')->isEmpty()) {
      return $access;
    }

    $results = $this->queryRevisionsInSameMajorAndMinor($entity);
    if (count($results) !== 2) {
      // There should be two results: the validated revision and the published
      // one.
      return $access;
    }

    $statuses = [
      TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
      TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED,
      TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
    ];
    $requests = $this->providerManager->getExistingTranslationRequests($entity, TRUE, $statuses);
    if (!$requests) {
      return $access;
    }

    // It means we have a translation request started at the validated revision
    // preceding the currently published revision.
    return AccessResult::forbidden()->addCacheableDependency($access);
  }

}
