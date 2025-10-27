<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\oe_translation\Event\TranslationAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Determines access to create new translations based on the corporate workflow.
 */
class TranslationAccessSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The moderation info.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TranslationAccessSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ModerationInformationInterface $moderationInformation, EntityTypeManagerInterface $entityTypeManager) {
    $this->moderationInformation = $moderationInformation;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationAccessEvent::EVENT => 'access',
    ];
  }

  /**
   * Callback to control the access.
   *
   * Entities using the corporate workflow can only be translated if they are
   * in either validated or published state.
   *
   * @param \Drupal\oe_translation\Event\TranslationAccessEvent $event
   *   The event.
   */
  public function access(TranslationAccessEvent $event) {
    $entity = $event->getEntity();
    $cache = CacheableMetadata::createFromObject($event->getAccess());
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return;
    }

    $state = $entity->get('moderation_state')->value;
    if (!in_array($state, ['validated', 'published'])) {
      $event->setAccess(AccessResult::forbidden()->setReason($this->t('This content cannot be translated yet as it does not have a Validated nor Published major version.'))->addCacheableDependency($cache));
    }
  }

}
