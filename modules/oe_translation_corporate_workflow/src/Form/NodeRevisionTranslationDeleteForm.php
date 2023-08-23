<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Form\NodeRevisionDeleteForm;

/**
 * Handles the deletion of node revisions.
 *
 * This class only "kicks in" if we are attempting to delete a revision
 * translation. Otherwise, it defers back to the parent.
 *
 * In case we are deleting a translation, we need to ensure only the translation
 * of that revision gets cleared without making any other change to the node
 * or the revision.
 */
class NodeRevisionTranslationDeleteForm extends NodeRevisionDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->revision->isDefaultTranslation()) {
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
    if ($this->revision->isDefaultTranslation()) {
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

    $form_state->setRedirect(
      'entity.node.revision',
      [
        'node' => $this->revision->id(),
        'node_revision' => $this->revision->getRevisionId(),
      ]
    );
  }

}
