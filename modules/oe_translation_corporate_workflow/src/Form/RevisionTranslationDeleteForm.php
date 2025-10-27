<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\Form;

use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Handles the deletion of entity revisions.
 *
 * This class only "kicks in" if we are attempting to delete a revision
 * translation. Otherwise, it defers back to the parent.
 *
 * In case we are deleting a translation, we need to ensure only the translation
 * of that revision gets cleared without making any other change to the node
 * or the revision.
 *
 * For nodes, it's handled in NodeRevisionTranslationDeleteForm.
 */
class RevisionTranslationDeleteForm extends RevisionDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if (!$this->applies()) {
      return parent::getQuestion();
    }

    return $this->t('Are you sure you want to delete the %language translation of the revision from %revision-date?', [
      '%language' => $this->revision->language()->getName(),
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->applies()) {
      parent::submitForm($form, $form_state);
      return;
    }

    $language = $this->revision->language();
    $untranslated_revision = $this->revision->getUntranslated();
    $untranslated_revision->removeTranslation($language->getId());
    $untranslated_revision->setNewRevision(FALSE);
    $untranslated_revision->save();

    $this->logger('content')->notice('@type: deleted %title revision %revision translation in %language.', [
      '@type' => $this->revision->bundle(),
      '%title' => $this->revision->label(),
      '%revision' => $this->revision->getRevisionId(),
      '%language' => $language->getName(),
    ]);
    $this->messenger()->addStatus($this->t('Revision translation in %language from %revision-date of %title has been deleted.', [
      '%language' => $language->getName(),
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
      '%title' => $this->revision->label(),
    ]));

    $url = $untranslated_revision->toUrl('revision');
    $form_state->setRedirectUrl($url);
  }

  /**
   * Checks if the logic of this form override should apply to the revision.
   *
   * We only apply if it's using the corporate workflow and it's trying to
   * delete a specific translation.
   *
   * @return bool
   *   Whether it applies.
   */
  protected function applies() {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = \Drupal::service('content_moderation.moderation_information')->getWorkflowForEntity($this->revision);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      return FALSE;
    }

    if ($this->revision->isDefaultTranslation()) {
      return FALSE;
    }

    return TRUE;
  }

}
