<?php

namespace Drupal\oe_translation;

use Drupal\content_moderation\Entity\Handler\ModerationHandler;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content moderation handler.
 *
 * Extends the default handler and ensures that when entities are saved, new
 * revisions are created only if the entities are in their original language.
 * This allows for translations to be added to the same revision without
 * creating extra revisions every time translations come from the local or
 * remote translation service.
 */
class TranslationModerationHandler extends ModerationHandler {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * NodeModerationHandler constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(ModerationInformationInterface $moderation_info) {
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function onPresave(ContentEntityInterface $entity, $default_revision, $published_state) {
    if ($entity->isDefaultTranslation()) {
      parent::onPresave($entity, $default_revision, $published_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function enforceRevisionsEntityFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $form['revision']['#disabled'] = TRUE;
    $form['revision']['#default_value'] = TRUE;
    $form['revision']['#description'] = $this->t('Revisions are required.');
  }

  /**
   * {@inheritdoc}
   */
  public function enforceRevisionsBundleFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // Force the revision checkbox on.
    $form['workflow']['options']['revision']['#value'] = 'revision';
    $form['workflow']['options']['revision']['#disabled'] = TRUE;
  }

}
