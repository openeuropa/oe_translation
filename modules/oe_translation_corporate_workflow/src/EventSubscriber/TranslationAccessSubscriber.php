<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\oe_translation\Event\TranslationRequestCreateAccessEvent;
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
   * TranslationAccessSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation info.
   */
  public function __construct(ModerationInformationInterface $moderationInformation) {
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Run after other subscribers (default priority 0) so that this
      // restriction always has the final say, even if another subscriber
      // already granted access.
      TranslationRequestCreateAccessEvent::class => ['access', -100],
    ];
  }

  /**
   * Callback to control the access.
   *
   * Entities using the corporate workflow can only be translated if they are
   * in either validated or published state.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestCreateAccessEvent $event
   *   The event.
   */
  public function access(TranslationRequestCreateAccessEvent $event) {
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
