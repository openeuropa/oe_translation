<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Plugin\RemoteTranslationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation\Event\AvailableLanguagesAlterEvent;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_cdt\Api\CdtClient;
use Drupal\oe_translation_cdt\Event\CdtRequestEvent;
use Drupal\oe_translation_cdt\Mapper\DtoMapperInterface;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\LanguageCheckboxesAwareTrait;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\CdtClient\Exception\ValidationErrorsException;
use OpenEuropa\CdtClient\Model\Response\ReferenceContact;
use OpenEuropa\CdtClient\Model\Response\ReferenceItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the CDT translator provider plugin.
 *
 * @RemoteTranslationProvider(
 *   id = "cdt",
 *   label = @Translation("CDT"),
 *   description = @Translation("CDT translator provider plugin."),
 * )
 */
class Cdt extends RemoteTranslationProviderBase {

  use LanguageCheckboxesAwareTrait;

  /**
   * The class constructor.
   *
   * @param mixed[] $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation\TranslationSourceManagerInterface $translationSourceManager
   *   The translation source manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\oe_translation_cdt\Mapper\DtoMapperInterface $dtoMapper
   *   The DTO mapper.
   * @param \Drupal\oe_translation_cdt\Api\CdtClient $cdtClient
   *   The CDT client.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LanguageManagerInterface $languageManager,
    EntityTypeManagerInterface $entityTypeManager,
    TranslationSourceManagerInterface $translationSourceManager,
    MessengerInterface $messenger,
    protected DtoMapperInterface $dtoMapper,
    protected CdtClient $cdtClient,
    protected StateInterface $state,
    protected EventDispatcherInterface $eventDispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $languageManager, $entityTypeManager, $translationSourceManager, $messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('oe_translation.translation_source_manager'),
      $container->get('messenger'),
      $container->get('oe_translation_cdt.translation_request_mapper'),
      $container->get('oe_translation_cdt.client'),
      $container->get('state'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'language_mapping' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $default_configuration = $this->defaultConfiguration();
    // Only include configuration that is defined in the default array.
    $configuration = array_filter($configuration, function ($key) use ($default_configuration) {
      return isset($default_configuration[$key]);
    }, ARRAY_FILTER_USE_KEY);
    $this->configuration = $configuration + $default_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['language_mapping'] = $form_state->getValue('language_mapping');
  }

  /**
   * {@inheritdoc}
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    $languages = $this->languageManager->getLanguages();
    $event = new AvailableLanguagesAlterEvent($languages);
    $this->eventDispatcher->dispatch($event, AvailableLanguagesAlterEvent::NAME);
    $this->addLanguageCheckboxes($form, $form_state, $event->getLanguages());

    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')->create([
      'bundle' => 'cdt',
    ]);
    $form_state->set('request', $request);

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load('oe_translation_request.cdt.default');
    $form_state->set('form_display', $form_display);
    $reference_data_object = $this->cdtClient->client()->getReferenceData();
    $reference_data = [
      'department' => $reference_data_object->getDepartments(),
      'priority' => $reference_data_object->getPriorities(),
      'confidentiality' => $reference_data_object->getConfidentialities(),
      'deliver_to' => $reference_data_object->getContacts(),
      'contact_usernames' => $reference_data_object->getContacts(),
    ];

    foreach ($form_display->getComponents() as $name => $component) {
      /** @var \Drupal\Core\Field\WidgetInterface|null $widget */
      $widget = $form_display->getRenderer($name);
      if (!$widget) {
        continue;
      }

      $items = $request->get($name);
      $items->filterEmptyItems();
      $form[$name] = $widget->form($items, $form, $form_state);
      $form[$name]['#access'] = $items->access('edit');

      if (isset($reference_data[$name]) && $component['type'] === 'string_textfield') {
        $options = [];
        foreach ($reference_data[$name] as $reference_item) {
          if ($reference_item instanceof ReferenceItem) {
            $options[$reference_item->getCode()] = $reference_item->getDescription();
          }
          elseif ($reference_item instanceof ReferenceContact) {
            $options[$reference_item->getUsername()] = sprintf(
              '%s %s (%s)',
              $reference_item->getFirstName(),
              $reference_item->getLastName(),
              $reference_item->getUsername()
            );
          }
        }

        $form[$name]['widget'][0]['value']['#type'] = 'select';
        $form[$name]['widget'][0]['value']['#options'] = $options;
        $form[$name]['widget'][0]['value']['#size'] = 1;
      }
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitRequestToProvider(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $form_state->get('form_display');
    $request = $form_state->get('request');
    $form_display->extractFormValues($request, $form, $form_state);
    $languages = $this->getSubmittedLanguages($form, $form_state);
    $entity = $this->getEntity();

    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')->create([
      'bundle' => 'cdt',
      'source_language_code' => $entity->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => $form_state->get('translator_id'),
      'request_status' => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    ]);

    $request->setContentEntity($entity);
    $data = $this->translationSourceManager->extractData($entity->getUntranslated());
    $request->setData($data);
    $request->setComments($form_state->getValue('comments')['0']['value']);
    $request->setPhoneNumber($form_state->getValue('phone_number')['0']['value']);
    $request->setDepartment($form_state->getValue('department')['0']['value']);
    $request->setPriority($form_state->getValue('priority')['0']['value']);
    $request->setConfidentiality($form_state->getValue('confidentiality')['0']['value']);
    $request->setDeliverTo(array_column($form_state->getValue('deliver_to'), 'value'));
    $request->setContactUsernames(array_column($form_state->getValue('contact_usernames'), 'value'));
    $request->save();

    // Send it to CDT and update its status.
    try {
      $this->createAndSendRequestObject($request, $form, $form_state);
    }
    catch (ValidationErrorsException $exception) {
      $request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED);
      $error_list = [];
      foreach ($exception->getValidationErrors()->getErrors() as $key => $errors) {
        $error_list[] = $this->t('Field @field: @errors', [
          '@field' => $key,
          '@errors' => implode(', ', $errors),
        ]);
      }
      $this->messenger->addError($this->t('There was a problem with validating the CDT request. Please verify the CDT connection.'));
      $request->log(
        "Validation error: @message\n@fields",
        [
          '@message' => $exception->getValidationErrors()->getMessage(),
          '@fields' => implode("\n", $error_list),
        ],
        TranslationRequestLogInterface::ERROR
      );
    }
    catch (\Throwable $exception) {
      $request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED);
      $this->messenger->addError($this->t('There was a problem sending the request to CDT.'));
      $message = $exception->getMessage();

      $request->log('@type: <strong>@message</strong>', [
        '@type' => get_class($exception),
        '@message' => $message,
      ], TranslationRequestLogInterface::ERROR);
    }
    $request->save();
  }

  /**
   * Creates and sends the request object to CDT.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request
   *   The request.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \OpenEuropa\CdtClient\Exception\ValidationErrorsException
   */
  protected function createAndSendRequestObject(TranslationRequestCdtInterface $request, array $form, FormStateInterface $form_state): void {

    /** @var \OpenEuropa\CdtClient\Model\Request\Translation $dto */
    $dto = $this->dtoMapper->convertEntityToDto($request);
    $event = new CdtRequestEvent($dto, $request);
    $this->eventDispatcher->dispatch($event, CdtRequestEvent::NAME);

    $this->cdtClient->client()->validateTranslationRequest($dto);
    $correlation_id = $this->cdtClient->client()->sendTranslationRequest($dto);
    $request->setCorrelationId($correlation_id);
    $request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED);
    $request->save();
  }

}
