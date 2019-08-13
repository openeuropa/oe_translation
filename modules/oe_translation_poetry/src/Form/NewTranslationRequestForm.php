<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

/**
 * Form for requesting new translations.
 */
class NewTranslationRequestForm extends PoetryCheckoutFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_poetry_new_translation_request';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestOperation(): string {
    return 'INSERT';
  }

}
