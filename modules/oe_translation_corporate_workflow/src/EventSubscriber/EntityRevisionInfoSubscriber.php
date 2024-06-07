<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation\Event\EntityRevisionEvent;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides the entity revision onto which the translation should be saved.
 *
 * When content uses the corporate workflow, the translations need to be saved
 * onto the same major version, in the latest revision made as part of that
 * major version.
 */
class EntityRevisionInfoSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The translator providers service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translatorProviders;

  /**
   * EntityRevisionInfoSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information.
   * @param \Drupal\oe_translation\TranslatorProvidersInterface $translatorProviders
   *   The translator providers service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModerationInformationInterface $moderationInformation, TranslatorProvidersInterface $translatorProviders) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moderationInformation = $moderationInformation;
    $this->translatorProviders = $translatorProviders;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityRevisionEvent::EVENT => ['getEntity', 0],
    ];
  }

  /**
   * Returns the correct entity revision.
   *
   * @param \Drupal\oe_translation\Event\EntityRevisionEvent $event
   *   The event.
   */
  public function getEntity(EntityRevisionEvent $event): void {
    $entity = $event->getEntity();
    $target_langcode = $event->getTargetLanguage();

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow' || !$this->translatorProviders->hasTranslators($entity->getEntityType())) {
      // We only care about the entities moderated using the corporate
      // workflow and which are using our translation system.
      return;
    }

    if (!$entity->hasField('version') || $entity->get('version')->isEmpty()) {
      // We rely on the corporate workflow entity version field.
      return;
    }

    /** @var \Drupal\entity_version\Plugin\Field\FieldType\EntityVersionItem $original_version */
    $original_version = $entity->get('version')->first();
    $original_major = $original_version->get('major')->getValue();
    $original_minor = $original_version->get('minor')->getValue();

    // Load the latest revision of the same entity that has the same major and
    // minor version. This is in case the entity is published since it was
    // validated to ensure we save the translation onto that version.
    $results = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->getQuery()
      ->condition($entity->getEntityType()->getKey('id'), $entity->id())
      ->condition('version.major', $original_major)
      ->condition('version.minor', $original_minor)
      ->accessCheck(FALSE)
      ->allRevisions()
      ->execute();

    end($results);
    $vid = key($results);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($vid);

    // We create the empty translation on the entity so that we ensure if we
    // need to set the entity to not be the default revision (see below), it
    // doesn't get later overridden in
    // TranslationSourceManager::doSaveTranslations().
    if (!$entity->hasTranslation($target_langcode)) {
      // We need to ensure that after we create the translation, we maintain
      // the "default revision" flag so that in case we are creating a
      // translation for a non-default revision, it doesn't transform it by
      // accident into a default revision. This happens, for example, if we
      // have a published revision followed by a draft (default) revision as a
      // result of an Unpublishing action.
      $default_revision = $entity->isDefaultRevision();
      $entity->addTranslation($target_langcode, $entity->toArray());
      $entity->isDefaultRevision($default_revision);
    }

    // Check if the entity has a forward revision that is published and mark
    // the revision as not the default if any are found. This is needed because
    // it means the translation is being saved on a older version and we need to
    // make sure Drupal doesn't turn this revision into the current one.
    $has_forward_published = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->getQuery()
      ->condition($entity->getEntityType()->getKey('id'), $entity->id())
      ->condition($entity->getEntityType()->getKey('revision'), $vid, '>')
      ->condition($entity->getEntityType()->getKey('published'), TRUE)
      ->accessCheck(FALSE)
      ->allRevisions()
      ->execute();

    if (!empty($has_forward_published)) {
      $entity->isDefaultRevision(FALSE);
    }

    $event->setEntity($entity);
    $event->stopPropagation();
  }

}
