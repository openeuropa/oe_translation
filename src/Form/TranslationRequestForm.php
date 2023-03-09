<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the translation request entity add/edit forms.
 */
class TranslationRequestForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New translation request %label has been created.', $message_arguments));
      $this->logger('oe_translation_request')->notice('Created new translation request %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The translation request %label has been updated.', $message_arguments));
      $this->logger('oe_translation_request')->notice('Updated new translation request %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.oe_translation_request.canonical', ['oe_translation_request' => $entity->id()]);
  }

  /**
   * Validates that the element is not longer than the max length.
   *
   * @param array $element
   *   The input element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see TranslationFormTrait::translationFormElement()
   */
  public static function validateMaxLength(array $element, FormStateInterface &$form_state): void {
    if (isset($element['#max_length']) && ($element['#max_length'] < mb_strlen($element['#value']))) {
      $form_state->setError($element,
          t('The field has @size characters while the limit is @limit.', [
            '@size' => mb_strlen($element['#value']),
            '@limit' => $element['#max_length'],
          ])
        );
    }
  }

}
