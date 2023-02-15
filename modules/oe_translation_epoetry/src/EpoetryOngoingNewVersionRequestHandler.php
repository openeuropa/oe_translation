<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Default handler for new version requests.
 */
class EpoetryOngoingNewVersionRequestHandler implements EpoetryOngoingNewVersionRequestHandlerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a EpoetryOngoingNewVersionRequestHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function canCreateRequest(TranslationRequestEpoetryInterface $request): bool {
    // If the request doesn't have one of these statuses, we cannot make such
    // request.
    $access = in_array($request->getEpoetryRequestStatus(), [
      TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
    ]);

    if (!$access) {
      return FALSE;
    }

    // The request needs to still be active.
    if ($request->getRequestStatus() !== TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE) {
      return FALSE;
    }

    $content_entity = $request->getContentEntity();

    // If we have any revisions after this one, we can make an update request.
    return !$content_entity->isLatestRevision();
  }

  /**
   * {@inheritdoc}
   */
  public function getInfoMessage(TranslationRequestEpoetryInterface $request): MarkupInterface {
    return $this->t('Because you made changes to the content and there is an ongoing request, you can request an update to the ongoing translation.');
  }

  /**
   * {@inheritdoc}
   *
   * By default, this will be the last revision of the entity.
   */
  public function getUpdateEntity(TranslationRequestEpoetryInterface $request): ContentEntityInterface {
    $entity = $request->getContentEntity();
    $revision_id = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->getLatestRevisionId($entity->id());
    return $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($revision_id);
  }

}
