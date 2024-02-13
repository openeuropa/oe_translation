<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation_active_revision\ActiveRevisionInterface;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form to remove a mapping.
 */
class MappingRemovalConfirmationForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MappingRemovalConfirmationForm.
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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_active_revision_mapping_removal_confirmation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ActiveRevisionInterface $active_revision = NULL, string $langcode = NULL, string $entity_type = NULL, string $entity_id = NULL) {
    $form = parent::buildForm($form, $form_state);

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    $mapping = $active_revision->getLanguageMapping($langcode, $entity);
    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value === 'validated' && $mapping->getScope() === LanguageWithEntityRevisionItem::SCOPE_BOTH && !$form_state->getUserInput()) {
      $this->messenger()->addWarning('Please be aware that this will remove the mapping for both the currently Published version and the new Validated major version. If there is one, the  carried over translation will be used instead.');
    }
    else {
      $this->messenger()->addWarning('Please be aware that this will remove the mapping. If there is one, the carried over translation will be used instead.');
    }

    $form_state->set('active_revision', $active_revision);
    $form_state->set('langcode', $langcode);

    return $form;
  }

  /**
   * Access callback to the form.
   *
   * Here we check if the active revision actually has a mapping for the
   * given language. There is also a CSRF access check.
   *
   * @param \Drupal\oe_translation_active_revision\ActiveRevisionInterface|null $active_revision
   *   The active revision.
   * @param string|null $langcode
   *   The langcode.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(ActiveRevisionInterface $active_revision = NULL, string $langcode = NULL): AccessResultInterface {
    if ($active_revision->hasLanguageMapping($langcode)) {
      return AccessResult::allowed()->addCacheableDependency($active_revision);
    }

    return AccessResult::forbidden()->addCacheableDependency($active_revision);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_active_revision\ActiveRevisionInterface $active_revision */
    $active_revision = $form_state->get('active_revision');
    $langcode = $form_state->get('langcode');

    $active_revision->removeLanguageMapping($langcode);
    // If we are left with no mappings, delete the entire entity.
    if ($active_revision->get('field_language_revision')->isEmpty()) {
      $active_revision->delete();
      $this->messenger()->addMessage($this->t('The mapping has been removed. There are no more language mappings for this entity.'));
      return;
    }

    $active_revision->save();
    $this->messenger()->addMessage($this->t('The mapping has been removed for this language.'));
    $url = $form['actions']['cancel']['#url'];
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove this mapping?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // We rely on the destination query parameter.
  }

}
