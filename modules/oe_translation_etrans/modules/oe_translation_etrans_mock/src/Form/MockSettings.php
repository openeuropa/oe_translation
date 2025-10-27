<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans_mock\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mock settings to configure Etrans for testing with the mock.
 *
 * We use this to test on remote environments where we cannot control the
 * environment variables.
 */
class MockSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_etrans_mock_mock_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_translation_etrans_mock.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['delivery_endpoint'] = [
      '#type' => 'textfield',
      '#maxlength' => 1000,
      '#title' => $this->t('Delivery endpoint'),
      '#default_value' => $this->config('oe_translation_etrans_mock.settings')->get('delivery_endpoint'),
    ];
    $form['failure_endpoint'] = [
      '#type' => 'textfield',
      '#maxlength' => 1000,
      '#title' => $this->t('Failure endpoint'),
      '#default_value' => $this->config('oe_translation_etrans_mock.settings')->get('failure_endpoint'),
    ];

    $form['disable_middleware'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable middleware'),
      '#description' => $this->t('Check this box if you want to be able to send external requests to the actual etrans endpoint.'),
      '#default_value' => $this->config('oe_translation_etrans_mock.settings')->get('disable_middleware'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('oe_translation_etrans_mock.settings')
      ->set('delivery_endpoint', $form_state->getValue('delivery_endpoint'))
      ->set('failure_endpoint', $form_state->getValue('failure_endpoint'))
      ->set('disable_middleware', $form_state->getValue('disable_middleware'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
