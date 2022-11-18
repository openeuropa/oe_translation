<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote_test\Plugin\RemoteTranslationProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation_remote\LanguageCheckboxesAwareTrait;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Provides a test Remote translator provider plugin.
 *
 * @RemoteTranslationProvider(
 *   id = "remote_one",
 *   label = @Translation("Remote one"),
 *   description = @Translation("Remote one translator provider plugin."),
 * )
 */
class RemoteOne extends RemoteTranslationProviderBase {

  use LanguageCheckboxesAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    $this->addLanguageCheckboxes($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateRequest(array &$form, FormStateInterface $form_state): void {
    // Validate that at least one language is selected.
    $languages = $this->getSubmittedLanguages($form, $form_state);
    if (!$languages) {
      $form_state->setErrorByName('languages', $this->t('Please select at least one language.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitRequestToProvider(array &$form, FormStateInterface $form_state): void {
    $languages = $this->getSubmittedLanguages($form, $form_state);

    $entity = $this->getEntity();
    /** @var \Drupal\oe_translation_remote_test\TranslationRequestTestRemote $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')->create([
      'bundle' => 'test_remote',
      'source_language_code' => $entity->language()->getId(),
      'target_languages' => $languages,
      'request_status' => TranslationRequestRemoteInterface::STATUS_REQUEST_ACTIVE,
      'translator_provider' => $form_state->get('translator_id'),
    ]);

    $request->setContentEntity($entity);
    $data = $this->translationSourceManager->extractData($entity->getUntranslated());
    $request->setData($data);
    $request->save();
    $this->messenger->addStatus($this->t('The translation request has been sent.'));
  }

}
