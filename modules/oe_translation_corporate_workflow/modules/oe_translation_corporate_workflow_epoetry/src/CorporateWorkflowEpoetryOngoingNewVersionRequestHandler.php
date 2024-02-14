<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow_epoetry;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandler;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;

/**
 * Overrides the new version request handler for ongoing ePoetry requests.
 */
class CorporateWorkflowEpoetryOngoingNewVersionRequestHandler extends EpoetryOngoingNewVersionRequestHandler {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModerationInformationInterface $moderationInformation) {
    parent::__construct($entityTypeManager);
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   *
   * We override the parent access check to ensure that update requests to
   * ongoing translation requests can only be made if the changed content has
   * reached a new version (has become published or validated), in addition
   * to the constraints imposed by the parent class.
   */
  public function canCreateRequest(TranslationRequestEpoetryInterface $request): bool {
    $entity = $request->getContentEntity();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return parent::canCreateRequest($request);
    }

    $access = parent::canCreateRequest($request);
    if (!$access) {
      return FALSE;
    }

    $update_entity = $this->getUpdateEntity($request);

    $state = $update_entity->get('moderation_state')->value;
    if (!in_array($state, ['validated', 'published'])) {
      return FALSE;
    }

    if (!$entity->hasField('version') || $entity->get('version')->isEmpty()) {
      // We rely on the corporate workflow entity version field.
      return FALSE;
    }

    // At this point, we are sure we are dealing with a corporate workflow
    // entity, so we need to check that the update revision is not in the same
    // major version because then it's not an update of the content.
    $original_version = $entity->get('version')->first();
    $original_major = (int) $original_version->get('major')->getValue();
    $update_version = $update_entity->get('version')->first();
    $update_major = (int) $update_version->get('major')->getValue();

    if ($original_major === $update_major) {
      return FALSE;
    }

    return TRUE;
  }

}
