<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote_test\Plugin\RemoteTranslationProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\oe_translation_remote_test\TranslationRequestTestRemote;

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

  /**
   * {@inheritdoc}
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    $languages = $this->languageManager->getLanguages();
    $entity = $this->getEntity();
    $source_language = $entity->language()->getId();
    unset($languages[$source_language]);

    $form['languages'] = [];
    foreach ($languages as $language) {
      $form['languages'][$language->getId()] = [
        '#type' => 'checkbox',
        '#title' => $language->getName(),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitRequestToProvider(FormStateInterface $form_state): void {
    $language_values = $form_state->getValue('languages');
    $languages = [];
    foreach ($language_values as $langcode => $value) {
      if ($value === 1) {
        $languages[] = [
          'langcode' => $langcode,
          'status' => TranslationRequestTestRemote::STATUS_LANGUAGE_ACTIVE,
        ];
      }
    }

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
