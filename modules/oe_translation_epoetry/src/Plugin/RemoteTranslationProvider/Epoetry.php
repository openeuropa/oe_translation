<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\RemoteTranslationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, TranslationSourceManagerInterface $translationSourceManager, MessengerInterface $messenger, RequestFactory $requestFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $languageManager, $entityTypeManager, $translationSourceManager, $messenger);

    $this->requestFactory = $requestFactory;
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
      $container->get('oe_translation_epoetry.request_factory')
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
      // Create the request object from the request entity.
      $object = $this->requestFactory->createTranslationRequest($request);
      // Send the request object.
      $response = $this->requestFactory->getRequestClient()->createLinguisticRequest($object);
      // We don't update the request status because if the request is fine, it
      // will become "SenttoDG" which for us is "active";.
      $request_id = static::requestIdFromResponse($response->getReturn());
      $request->setRequestId($request_id);
      $this->messenger->addStatus($this->t('The translation request has been sent to DGT.'));
    }
    catch (\Exception $exception) {
      // @todo handle error.
      $request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED);
      $this->messenger->addError($this->t('There was a problem sending the request to DGT.'));
    }
    $request->save();
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

}
