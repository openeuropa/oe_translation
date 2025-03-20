<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\oe_translation\Event\TranslationSourceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation source events.
 */
class TranslationSourceEventSubscriber implements EventSubscriberInterface {

  /**
   * The moderation information.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * TranslationSourceEventSubscriber constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information.
   */
  public function __construct(ModerationInformationInterface $moderationInformation) {
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationSourceEvent::SAVE => 'save',
    ];
  }

  /**
   * Reacts to the save event.
   *
   * If the translation of the entity doesn't have the same moderation state
   * as the original, set it to be the same as the original. We need to keep
   * these in sync.
   *
   * @param \Drupal\oe_translation\Event\TranslationSourceEvent $event
   *   The event.
   */
  public function save(TranslationSourceEvent $event): void {
    $entity = $event->getEntity();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      // We only care about the entities moderated using the corporate
      // workflow.
      return;
    }

    $language = $event->getLangcode();
    $translation = $entity->getTranslation($language);
    if ($translation->get('moderation_state')->value !== $entity->getUntranslated()->get('moderation_state')->value) {
      $translation->set('moderation_state', $entity->getUntranslated()->get('moderation_state')->value);
    }
  }

}
