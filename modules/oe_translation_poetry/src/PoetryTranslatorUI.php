<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;

/**
 * UI class for the Poetry translator.
 */
class PoetryTranslatorUI extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @todo build form for configuration of the default stuff.
    return $form;
  }

}
