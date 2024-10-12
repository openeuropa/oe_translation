<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;

/**
 * Allows to create a language to revision mapping.
 */
class MappingCreateForm extends MappingFormBase {

  use CorporateWorkflowTranslationTrait;

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
    $options = $this->getVersionOptions($entity_type, $entity_id, $langcode);

    if (count($options) > 0) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $langcode = NULL, ?string $entity_type = NULL, ?string $entity_id = NULL) {
    $language = $this->languageManager->getLanguage($langcode);
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    $form['#title'] = $this->t('Add a mapping for @title', ['@title' => $entity->label()]);

    $latest_entity = $storage->loadRevision($storage->getLatestRevisionId($entity->id()));
    if ($latest_entity->get('moderation_state')->value === 'validated') {
      $applies_to_published_and_validated = TRUE;
      $translation_request = $latest_entity->hasTranslation($langcode) ? $latest_entity->getTranslation($langcode)->get('translation_request')->entity : NULL;
      if ($translation_request instanceof TranslationRequestInterface && $translation_request->getContentEntity()->getRevisionId() == $latest_entity->getRevisionId()) {
        // If the latest entity (validated) has been already translated, it
        // means that the mapping scope should only apply to the published
        // version. Hence we show this messaging only in the other case.
        $applies_to_published_and_validated = FALSE;
      }

      if ($applies_to_published_and_validated && !$form_state->getUserInput()) {
        $this->messenger()->addWarning('Please be aware that creating this mapping will apply to both the currently Published version and the new Validated major version.');
      }

      $form_state->set('applies_to_published_and_validated', $applies_to_published_and_validated);
    }

    $options = $this->getVersionOptions($entity_type, $entity_id, $langcode);

    if (!$options) {
      $form['info'] = [
        '#markup' => $this->t('There are no version to map this language to.'),
      ];

      // This should not really happen.
      return $form;
    }

    $form['update_mapping'] = [
      '#type' => 'select',
      '#title' => $this->t('Add mapping for @language', ['@language' => $language->getName()]),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form_state->set('message', $this->t('The mapping has been added.'));
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 100,
    ];

    $form_state->set('entity_type', $entity_type);
    $form_state->set('entity_id', $entity_id);
    $form_state->set('langcode', $langcode);

    return $form;
  }

}
