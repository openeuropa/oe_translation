<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation_active_revision\ActiveRevisionInterface;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;

/**
 * Allows to update a language to revision mapping.
 */
class MappingUpdateForm extends MappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_active_revision_mapping_update';
  }

  /**
   * Access callback to the form.
   *
   * Here we check that we have at least 1 version the user can choose to
   * add a mapping to.
   *
   * @param \Drupal\oe_translation_active_revision\ActiveRevisionInterface|null $active_revision
   *   The active revision.
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
  public function access(ActiveRevisionInterface $active_revision = NULL, string $langcode = NULL, string $entity_type = NULL, string $entity_id = NULL): AccessResultInterface {
    $options = $this->getVersionOptions($entity_type, $entity_id, $langcode);
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    $mapping = $active_revision->getLanguageMapping($langcode, $entity);

    // Filter out the revision that is already mapped to this entity.
    $options = array_filter($options, function ($version, $revision_id) use ($mapping) {
      $mapped_revision = $mapping->getEntity();
      if (!$mapped_revision) {
        return TRUE;
      }
      return (int) $mapped_revision->getRevisionId() !== $revision_id;
    }, ARRAY_FILTER_USE_BOTH);

    if (count($options) > 0) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ActiveRevisionInterface $active_revision = NULL, string $langcode = NULL, string $entity_type = NULL, string $entity_id = NULL) {
    $language = $this->languageManager->getLanguage($langcode);
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    $mapping = $active_revision->getLanguageMapping($langcode, $entity);
    $form['#title'] = $this->t('Updating mapping for @title', ['@title' => $entity->label()]);

    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value === 'validated' && $mapping->getScope() === LanguageWithEntityRevisionItem::SCOPE_BOTH && !$form_state->getUserInput()) {
      $this->messenger()->addWarning('Please be aware that updating this mapping will apply to both the currently Published version and the new Validated major version.');
    }

    $options = $this->getVersionOptions($entity_type, $entity_id, $langcode);

    if (!$options) {
      $form['info'] = [
        '#markup' => $this->t('There are no versions to map this language to.'),
      ];

      // This should not really happen.
      return $form;
    }

    $form['update_mapping'] = [
      '#type' => 'select',
      '#title' => $this->t('Update mapping for @language', ['@language' => $language->getName()]),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form_state->set('message', $this->t('The mapping has been updated.'));
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 100,
    ];

    $form_state->set('active_revision', $active_revision);
    $form_state->set('entity_type', $entity_type);
    $form_state->set('entity_id', $entity_id);
    $form_state->set('langcode', $langcode);

    return $form;
  }

}
