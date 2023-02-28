<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\RemoteTranslationProvider;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_epoetry\Event\AvailableLanguagesAlterEvent;
use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\ContactItem;
use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\ContactItemInterface;
use Drupal\oe_translation_epoetry\RequestFactory;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\LanguageCheckboxesAwareTrait;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\EPoetry\Request\Type\LinguisticRequestOut;
use Phpro\SoapClient\Type\ResultInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the ePoetry translator provider plugin.
 *
 * @RemoteTranslationProvider(
 *   id = "epoetry",
 *   label = @Translation("ePoetry"),
 *   description = @Translation("ePoetry translator provider plugin."),
 * )
 */
class Epoetry extends RemoteTranslationProviderBase {

  use LanguageCheckboxesAwareTrait;

  /**
   * The request factory.
   *
   * @var \Drupal\oe_translation_epoetry\RequestFactory
   */
  protected $requestFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, TranslationSourceManagerInterface $translationSourceManager, MessengerInterface $messenger, RequestFactory $requestFactory, StateInterface $state, EventDispatcherInterface $eventDispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $languageManager, $entityTypeManager, $translationSourceManager, $messenger);

    $this->requestFactory = $requestFactory;
    $this->state = $state;
    $this->eventDispatcher = $eventDispatcher;
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
      $container->get('oe_translation_epoetry.request_factory'),
      $container->get('state'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'contacts' => [],
      'auto_accept' => FALSE,
      'title_prefix' => '',
      'site_id' => '',
      'language_mapping' => [],
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $contacts = $this->configuration['contacts'];

    $form['contacts'] = [
      '#title' => $this->t('Default contacts'),
      '#description' => $this->t('Configure the default contacts to be used for every ePoetry request. These will be prefilled but can be overridden per request. Author, Requester, and Recipient will be mandatory for each request.'),
      '#type' => 'fieldset',
    ];

    foreach (ContactItem::contactTypes() as $type) {
      $form['contacts'][$type] = [
        '#type' => 'textfield',
        '#title' => $type,
        '#default_value' => $contacts[$type] ?? NULL,
      ];
    }

    $form['title_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request title prefix'),
      '#default_value' => $this->configuration['title_prefix'],
      '#description' => $this->t('This string will be prefixed to the title of every request sent. It should help identify the origin of the request.'),
      '#required' => TRUE,
    ];

    $form['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site ID'),
      '#default_value' => $this->configuration['site_id'],
      '#description' => $this->t('This site ID used in the title of translation requests.'),
      '#required' => TRUE,
    ];

    $form['auto_accept'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-accept translations'),
      '#description' => $this->t('If checked, all ePoetry translation requests will be auto-accepted and you cannot control this anymore at the individual request level.'),
      '#default_value' => $this->configuration['auto_accept'],
    ];

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

    $form['dossiers_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('ePoetry dossiers'),
    ];
    $form['dossiers_wrapper']['dossiers'] = [
      '#theme' => 'item_list',
      '#items' => [],
    ];

    $dossiers = array_reverse(RequestFactory::getEpoetryDossiers(), TRUE);
    $i = 1;
    foreach ($dossiers as $number => $dossier) {
      $form['dossiers_wrapper']['dossiers']['#items'][] = new FormattableMarkup('@current Number @number / Code @code / Year @year / Part @part @reset', [
        '@number' => $number,
        '@code' => $dossier['code'],
        '@year' => $dossier['year'],
        '@part' => $dossier['part'],
        '@current' => $i == 1 ? '[CURRENT]' : '',
        '@reset' => $i == 1 && isset($dossier['reset']) ? '[SET TO RESET]' : '',
      ]);

      $i++;
    }

    $form['dossiers_wrapper']['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset current dossier'),
      '#description' => $this->t('Check this box and save if you would like to reset the current dossier so that the next request generates a new dossier.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $contact_values = $form_state->getValue('contacts');
    $contacts = [];
    foreach ($contact_values as $contact => $value) {
      if ($value == "") {
        continue;
      }

      $contacts[$contact] = $value;
    }

    $this->configuration['contacts'] = $contacts;
    $this->configuration['auto_accept'] = (bool) $form_state->getValue('auto_accept');
    $this->configuration['title_prefix'] = $form_state->getValue('title_prefix');
    $this->configuration['site_id'] = $form_state->getValue('site_id');
    $this->configuration['language_mapping'] = $form_state->getValue('language_mapping');

    $reset_dossier = (bool) $form_state->getValue(['dossiers_wrapper', 'reset']);
    if ($reset_dossier) {
      $dossiers = array_reverse(RequestFactory::getEpoetryDossiers(), TRUE);
      $number = key($dossiers);
      $dossier = &$dossiers[$number];
      $dossier['reset'] = TRUE;
      RequestFactory::setEpoetryDossiers($dossiers);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    $entity = $this->getEntity();

    // Check if we have already made requests to ePoetry for this node, because
    // if we did, we need to use a different request type and inform the user.
    // To determine the last request, we first check the form state as it may
    // already be set by NewVersionCreateForm in case this is a request during
    // an ongoing one.
    $last_request = $form_state->get('epoetry_last_request');
    if (!$last_request) {
      $last_request = $this->getLastRequest($entity);
    }
    if ($last_request) {
      $form_state->set('epoetry_last_request', $last_request);
      $form['info'] = [
        '#markup' => $this->t('You are making a request for a new version. The previous version was translated with the <strong>@id</strong> request ID.', ['@id' => $last_request->getRequestId(TRUE)]),
      ];

      // Add an extra message if the previous version was rejected.
      if ($last_request->getEpoetryRequestStatus() === TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED) {
        $form['info']['#markup'] .= ' <strong>' . $this->t('The previous request had been rejected. You are now resubmitting the request, please ensure it is now valid.') . '</strong>';
      }
    }

    $languages = $this->languageManager->getLanguages();
    $event = new AvailableLanguagesAlterEvent($languages);
    $this->eventDispatcher->dispatch($event, AvailableLanguagesAlterEvent::NAME);
    $this->addLanguageCheckboxes($form, $form_state, $event->getLanguages());

    $request = $this->entityTypeManager->getStorage('oe_translation_request')->create([
      'bundle' => 'epoetry',
    ]);
    $form_state->set('request', $request);

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load('oe_translation_request.epoetry.default');
    $form_state->set('form_display', $form_display);

    foreach ($form_display->getComponents() as $name => $component) {
      $widget = $form_display->getRenderer($name);
      if (!$widget) {
        continue;
      }

      $items = $request->get($name);
      $items->filterEmptyItems();
      if ($name === 'contacts') {
        $widget->setSetting('multiple_values', FALSE);
      }
      $form[$name] = $widget->form($items, $form, $form_state);
      $form[$name]['#access'] = $items->access('edit');
    }

    // Disable the auto-accept feature if it's disabled at the plugin level.
    $form['auto_accept']['#disabled'] = $this->configuration['auto_accept'];
    if ($form['auto_accept']['#disabled']) {
      $form['auto_accept']['widget']['value']['#description'] .= '<strong>' . $this->t('. The auto-accept feature is enabled at site level. All requests will be auto-accepted.') . '</strong>';
    }

    // Ensure the deadline is in the future.
    $form['deadline']['widget'][0]['value']['#attributes']['min'] = (new \DateTime('today'))->format('Y-m-d');

    // Add the contact fields. These are the required for our requests.
    $types = [
      ContactItemInterface::RECIPIENT,
      ContactItemInterface::WEBMASTER,
      ContactItemInterface::EDITOR,
    ];

    $form['contacts'] = [
      '#title' => $this->t('Contacts'),
      '#type' => 'fieldset',
    ];

    foreach ($types as $type) {
      $form['contacts'][$type] = [
        '#type' => 'textfield',
        '#title' => $type,
        '#default_value' => $this->configuration['contacts'][$type] ?? NULL,
        '#required' => TRUE,
      ];
    }
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

    $message = $form_state->getValue('message');
    if (!empty($message[0]['value'])) {
      $length = mb_strlen($message[0]['value']);
      if ($length > 1699) {
        $form_state->setErrorByName('message', $this->t('Please keep the message under 1700 characters.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitRequestToProvider(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $form_state->get('form_display');
    $request = $form_state->get('request');
    $form_display->extractFormValues($request, $form, $form_state);

    $values = $form_state->getValues();

    $languages = $this->getSubmittedLanguages($form, $form_state);

    $entity = $this->getEntity();
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')->create([
      'bundle' => 'epoetry',
      'source_language_code' => $entity->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => $form_state->get('translator_id'),
      'request_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE,
    ]);

    $request->setContentEntity($entity);
    $data = $this->translationSourceManager->extractData($entity->getUntranslated());
    $request->setData($data);
    $request->setAutoAccept((bool) $values['auto_accept']['value']);
    $request->setAutoSync((bool) $values['auto_sync']['value']);
    $request->setDeadline($values['deadline'][0]['value']);
    $request->setMessage($values['message'][0]['value']);
    $request->setContacts($values['contacts']);

    // Save the request before dispatching it to ePoetry.
    $request->save();

    // Send it to ePoetry and update its status.
    try {
      $this->createAndSendRequestObject($request, $form, $form_state);
    }
    catch (\Throwable $exception) {
      $request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED);
      $this->messenger->addError($this->t('There was a problem sending the request to ePoetry.'));
      $request->log('@type: <strong>@message</strong>', [
        '@type' => get_class($exception),
        '@message' => $exception->getMessage(),
      ], TranslationRequestLogInterface::ERROR);
    }
    $request->save();
  }

  /**
   * Creates and sends the request object to ePoetry.
   *
   * The type of request depends on the scenario:
   *
   * - if we are making the first request, we use the CreateLinguisticRequest
   * - if we are making a new request for an entity for which we have already
   * made requests, we use the CreateNewVersion request. If we reach 99
   * versions, we reset and again use CreateLinguisticRequest.
   * - if we are making a new request to a node for which we haven't made a
   * request before, we use the addNewPartToDossier request to add a new part
   * to the existing dossier (whose number we keep in State). If we reach 30
   * parts, we reset and use again the CreateLinguisticRequest to create a
   * new dossier.
   * - if we are making a request for a node for which a request had been made
   * before BUT was rejected by ePoetry, we use resubmitRequest to try it
   * again under the same number/part/version.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function createAndSendRequestObject(TranslationRequestEpoetryInterface $request, array $form, FormStateInterface $form_state): void {
    $last_request = $form_state->get('epoetry_last_request');
    if (!$last_request instanceof TranslationRequestEpoetryInterface || $this->dossierResetNeeded()) {
      $dossiers = RequestFactory::getEpoetryDossiers();
      // If we are not making a new version request, we need to check if
      // we have a number set in the system, we need to add a new part
      // the existing dossier for any new nodes. This is because that is how
      // ePoetry expects we send requests. Of course, if we don't need to reset
      // the dossier.
      if ($this->newPartNeeded($request, $form, $form_state) && !$this->dossierResetNeeded()) {
        $object = $this->requestFactory->addNewPartToDossierRequest($request);
        $response = $this->requestFactory->getRequestClient()->addNewPartToDossier($object);
        // If we made an addPartToDossier request, we need to increment the
        // part in the dossier stored in our State system.
        $dossier = $response->getReturn()->getRequestReference()->getDossier();
        $number = $dossier->getNumber();
        $dossiers[$number]['part'] = $dossiers[$number]['part'] + 1;
        RequestFactory::setEpoetryDossiers($dossiers);
        $request->log('The request has been sent successfully using the <strong>addNewPartToDossierRequest</strong> request type.');
        $this->logInformativeMessages($response, $request);
      }
      else {
        $object = $this->requestFactory->createLinguisticRequest($request);
        $response = $this->requestFactory->getRequestClient()->createLinguisticRequest($object);
        // If we made a new CreateLinguisticRequest, it means we started a new
        // dossier so keep the generated number in the state. Together, we will
        // keep also the last generated part for this number. But first, we just
        // initialize it.
        $dossier = $response->getReturn()->getRequestReference()->getDossier();
        $number = $dossier->getNumber();
        $dossiers[$number] = [
          'part' => 0,
          'code' => $dossier->getRequesterCode(),
          'year' => $dossier->getYear(),
        ];
        $request->log('The request has been sent successfully using the <strong>createLinguisticRequest</strong> request type. The dossier number started is <strong>@number</strong>.', ['@number' => $dossier->getNumber()]);
        $this->logInformativeMessages($response, $request);
        RequestFactory::setEpoetryDossiers($dossiers);
      }
    }
    else {
      // If we are dealing with an existing translation request, we need to
      // check if it was rejected, because if it was, we need to use
      // resubmitRequest instead of createNewVersion.
      if ($last_request->getEpoetryRequestStatus() !== TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED) {
        $object = $this->requestFactory->createNewVersionRequest($request, $last_request);
        $response = $this->requestFactory->getRequestClient()->createNewVersion($object);
        $request->log('The request has been sent successfully using the <strong>createNewVersionRequest</strong> request type.');
        $this->logInformativeMessages($response, $request);
      }
      else {
        $object = $this->requestFactory->resubmitRequest($request, $last_request);
        $response = $this->requestFactory->getRequestClient()->resubmitRequest($object);
        $request->log('The request has been sent successfully using the <strong>resubmitRequest</strong> request type.');
        $this->logInformativeMessages($response, $request);
      }

      if ($form_state->get('create_new_version_ongoing')) {
        // If we are creating a new version request from an ongoing state,
        // mark the old request as finished.
        // @see NewVersionCreateForm.
        $this->messenger->addStatus($this->t('The old request with the id <strong>@old</strong> has been marked as Finished.', ['@old' => $last_request->getRequestId(TRUE)]));
        $last_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
        $request->set('update_of', $last_request);
        $request->log('The request has replaced an ongoing translation request which has now been marked as Finished: <strong>@last_request</strong>.', ['@last_request' => $last_request->getRequestId(TRUE)]);
        $last_request->log('This request has been marked as Finished and has been replaced by an updated one: <strong>@new_request</strong>', ['@new_request' => $request->toLink(t('New request'))->toString()]);
        $last_request->save();
      }
    }

    $request_id = static::requestIdFromResponse($response->getReturn());
    $request->setRequestId($request_id);

    // At this stage, the request still stays active. However, we set the
    // status coming from ePoetry for its specific request status.
    $request->setEpoetryRequestStatus($response->getReturn()->getRequestDetails()->getStatus());
    $this->messenger->addStatus($this->t('The translation request has been sent to ePoetry.'));
  }

  /**
   * Determines if we need to make a request by adding a new part to a dossier.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   Whether we add a new part to a dossier.
   */
  protected function newPartNeeded(TranslationRequestEpoetryInterface $request, array $form, FormStateInterface $form_state): bool {
    $dossiers = RequestFactory::getEpoetryDossiers();
    if (empty($dossiers)) {
      // If we don't yet have any numbers, we create a new linguistic request.
      return FALSE;
    }

    $dossiers = array_reverse($dossiers, TRUE);
    $dossier = current($dossiers);
    // We check the last number and if it's corresponding last part is 30,
    // we reset.
    // Before returning, set the reference values onto the request so we can
    // use in the RequestFactory.
    $request->setRequestId([
      'code' => $dossier['code'],
      'year' => $dossier['year'],
      'number' => key($dossiers),
      'version' => 0,
      'part' => 0,
      'service' => 'TRA',
    ]);
    return $dossier['part'] < 30;
  }

  /**
   * Checks if we need to reset the current dossier.
   *
   * This happens when an admin edits the poetry translator and indicates that
   * the current dossier needs to be reset so that the immediate next request
   * because a createLinguisticRequest.
   *
   * @return bool
   *   Whether to reset the current dossier.
   */
  protected function dossierResetNeeded(): bool {
    $dossiers = RequestFactory::getEpoetryDossiers();
    if (empty($dossiers)) {
      return FALSE;
    }

    $dossiers = array_reverse($dossiers, TRUE);
    $dossier = current($dossiers);
    return isset($dossier['reset']);
  }

  /**
   * Returns the request ID array from a given linguistic request response.
   *
   * @param \OpenEuropa\EPoetry\Request\Type\LinguisticRequestOut $linguistic_request_out
   *   The outgoing linguistic request object.
   *
   * @return array
   *   The request ID values.
   */
  public static function requestIdFromResponse(LinguisticRequestOut $linguistic_request_out): array {
    $reference = $linguistic_request_out->getRequestReference();
    $dossier = $reference->getDossier();
    $year = $dossier->getYear();
    $number = $dossier->getNumber();
    $code = $dossier->getRequesterCode();
    $part = $reference->getPart();
    $version = $reference->getVersion();
    $type = $reference->getProductType();
    return [
      'code' => $code,
      'year' => $year,
      'number' => $number,
      'version' => $version,
      'part' => $part,
      'service' => $type,
    ];
  }

  /**
   * Returns the last translation requested sent for this entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface|null
   *   The request entity or NULL if none.
   */
  protected function getLastRequest(ContentEntityInterface $entity): ?TranslationRequestEpoetryInterface {
    $ids = $this->entityTypeManager->getStorage('oe_translation_request')
      ->getQuery()
      ->condition('content_entity__entity_type', $entity->getEntityTypeId())
      ->condition('content_entity__entity_id', $entity->id())
      ->condition('bundle', 'epoetry')
      // Filter out the requests which have failed because those don't have
      // any request IDs. Other statuses we don't need to include as by this
      // point we already checked there is nothing ongoing.
      ->condition('request_status', [
        TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED,
        TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
      ], 'NOT IN')
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();

    if (!$ids) {
      return NULL;
    }

    $id = reset($ids);
    return $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
  }

  /**
   * Logs the ePoetry informative messages from the response.
   *
   * @param \Phpro\SoapClient\Type\ResultInterface $response
   *   The response.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The local translation request.
   */
  protected function logInformativeMessages(ResultInterface $response, TranslationRequestEpoetryInterface $request): void {
    if ($response->getReturn()->getInformativeMessages()->hasMessage()) {
      foreach ($response->getReturn()->getInformativeMessages()->getMessage() as $message) {
        $request->log((new FormattableMarkup('Message from ePoetry: <strong>@message</strong>', ['@message' => $message]))->__toString());
      }
    }
  }

}
