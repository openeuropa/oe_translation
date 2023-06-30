<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation\Event\TranslationSynchronisationEvent;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles the synchronization of the translation data onto the node.
 */
class RemoteTranslationSynchroniser {

  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The translation source manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationSourceManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a RemoteTranslationSynchroniser.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\oe_translation\TranslationSourceManagerInterface $translationSourceManager
   *   The translation source manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(LanguageManagerInterface $languageManager, TranslationSourceManagerInterface $translationSourceManager, MessengerInterface $messenger, EventDispatcherInterface $eventDispatcher) {
    $this->languageManager = $languageManager;
    $this->translationSourceManager = $translationSourceManager;
    $this->messenger = $messenger;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Saves the translated data onto the node from a translation request.
   *
   * @param \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $translation_request
   *   The translation request.
   * @param string $language_code
   *   The language code.
   * @param bool $auto
   *   Whether the sync happened automatically.
   */
  public function synchronise(TranslationRequestRemoteInterface $translation_request, string $language_code, bool $auto = FALSE): void {
    $entity = $translation_request->getContentEntity();
    $language = $this->languageManager->getLanguage($language_code);
    $data = $translation_request->getTranslatedData();
    $language_data = $data[$language->getId()] ?? [];
    if (!$language_data) {
      $translation_request->log('An attempt to @auto sync the <strong>@language</strong> translation has failed because there was no data to synchronise.', [
        '@language' => $language->getName(),
        '@auto' => $auto ? 'automatically' : '',
      ], TranslationRequestLogInterface::ERROR);
      $translation_request->save();
      $this->messenger->addError($this->t('There was no data to synchronise.'));

      return;
    }

    $language_data['#translation_request'] = $translation_request;
    $saved = $this->translationSourceManager->saveData($language_data, $entity, $language->getId(), TRUE, $translation_request->getData());
    if (!$saved) {
      $translation_request->log('An attempt to sync @auto the <strong>@language</strong> translation has failed.', [
        '@language' => $language->getName(),
        '@auto' => $auto ? 'automatically' : '',
      ], TranslationRequestLogInterface::ERROR);
      $translation_request->save();
      $this->messenger->addError($this->t('There was a problem synchronising the translation. Check the global site logs for the error.'));

      return;
    }

    $translation_request->updateTargetLanguageStatus($language->getId(), TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED);
    $translation_request->log('The <strong>@language</strong> translation has been @autosynchronised with the content.', [
      '@language' => $language->getName(),
      '@auto' => $auto ? 'automatically ' : '',
    ]);
    $translation_request->save();
    $this->messenger->addStatus($this->t('The translation in @language has been synchronised.', ['@language' => $language->getName()]));
    $event = new TranslationSynchronisationEvent($entity, $translation_request, $language_code);
    $this->eventDispatcher->dispatch($event, TranslationSynchronisationEvent::NAME);
  }

}
