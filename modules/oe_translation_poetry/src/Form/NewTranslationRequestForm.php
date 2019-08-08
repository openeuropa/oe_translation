<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

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
  protected function getRequestType() {
    return 'INSERT';
  }
}
