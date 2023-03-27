<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation\Event\AvailableLanguagesAlterEvent;
use Drupal\oe_translation_epoetry\RequestFactory;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\LanguageCheckboxesAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the form for modifying the request, i.e. adding new languages.
 */
class ModifyLinguisticRequestForm extends FormBase {

  use LanguageCheckboxesAwareTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request factory.
   *
   * @var \Drupal\oe_translation_epoetry\RequestFactory
   */
  protected $requestFactory;

  /**
   * The entity being translated.
   *
   * We store it here so that the LanguageCheckboxesAwareTrait can use it.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a ModifyLinguisticRequestForm form.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\oe_translation_epoetry\RequestFactory $requestFactory
   *   The request factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(MessengerInterface $messenger, LanguageManagerInterface $languageManager, RequestFactory $requestFactory, EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher) {
    $this->languageManager = $languageManager;
    $this->requestFactory = $requestFactory;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('oe_translation_epoetry.request_factory'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Access callback for the modifyLinguisticRequest request.
   *
   * The request can be made only if the user has the appropriate permission
   * and the request has the correct status.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function access(TranslationRequestEpoetryInterface $translation_request, AccountInterface $account): AccessResultInterface {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    $cache->addCacheableDependency($translation_request);

    if (!$account->hasPermission('translate any entity')) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    // We only allow to add new languages if the request is accepted or
    // suspended.
    $states = [
      TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
    ];
    $allowed = in_array($translation_request->getEpoetryRequestStatus(), $states) && $translation_request->getRequestStatus() === TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED;
    if ($allowed) {
      return AccessResult::allowed()->addCacheableDependency($cache);
    }

    return AccessResult::forbidden()->addCacheableDependency($cache);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'modify_linguistic_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TranslationRequestEpoetryInterface $translation_request = NULL) {
    if (!$translation_request) {
      throw new NotFoundHttpException();
    }

    $form['#tree'] = TRUE;

    $form['info'] = [
      '#markup' => $this->t('You are making a request to add extra languages to an existing, ongoing request with the ID <strong>@id</strong>.', ['@id' => $translation_request->getRequestId(TRUE)]),
    ];

    $this->entity = $translation_request->getContentEntity();
    $form_state->set('translation_request', $translation_request);
    $languages = $this->languageManager->getLanguages();
    $event = new AvailableLanguagesAlterEvent($languages);
    $this->eventDispatcher->dispatch($event, AvailableLanguagesAlterEvent::NAME);
    $this->addLanguageCheckboxes($form, $form_state, $event->getLanguages());

    // Disable the languages that have been already requested.
    $languages = $translation_request->getTargetLanguages();
    foreach ($languages as $language_with_status) {
      $form['languages'][$language_with_status->getLangcode()]['#disabled'] = TRUE;
      $form['languages'][$language_with_status->getLangcode()]['#default_value'] = TRUE;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request */
    $translation_request = $form_state->get('translation_request');
    $new_languages = $this->getSubmittedLanguages($form, $form_state);
    $new_language_labels = [];
    foreach ($new_languages as $new_language) {
      $new_language_labels[] = $this->languageManager->getLanguage($new_language['langcode'])->getName();
    }

    try {
      // Backup the existing target languages and set only the new ones onto the
      // entity because the constructed request must include only those.
      $target_languages = $translation_request->getTargetLanguages();
      $translation_request->set('target_languages', []);
      foreach ($new_languages as $delta => $values) {
        $translation_request->updateTargetLanguageStatus($values['langcode'], $values['status']);
      }

      $object = $this->requestFactory->modifyLinguisticRequestRequest($translation_request);
      $response = $this->requestFactory->getRequestClient()->modifyLinguisticRequest($object);
      // Set back the target languages we removed before making the request.
      foreach ($translation_request->getTargetLanguages() as $language_with_status) {
        $target_languages[$language_with_status->getLangcode()] = $language_with_status;
      }
      $translation_request->set('target_languages', array_values($target_languages));

      // Log the request.
      $translation_request->log('The <strong>modifyLinguisticRequest</strong> has been sent to ePoetry for adding the following extra languages: <strong>@languages</strong>.', ['@languages' => implode(', ', $new_language_labels)]);
      if ($response->getReturn()->getInformativeMessages()->hasMessage()) {
        foreach ($response->getReturn()->getInformativeMessages()->getMessage() as $message) {
          $translation_request->log((new FormattableMarkup('Message from ePoetry: <strong>@message</strong>', ['@message' => $message]))->__toString());
        }
      }
      // We save without updating the status of the request.
      $translation_request->save();
      $this->messenger->addStatus($this->t('The translation request has been sent to ePoetry.'));
    }
    catch (\Throwable $exception) {
      $this->messenger->addError($this->t('There was a problem sending the request to ePoetry.'));

      // Reload the translation request from storage to ensure we are clearing
      // all the values we set in the try{} block as we don't want to save it
      // and include the language changes because the request failed.
      $this->entityTypeManager->getStorage('oe_translation_request')->resetCache();
      $translation_request = $this->entityTypeManager->getStorage('oe_translation_request')->load($translation_request->id());

      // We log but we don't change any statuses.
      $translation_request->log('There was an error with the <strong>modifyLinguisticRequest</strong> for adding the following extra languages: <strong>@languages</strong>. The error: @error', [
        '@languages' => implode(', ', $new_language_labels),
        '@error' => $exception->getMessage(),
      ], TranslationRequestLogInterface::ERROR);

      $translation_request->save();
    }
  }

  /**
   * Returns the translated entity, as expected by LanguageCheckboxesAwareTrait.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  protected function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

}
