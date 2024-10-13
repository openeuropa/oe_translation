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
 * Confirmation form to map to null (hide translation).
 */
class MapToNullConfirmationForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MapToNullConfirmationForm.
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_active_revision_map_to_null_confirmation';
  }

  /**
   * Access callback to the form.
   *
   * Here we check that there is actually a translation for this language to
   * hide. In doing so, we need to check both the published version of exists
   * and the latest version that it may have which is not yet published (
   * validated).
   *
   * @param string|null $langcode
   *   The langcode.
   * @param string|null $entity_type
   *   The entity type.
   * @param string|null $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(?string $langcode = NULL, ?string $entity_type = NULL, ?string $entity_id = NULL): AccessResultInterface {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity->hasTranslation($langcode)) {
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value === 'validated' && $latest_entity->hasTranslation($langcode)) {
      return AccessResult::allowed()->addCacheableDependency($entity);
    }

    return AccessResult::forbidden()->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $langcode = NULL, ?string $entity_type = NULL, ?string $entity_id = NULL) {
    $form = parent::buildForm($form, $form_state);

    $active_revision = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity($entity_type, $entity_id);
    if (!$active_revision instanceof ActiveRevisionInterface) {
      $active_revision = $this->entityTypeManager->getStorage('oe_translation_active_revision')
        ->create([
          'status' => 1,
        ]);
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    $mapping = $active_revision->getLanguageMapping($langcode, $entity);

    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value === 'validated' && $mapping->getScope() === LanguageWithEntityRevisionItem::SCOPE_BOTH && !$form_state->getUserInput()) {
      // Create correcting messaging for the user depending on what this mapping
      // will apply to. It may be that there is a translation available only
      // for the Validated version.
      if ($entity->hasTranslation($langcode)) {
        $this->messenger()->addWarning('Please be aware that mapping to "hidden" will apply to both the currently Published version and the new Validated major version.');
      }
      else {
        $this->messenger()->addWarning('Please be aware that mapping to "hidden" will be relevant to the new Validated major version as there is no translation to hide in the Published version.');
      }
    }

    $form_state->set('active_revision', $active_revision);
    $form_state->set('entity_type', $entity_type);
    $form_state->set('entity_id', $entity_id);
    $form_state->set('langcode', $langcode);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $langcode = $form_state->get('langcode');
    $entity_type = $form_state->get('entity_type');
    $entity_id = $form_state->get('entity_id');
    $active_revision = $form_state->get('active_revision');

    $active_revision->setLanguageMapping($langcode, $entity_type, (int) $entity_id, 0);
    $active_revision->save();

    $this->messenger()->addMessage($this->t('The translation has been mapped to "hidden". It has not been deleted so you can always remove this mapping.'));
    $url = $form['actions']['cancel']['#url'];
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to map this translation to "hidden"?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // We rely on the destination query parameter.
  }

}
