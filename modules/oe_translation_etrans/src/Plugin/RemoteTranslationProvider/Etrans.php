<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans\Plugin\RemoteTranslationProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation\Event\AvailableLanguagesAlterEvent;
use Drupal\oe_translation\LanguageMapper;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_content_formatter\ContentFormatter\ContentFormatterInterface;
use Drupal\oe_translation_etrans\EtransClient;
use Drupal\oe_translation_etrans\EtransRequest;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Drupal\oe_translation_remote\LanguageCheckboxesAwareTrait;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the eTrans translator provider plugin.
 *
 * @RemoteTranslationProvider(
 *   id = "etrans",
 *   label = @Translation("eTrans"),
 *   description = @Translation("eTrans translator provider plugin."),
 * )
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Etrans extends RemoteTranslationProviderBase {

  use LanguageCheckboxesAwareTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The etrans API client.
   *
   * @var \Drupal\oe_translation_etrans\EtransClient
   */
  protected $etransClient;

  /**
   * The translation content formatter.
   *
   * @var \Drupal\oe_translation_content_formatter\ContentFormatter\ContentFormatterInterface
   */
  protected $contentFormatter;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, TranslationSourceManagerInterface $translationSourceManager, MessengerInterface $messenger, EventDispatcherInterface $eventDispatcher, EtransClient $etransClient, ContentFormatterInterface $contentFormatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $languageManager, $entityTypeManager, $translationSourceManager, $messenger);

    $this->eventDispatcher = $eventDispatcher;
    $this->etransClient = $etransClient;
    $this->contentFormatter = $contentFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('oe_translation.translation_source_manager'),
      $container->get('messenger'),
      $container->get('event_dispatcher'),
      $container->get('oe_translation_etrans.client'),
      $container->get('oe_translation_content_formatter.html_formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'language_mapping' => [],
      'domain' => 'GEN',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $default_configuration = $this->defaultConfiguration();
    // Only include configuration that is defined in the default configuration
    // array.
    $configuration = array_filter($configuration, function ($value, $key) use ($default_configuration) {
      return isset($default_configuration[$key]);
    }, ARRAY_FILTER_USE_BOTH);
    $this->configuration = $configuration + $default_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess(?AccountInterface $account = NULL): AccessResultInterface {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['language_mapping'] = [
      '#title' => $this->t('Language mapping'),
      '#type' => 'fieldset',
    ];

    foreach ($this->languageManager->getLanguages() as $language) {
      $form['language_mapping'][$language->getId()] = [
        '#type' => 'textfield',
        '#title' => $language->getName(),
        '#default_value' => $this->configuration['language_mapping'][$language->getId()] ?? strtoupper($language->getId()),
      ];
    }

    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#description' => $this->t('The domain to use when translating'),
      '#default_value' => $this->configuration['domain'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['language_mapping'] = $form_state->getValue('language_mapping');
    $this->configuration['domain'] = $form_state->getValue('domain');
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    $languages = $this->languageManager->getLanguages();
    $event = new AvailableLanguagesAlterEvent($languages);
    $this->eventDispatcher->dispatch($event, AvailableLanguagesAlterEvent::NAME);
    $this->addLanguageCheckboxes($form, $form_state, $event->getLanguages());

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
    $request = $this->entityTypeManager->getStorage('oe_translation_request')->create([
      'bundle' => 'etrans',
      'source_language_code' => $entity->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => $form_state->get('translator_id'),
      'request_status' => TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED,
    ]);

    $request->setContentEntity($entity);
    $data = $this->translationSourceManager->extractData($entity->getUntranslated());
    $request->setData($data);

    // Save the request before dispatching it to etrans.
    $request->save();

    // Send it to etrans and update its status.
    try {
      $this->createAndSendRequestObject($request, $form, $form_state);
    }
    catch (\Throwable $exception) {
      $request->setRequestStatus(TranslationRequestEtransInterface::STATUS_REQUEST_FAILED);
      $this->messenger->addError($this->t('There was a problem sending the etrans request to DGT.'));
      $message = $exception->getMessage();
      $request->log('@type: <strong>@message</strong>', [
        '@type' => get_class($exception),
        '@message' => $message,
      ], TranslationRequestLogInterface::ERROR);
    }
    $request->save();
  }

  /**
   * Creates and sends the request object to eTrans.
   *
   * @param \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $request
   *   The translation request.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function createAndSendRequestObject(TranslationRequestEtransInterface $request, array $form, FormStateInterface $form_state): void {
    $exported = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', (string) $this->contentFormatter->export($request));
    $entity = $request->getContentEntity();

    $source_language = LanguageMapper::getMappedLanguageCode($entity->language()->getId(), $request);
    $target_languages = [];
    foreach ($request->getTargetLanguages() as $language_with_status) {
      $target_languages[] = LanguageMapper::getMappedLanguageCode($language_with_status->getLangcode(), $request);
    }

    $client_request = new EtransRequest($request, $exported, $source_language, $target_languages);
    $client_request->setDomain($this->configuration['domain']);
    $response = $this->etransClient->sendRequest($client_request);
    if ($response->getStatusCode() !== 200) {
      throw new \Exception(sprintf('The response status code was not 200 but instead %s.', $response->getStatusCode()));
    }
    $contents = $response->getBody()->getContents();
    $content = json_decode($contents);
    if (!$content || !isset($content->requestId)) {
      throw new \Exception(sprintf('The response did not include a valid content with a request ID: %s', serialize($contents)));
    }

    $request->setRemoteId((string) $content->requestId);
    $request->save();
    $this->messenger->addStatus($this->t('The etrans request has been sent to DGT.'));
  }

}
