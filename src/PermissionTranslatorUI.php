<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_local\LocalTranslatorUi;

/**
 * UI for the PermissionTranslator plugin.
 */
class PermissionTranslatorUI extends LocalTranslatorUi {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job) {
    // Cannot inject into this class because it's a "plugin" without a manager.
    // @see TranslationManager::createUIInstance().
    /** @var \Drupal\oe_translation\Plugin\tmgmt\Translator\PermissionTranslator $plugin */
    $plugin = \Drupal::service('plugin.manager.tmgmt.translator')->createInstance($job->getTranslator()->getPluginId());
    $allowed_users = $plugin->getAllowedUsers();
    if (!$allowed_users) {
      $form['message'] = [
        '#plain_text' => $this->t('There are no users available to assign.'),
      ];

      return $form;
    }

    $names = [];
    foreach ($allowed_users as $user) {
      $names[$user->id()] = $user->getDisplayName();
    }

    $form['translator'] = [
      '#title' => $this->t('Assign to'),
      '#type' => 'select',
      '#options' => $names,
      '#default_value' => $job->getSetting('translator'),
    ];

    return $form;
  }

}
