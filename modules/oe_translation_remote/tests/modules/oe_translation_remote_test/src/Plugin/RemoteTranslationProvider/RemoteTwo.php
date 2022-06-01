<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote_test\Plugin\RemoteTranslationProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;

/**
 * Provides a test Remote translator provider plugin.
 *
 * @RemoteTranslationProvider(
 *   id = "remote_two",
 *   label = @Translation("Remote two"),
 *   description = @Translation("Remote two translator provider plugin."),
 * )
 */
class RemoteTwo extends RemoteTranslationProviderBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'configuration_string' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['configuration_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('A test configuration string'),
      '#default_value' => $this->configuration['configuration_string'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['configuration_string'] = $form_state->getValue([
      'configuration_string',
    ]);
  }

}
