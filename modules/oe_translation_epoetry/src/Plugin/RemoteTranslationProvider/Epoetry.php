<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\RemoteTranslationProvider;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\ContactItem;
use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\ContactItemInterface;
use Drupal\oe_translation_epoetry\RequestFactory;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\LanguageCheckboxesAwareTrait;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\EPoetry\Request\Type\LinguisticRequestOut;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, TranslationSourceManagerInterface $translationSourceManager, MessengerInterface $messenger, RequestFactory $requestFactory, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $languageManager, $entityTypeManager, $translationSourceManager, $messenger);

    $this->requestFactory = $requestFactory;
    $this->state = $state;
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
      $container->get('state')
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
    ] + parent::defaultConfiguration();
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
  }

  /**
   * {@inheritdoc}
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    $languages = $this->languageManager->getLanguages();
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
    }

    $source_language = $entity->language()->getId();
    unset($languages[$source_language]);

    $form['languages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Languages'),
    ];
    foreach ($languages as $language) {
      $form['languages'][$language->getId()] = [
        '#type' => 'checkbox',
        '#title' => $language->getName(),
      ];
    }

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

    // Save the request before dispatching it to DGT.
    $request->save();

    // Send it to DGT and update its status.
    try {
      $this->createAndSendRequestObject($request, $form, $form_state);
    }
    catch (\Throwable $exception) {
      // @todo handle error.
      $request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED);
      $this->messenger->addError($this->t('There was a problem sending the request to DGT.'));
      watchdog_exception('epoetry', $exception);
    }
    $request->save();
  }

  /**
   * Creates the and sends the request object to ePoetry.
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
    if (!$last_request instanceof TranslationRequestEpoetryInterface) {
      $dossiers = $this->state->get('oe_translation_epoetry.dossiers', []);
      // If we are not making a new version request, we need to check if
      // we have a number set in the system, we need to add a new part
      // the existing dossier for any new nodes. This is because that is how
      // DGT expects we send requests.
      if ($this->newPartNeeded($request, $form, $form_state)) {
        $object = $this->requestFactory->addNewPartToDossierRequest($request);
        $response = $this->requestFactory->getRequestClient()->addNewPartToDossier($object);
        // If we made an addPartToDossier request, we need to increment the
        // part in the dossier stored in our State system.
        $dossier = $response->getReturn()->getRequestReference()->getDossier();
        $number = $dossier->getNumber();
        $dossiers[$number]['part'] = $dossiers[$number]['part'] + 1;
        $this->state->set('oe_translation_epoetry.dossiers', $dossiers);
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
        $this->state->set('oe_translation_epoetry.dossiers', $dossiers);
      }
    }
    else {
      $object = $this->requestFactory->createNewVersionRequest($request, $last_request);
      $response = $this->requestFactory->getRequestClient()->createNewVersion($object);

      if ($form_state->get('create_new_version_ongoing')) {
        // If we are creating a new version request from an ongoing state,
        // mark the old request as finished.
        // @see NewVersionCreateForm.
        $last_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
        $last_request->save();
        $this->messenger->addStatus($this->t('The old request with the id <strong>@old</strong> has been marked as Finished.', ['@old' => $last_request->getRequestId(TRUE)]));
        // @todo , log and reference the old request in the new one.
      }
    }

    $request_id = static::requestIdFromResponse($response->getReturn());
    $request->setRequestId($request_id);

    // At this stage, the request still stays active. However, we set the
    // status coming from ePoetry for its specific request status.
    $request->setEpoetryRequestStatus($response->getReturn()->getRequestDetails()->getStatus());
    $this->messenger->addStatus($this->t('The translation request has been sent to DGT.'));
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
    $dossiers = $this->state->get('oe_translation_epoetry.dossiers', []);
    if (empty($dossiers)) {
      // If we don't yet have any numbers, we create a new linguistic request.
      return FALSE;
    }

    $dossiers = array_reverse($dossiers, TRUE);
    // We check the last number and if it's corresponding last part is 30,
    // we reset.
    $dossier = current($dossiers);
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
   * In the query, we don't look for the status because at this point, we have
   * already checked that there is nothing ongoing.
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
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();

    if (!$ids) {
      return NULL;
    }

    $id = reset($ids);
    return $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
  }

}
