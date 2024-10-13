<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for starting a new remote translation request.
 */
class RemoteTranslationNewForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote translation provider manager.
   *
   * @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager
   */
  protected $providerManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs a new RemoteTranslationNewForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager $providerManager
   *   The remote translation provider manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RemoteTranslationProviderManager $providerManager, AccountInterface $account) {
    $this->entityTypeManager = $entityTypeManager;
    $this->providerManager = $providerManager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_remote_new_form';
  }

  /**
   * {@inheritdoc}
   */
  public function access(): AccessResultInterface {
    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('remote_translation_provider')->loadByProperties(['enabled' => TRUE]);
    if (!$translators) {
      return AccessResult::forbidden()->addCacheTags(['config:remote_translation_provider_list']);
    }

    return AccessResult::allowed()->addCacheTags(['config:remote_translation_provider_list']);
  }

  /**
   * Title callback for the page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param null|string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   The title.
   */
  public function title(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    return [
      '#markup' => $this->t('Remote translations for @entity', ['@entity' => $entity->label()]),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $route_match = NULL, $entity_type_id = NULL) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    $form['#entity'] = $entity;

    // Check for existing requests.
    $requests = $this->getExistingTranslationRequests($entity);
    if ($requests) {
      $form['requests'] = $this->buildExistingRequestsForm($form, $form_state, $entity, $requests);
    }

    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('remote_translation_provider')->loadByProperties(['enabled' => TRUE]);
    $options = [];
    foreach ($translators as $translator) {
      $options[$translator->id()] = $translator->label();
    }

    $access = $this->createNewRequestAccess($entity);
    $form['translator_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Start a new translation request'),
      '#attributes' => ['class' => ['translator-wrapper']],
    ];
    if ($access->isForbidden() && $access->getReason()) {
      $form['translator_wrapper']['access'] = [
        '#theme' => 'status_messages',
        '#message_list' => ['warning' => [$access->getReason()]],
      ];
    }
    $form['translator_wrapper']['translator'] = [
      '#type' => 'select',
      '#title' => $this->t('Translator'),
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('Please select a translator'),
      '#ajax' => [
        'callback' => [$this, 'translatorConfigurationAjaxCallback'],
        'wrapper' => 'translator-configuration-wrapper',
      ],
      '#disabled' => !$access->isAllowed(),
    ];

    if (count($options) === 1 && !$access->isForbidden()) {
      $form['translator_wrapper']['translator']['#default_value'] = key($options);
    }

    $form['translator_wrapper']['translator_configuration'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'translator-configuration-wrapper',
      ],
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $translator_id = NULL;
    if ($form_state->getValue('translator')) {
      $translator_id = $form_state->getValue('translator');
    }

    // If we only have a single option available, use that one.
    if (count($options) === 1 && !$access->isForbidden()) {
      $translator_id = key($options);
    }

    if ($translator_id) {
      $translator = $translators[$translator_id];
      /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
      $plugin = $this->providerManager->createInstance($translator->getProviderPlugin(), $translator->getProviderConfiguration());
      $plugin->setEntity($this->getRequestEntityRevision($entity));

      $form['translator_wrapper']['translator_configuration']['#type'] = 'details';
      $form['translator_wrapper']['translator_configuration']['#title'] = $this->t('New translation request using <em>@translator</em>', ['@translator' => $translator->label()]);
      $form['translator_wrapper']['translator_configuration'][$translator_id] = [
        '#process' => [[get_class($this), 'processTranslatorConfiguration']],
        '#plugin' => $plugin,
      ];

      $form['translator_wrapper']['translator_configuration']['actions'] = [
        '#type' => 'actions',
      ];

      $form['translator_wrapper']['translator_configuration']['actions']['send'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save and send'),
        '#submit' => ['::saveAndSend'],
        '#validate' => ['::validateBeforeSend'],
      ];
    }

    return $form;
  }

  /**
   * Ajax callback for the translator configuration form elements.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function translatorConfigurationAjaxCallback(array $form, FormStateInterface $form_state): array {
    return $form['translator_wrapper']['translator_configuration'];
  }

  /**
   * Process callback for the translator configuration form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The translator configuration form.
   */
  public static function processTranslatorConfiguration(array &$element, FormStateInterface $form_state): array {
    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
    $plugin = $element['#plugin'];
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);

    return $plugin->newTranslationRequestForm($element, $subform_state);
  }

  /**
   * Saves the new translation request and directly sends it.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateBeforeSend(array &$form, FormStateInterface $form_state): void {
    $translator_id = $form_state->getValue('translator');
    $element = $form['translator_wrapper']['translator_configuration'][$translator_id];
    $plugin = $element['#plugin'];
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $subform_state->set('translator_id', $translator_id);

    $plugin->validateRequest($element, $subform_state);
  }

  /**
   * Saves the new translation request and directly sends it.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function saveAndSend(array &$form, FormStateInterface $form_state): void {
    $translator_id = $form_state->getValue('translator');
    $element = $form['translator_wrapper']['translator_configuration'][$translator_id];
    $plugin = $element['#plugin'];
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $subform_state->set('translator_id', $translator_id);

    $plugin->submitRequestToProvider($element, $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Stays empty.
  }

  /**
   * Returns the existing translation requests for a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The translation requests.
   */
  protected function getExistingTranslationRequests(ContentEntityInterface $entity): array {
    // Defer to the provider manager to get the requests for this given
    // revision.
    return $this->providerManager->getExistingTranslationRequests($entity, FALSE);
  }

  /**
   * Builds the existing requests form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   * @param \Drupal\oe_translation_remote\TranslationRequestRemoteInterface[] $requests
   *   The existing requests.
   */
  protected function buildExistingRequestsForm(array $form, FormStateInterface $form_state, ContentEntityInterface $entity, array $requests): array {
    // If we only have a single request, render it directly here.
    if (count($requests) === 1) {
      $request = reset($requests);

      $form['title'] = [
        '#type' => 'inline_template',
        '#template' => "{% trans %}<h3>Ongoing remote translation request via <em>{{ translator }}</em>.</h3>{% endtrans %}",
        '#context' => [
          'translator' => $request->getTranslatorProvider()->label(),
        ],
      ];

      $view_builder = $this->entityTypeManager->getViewBuilder('oe_translation_request');
      $build = $view_builder->view($request);
      $form['request'] = $build;

      return $form;
    }

    // Otherwise, create a table of requests that link to their pages.
    $form['title'] = [
      '#type' => 'inline_template',
      '#template' => "<h3>{{ 'Ongoing remote translation requests'|t }}</h3>",
    ];

    $headers = [
      'translator' => $this->t('Translator'),
      'status' => $this->t('Request status'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];
    foreach ($requests as $request) {
      $entity = $request->getContentEntity();
      $row = [
        'translator' => $request->getTranslatorProvider()->label(),
        'status' => [
          'data' => [
            '#theme' => 'tooltip',
            '#label' => $request->getRequestStatus(),
            '#text' => $request->getRequestStatusDescription($request->getRequestStatus()),
          ],
        ],
        'operations' => [
          'data' => $request->getOperationsLinks(),
        ],
      ];

      $rows[] = [
        'data' => $row,
        'data-revision-id' => $entity->getRevisionId(),
        'data-translation-request' => $request->id(),
      ];
    }

    $form['table'] = [
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['ongoing-remote-translation-requests-table'],
      ],
    ];

    return $form;
  }

  /**
   * Checks access to create a new translation request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function createNewRequestAccess(ContentEntityInterface $entity): AccessResultInterface {
    $has_permission = $this->account->hasPermission('translate any entity');
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    if (!$has_permission) {
      return AccessResult::forbidden('The user is missing the translation permission.')->addCacheableDependency($cache);
    }

    // Check that there are no translation requests already for this entity. For
    // this, we care about any requests that are active in the provider,
    // regardless of the revision.
    $statuses = [
      TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
      TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED,
      TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
    ];
    $translation_requests = $this->providerManager->getExistingTranslationRequests($entity, FALSE, $statuses);
    $cache->addCacheTags(['oe_translation_request_list']);
    if (!$translation_requests) {
      return AccessResult::allowed()->addCacheableDependency($cache);
    }

    return AccessResult::forbidden('No new translation request can be made because there is already an active translation request for this entity version.')->addCacheableDependency($cache);
  }

  /**
   * Returns the entity revision to be used for the request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity revision.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The revision to use in the request.
   */
  protected function getRequestEntityRevision(ContentEntityInterface $entity): ContentEntityInterface {
    // By default, we use the current, default revision.
    return $entity;
  }

}
