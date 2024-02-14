<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_mock\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mock settings to configure ePoetry for testing with the mock.
 *
 * We use this to test on remote environments where we cannot control the
 * environment variables.
 */
class MockSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_epoetry_mock_mock_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['oe_translation_epoetry_mock.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#maxlength' => 1000,
      '#title' => $this->t('Endpoint'),
      '#default_value' => $this->config('oe_translation_epoetry_mock.settings')->get('endpoint'),
    ];

    $form['notifications_endpoint'] = [
      '#type' => 'textfield',
      '#maxlength' => 1000,
      '#title' => $this->t('Notifications endpoint'),
      '#default_value' => $this->config('oe_translation_epoetry_mock.settings')->get('notifications_endpoint'),
    ];

    $form['notifications_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notifications username'),
      '#default_value' => $this->config('oe_translation_epoetry_mock.settings')->get('notifications_username'),
    ];

    $form['notifications_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notifications password'),
      '#default_value' => $this->config('oe_translation_epoetry_mock.settings')->get('notifications_password'),
    ];

    $form['application_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application'),
      '#default_value' => $this->config('oe_translation_epoetry_mock.settings')->get('application_name'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('oe_translation_epoetry_mock.settings')
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('application_name', $form_state->getValue('application_name'))
      ->set('notifications_endpoint', $form_state->getValue('notifications_endpoint'))
      ->set('notifications_username', $form_state->getValue('notifications_username'))
      ->set('notifications_password', $form_state->getValue('notifications_password'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
