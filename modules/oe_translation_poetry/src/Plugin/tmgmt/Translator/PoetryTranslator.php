<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\tmgmt\Translator;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\oe_translation\AlterableTranslatorInterface;
use Drupal\oe_translation\ApplicableTranslatorInterface;
use Drupal\oe_translation\Event\TranslationAccessEvent;
use Drupal\oe_translation\JobAccessTranslatorInterface;
use Drupal\oe_translation\RouteProvidingTranslatorInterface;
use Drupal\oe_translation_poetry\Event\PoetryRequestTypeEvent;
use Drupal\oe_translation_poetry\Plugin\Field\FieldType\PoetryRequestIdItem;
use Drupal\oe_translation_poetry\Poetry;
use Drupal\oe_translation_poetry\PoetryJobQueueFactory;
use Drupal\oe_translation_poetry\PoetryRequestType;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\TranslatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * DGT Translator using the Poetry service.
 *
 * @TranslatorPlugin(
 *   id = "poetry",
 *   label = @Translation("Poetry"),
 *   description = @Translation("Allows the users to send translation requests to Poetry."),
 *   ui = "\Drupal\oe_translation_poetry\PoetryTranslatorUI",
 *   default_settings = {},
 *   map_remote_languages = TRUE,
 *   abort_class = "\Drupal\oe_translation_poetry\Form\JobItemAbortForm"
 * )
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PoetryTranslator extends TranslatorPluginBase implements ApplicableTranslatorInterface, AlterableTranslatorInterface, ContainerFactoryPluginInterface, RouteProvidingTranslatorInterface, JobAccessTranslatorInterface {

  /**
   * Status indicating that the translation is ongoing in Poetry.
   */
  const POETRY_STATUS_ONGOING = 'ongoing';

  /**
   * Status indicating that the translation has been received from Poetry.
   */
  const POETRY_STATUS_TRANSLATED = 'translated';

  /**
   * Status indicating that the translation has been cancelled in Poetry.
   */
  const POETRY_STATUS_CANCELLED = 'cancelled';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Poetry client.
   *
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current user job queue factory.
   *
   * @var \Drupal\oe_translation_poetry\PoetryJobQueueFactory
   */
  protected $jobQueueFactory;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * PermissionTranslator constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry client.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\oe_translation_poetry\PoetryJobQueueFactory $job_queue_factory
   *   The current user job queue.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Database\Connection $database
   *   The current route match.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, AccountProxyInterface $current_user, Poetry $poetry, LanguageManagerInterface $language_manager, AccessManagerInterface $access_manager, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, FormBuilderInterface $form_builder, PoetryJobQueueFactory $job_queue_factory, RouteMatchInterface $route_match, Connection $database, EventDispatcherInterface $eventDispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->poetry = $poetry;
    $this->languageManager = $language_manager;
    $this->accessManager = $access_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->formBuilder = $form_builder;
    $this->jobQueueFactory = $job_queue_factory;
    $this->currentRouteMatch = $route_match;
    $this->database = $database;
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
      $container->get('current_user'),
      $container->get('oe_translation_poetry.client.default'),
      $container->get('language_manager'),
      $container->get('access_manager'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('form_builder'),
      $container->get('oe_translation_poetry.job_queue_factory'),
      $container->get('current_route_match'),
      $container->get('database'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityTypeInterface $entityType): bool {
    // We can only translate Node entities with Poetry.
    return $entityType->id() === 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): RouteCollection {
    $collection = new RouteCollection();

    $route = new Route(
      '/admin/content/dgt/send-request-new/{node}',
      [
        '_form' => '\Drupal\oe_translation_poetry\Form\NewTranslationRequestForm',
        '_title_callback' => '\Drupal\oe_translation_poetry\Form\NewTranslationRequestForm::getPageTitle',
      ],
      [
        '_permission' => 'translate any entity',
        '_custom_access' => 'oe_translation_poetry.job_queue_factory::access',
      ]
    );
    $collection->add('oe_translation_poetry.job_queue_checkout_new', $route);

    $route = new Route(
      '/admin/content/dgt/send-request-update/{node}',
      [
        '_form' => '\Drupal\oe_translation_poetry\Form\UpdateTranslationRequestForm',
        '_title_callback' => '\Drupal\oe_translation_poetry\Form\UpdateTranslationRequestForm::getPageTitle',
      ],
      [
        '_permission' => 'translate any entity',
        '_custom_access' => 'oe_translation_poetry.job_queue_factory::access',
      ]
    );
    $collection->add('oe_translation_poetry.job_queue_checkout_update', $route);

    $route = new Route(
      '/admin/content/dgt/add-languages-request/{node}',
      [
        '_form' => '\Drupal\oe_translation_poetry\Form\AddLanguagesRequestForm',
        '_title_callback' => '\Drupal\oe_translation_poetry\Form\AddLanguagesRequestForm::getPageTitle',
      ],
      [
        '_permission' => 'translate any entity',
        '_custom_access' => 'oe_translation_poetry.job_queue_factory::access',
      ]
    );
    $collection->add('oe_translation_poetry.job_queue_checkout_add_languages', $route);

    $route = new Route(
      '/poetry/notifications',
      [
        '_controller' => '\Drupal\oe_translation_poetry\Controller\NotificationsController::handle',
      ],
      [
        '_access' => 'TRUE',
      ]
    );
    $collection->add('oe_translation_poetry.notifications', $route);

    return $collection;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function jobItemFormAlter(array &$form, FormStateInterface $form_state): void {

    // Improve the button labels and custom redirect page.
    if (isset($form['actions']['accept'])) {
      $form['actions']['accept']['#value'] = t('Accept translation');
      $form['actions']['accept']['#submit'][] = [$this, 'jobItemFormRedirect'];
    }

    if (isset($form['actions']['save'])) {
      $form['actions']['save']['#value'] = t('Update translation');
      $form['actions']['save']['#submit'][] = [$this, 'jobItemFormRedirect'];
    }

    if (isset($form['actions']['validate'])) {
      $form['actions']['validate']['#access'] = FALSE;
    }

    if (isset($form['actions']['validate_html'])) {
      $form['actions']['validate_html']['#access'] = FALSE;
    }

    if (isset($form['actions']['abort_job_item'])) {
      $form['actions']['abort_job_item']['#attributes']['class'] = ['button'];
      $form['actions']['abort_job_item']['#title'] = $this->t('Cancel translation job item');
    }

    if (isset($form['actions']['preview'])) {
      $form['actions']['preview']['#weight'] = 100;
    }

    // Hide the validation checkmarks.
    foreach (Element::children($form['review']) as $child_key) {
      $child = &$form['review'][$child_key];
      foreach (Element::children($child) as $grandchild_key) {
        $grandchild = &$child[$grandchild_key];
        foreach (Element::children($grandchild) as $great_grandchild_key) {
          $great_grandchild = &$grandchild[$great_grandchild_key];
          if (isset($great_grandchild['actions'])) {
            $great_grandchild['actions']['#access'] = FALSE;
          }
        }
      }
    }
  }

  /**
   * Redirect to the translation page.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function jobItemFormRedirect(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\tmgmt\JobItemInterface $job_item */
    $job_item = $form_state->getBuildInfo()['callback_object']->getEntity();
    $url = Url::fromRoute('entity.node.content_translation_overview', ['node' => $job_item->getItemId()]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function contentTranslationOverviewAlter(array &$build, RouteMatchInterface $route_match, $entity_type_id): void {
    if (!$this->currentUser->hasPermission('translate any entity')) {
      return;
    }

    if (!$this->poetry->isAvailable()) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $build['#entity'];

    $event = new TranslationAccessEvent($entity, 'poetry', $this->currentUser, $entity->language());
    $this->eventDispatcher->dispatch(TranslationAccessEvent::EVENT, $event);
    $access = $event->getAccess();
    if ($access instanceof AccessResultForbidden) {
      if ($access instanceof AccessResultReasonInterface && $access->getReason()) {
        \Drupal::messenger()->addWarning($access->getReason());
      }
      return;
    }

    $destination = $entity->toUrl('drupal:content-translation-overview');
    $languages = $this->languageManager->getLanguages();

    $unprocessed_languages = $this->getUnprocessedJobsByLanguage($entity);
    $accepted_languages = $this->getAcceptedJobsByLanguage($entity);
    $submitted_languages = $this->getSubmittedJobsByLanguage($entity);
    $translated_languages = $this->getTranslatedJobsByLanguage($entity);
    $cancelled_jobs = $this->getCancelledJobsByLanguage($entity);

    $request_type = $this->getRequestType($entity);

    // If we have accepted languages in Poetry, we need to include them (and
    // some others automatically in a potential update request). Also, these
    // are needed to determine which languages should be omitted from a
    // language addition request.
    // @see oe_translation_poetry_form_tmgmt_content_translate_form_alter().
    $build['#accepted_languages'] = $accepted_languages;
    $build['#translated_languages'] = $translated_languages;
    $build['#completed_languages'] = [];
    // Load the completed jobs in the same request. For this we need to get
    // the request ID from one of the accepted jobs in order to filter the
    // completed jobs and include only the ones in this request. For the
    // translated jobs we don't have to filter by request ID because those
    // still contain the `poetry_state` value which indicates they are part
    // of the last request for that content.
    if ($accepted_languages) {
      $accepted_job_info = reset($accepted_languages);
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $this->entityTypeManager->getStorage('tmgmt_job')->load($accepted_job_info->tjid);
      $completed_languages = $this->getCompletedJobsByLanguage($entity, $job->get('poetry_request_id')->first()->getValue());
      $build['#completed_languages'] = $completed_languages;
    }

    // If we are talking about an update request, add a marker to the build
    // so that we can use it in the form alter.
    if ($request_type->getType() === PoetryRequestType::UPDATE) {
      $build['#update_request'] = TRUE;
    }

    // Build the TMGMT translation request form.
    $build = $this->formBuilder->getForm('Drupal\tmgmt_content\Form\ContentTranslateForm', $build);

    if (isset($build['actions']['add_to_cart'])) {
      $build['actions']['add_to_cart']['#access'] = FALSE;
    }

    // If for some reason we don't have the request button we bail out.
    if (!isset($build['actions']['request'])) {
      return;
    }

    foreach ($languages as $langcode => $language) {
      // Add links for unprocessed jobs to delete the delete.
      if (array_key_exists($langcode, $unprocessed_languages)) {
        $links = &$build['languages']['#options'][$langcode][4]['data']['#links'];
        $url_options = [
          'language' => $language,
          'query' => ['destination' => $destination->toString()],
        ];
        $delete_url = Url::fromRoute(
          'entity.tmgmt_job.delete_form',
          ['tmgmt_job' => $unprocessed_languages[$language->getId()]->tjid],
          $url_options
        );

        if ($delete_url->access()) {
          $links['tmgmt.poetry.delete'] = [
            'url' => $delete_url,
            'title' => $this->t('Delete unprocessed job'),
          ];
        }
      }

      // Show a label instead of link for certain jobs.
      if (array_key_exists($langcode, $accepted_languages)) {
        $build['languages']['#options'][$langcode][3] = $this->t('Ongoing in Poetry');
      }
      elseif (array_key_exists($langcode, $submitted_languages)) {
        $build['languages']['#options'][$langcode][3] = $this->t('Submitted to Poetry');
      }
      elseif (array_key_exists($langcode, $translated_languages)) {
        $job_item = $this->entityTypeManager->getStorage('tmgmt_job_item')->load($translated_languages[$language->getId()]->tjiid);
        $build['languages']['#options'][$langcode][3] = $this->t('Ready for review');

        $review_url = $job_item->toUrl();
        if ($review_url->access()) {
          $build['languages']['#options'][$langcode][4]['data']['#links']['tmgmt.poetry.review'] = [
            'url' => $review_url,
            'title' => $this->t('Review translation'),
          ];
        }
      }
      elseif (array_key_exists($langcode, $cancelled_jobs)) {
        $build['languages']['#options'][$langcode][3] = $this->t('Cancelled in Poetry');
      }
    }

    $job_queue = $this->jobQueueFactory->get($entity);
    /** @var \Drupal\tmgmt\JobInterface[] $current_jobs */
    $current_jobs = $job_queue->getAllJobs();

    // If there are jobs in the queue, finish them first.
    if (!empty($current_jobs)) {
      $current_target_languages = $job_queue->getTargetLanguages();
      $language_list = implode(', ', $current_target_languages);
      $build['actions']['request']['#value'] = $this->t('Finish translation request to DGT for @language_list', ['@language_list' => $language_list]);
      return;
    }

    // If the request is for new translations and we don't have an ongoing
    // request in progress (either accepted or submitted), we show the button
    // to make a new request.
    if (empty($accepted_languages) && empty($submitted_languages) && $request_type->getType() === PoetryRequestType::NEW) {
      $message = $request_type->getMessage() ?? $this->t('Request a DGT translation for the selected languages');
      $build['actions']['request']['#value'] = $message;
      return;
    }

    // If we have an ongoing request, we should show the request ID to the user.
    if ($submitted_languages || $accepted_languages || $translated_languages) {
      $ongoing = array_merge($submitted_languages, $accepted_languages, $translated_languages);
      $job_info = reset($ongoing);
      $job = $this->entityTypeManager->getStorage('tmgmt_job')->load($job_info->tjid);
      $request_id = $job->get('poetry_request_id')->first()->getValue();
      $provider_info = [
        '#type' => 'details',
        '#title' => $this->t('Translation provider information'),
        '#open' => TRUE,
        'info' => [
          '#markup' => $this->t('DGT Poetry request reference: @ref', ['@ref' => PoetryRequestIdItem::toReference($request_id)]),
        ],
      ];
      array_unshift($build, $provider_info);
    }

    // If requests are waiting for translation by DGT, it is possible to
    // request an update. But only if there are no submitted languages that
    // DGT has not responded to.
    // @see oe_translation_poetry_form_tmgmt_content_translate_form_alter().
    if ($request_type->getType() === PoetryRequestType::UPDATE && empty($submitted_languages)) {
      $message = $request_type->getMessage() ?? $this->t('Request a DGT translation update for the selected languages');
      $build['actions']['request']['#value'] = $message;
      return;
    }

    if (($accepted_languages || $translated_languages) && !$submitted_languages) {
      // If at this point we are not talking about an update request but we
      // do have accepted languages we return to allow the language addition
      // action to be added.
      $build['actions']['request']['#access'] = FALSE;
      return;
    }

    // If we reached this point, it means we cannot make any kind of request.
    $build['actions']['#access'] = FALSE;
    $message = $request_type->getMessage() ?? $this->t('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');
    \Drupal::messenger()->addWarning($message);
  }

  /**
   * Submit handler for the TMGMT content translation overview form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see oe_translation_poetry_form_tmgmt_content_translate_form_alter()
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function submitPoetryTranslationRequest(array &$form, FormStateInterface $form_state): void {
    // Determine if we are making a request to add extra languages to an
    // ongoing request.
    $triggering_element = $form_state->getTriggeringElement();
    $extra_language_request = isset($triggering_element['#op']) && $triggering_element['#op'] === 'add-languages';
    $ongoing_languages = $extra_language_request ? $form_state->get('ongoing_languages') : [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    $entity = $entity->isDefaultTranslation() ? $entity : $entity->getUntranslated();
    $job_queue = $this->jobQueueFactory->get($entity);
    $job_queue->setEntityId($entity->getEntityTypeId(), $entity->getRevisionId());
    $values = $form_state->getValues();

    foreach (array_keys(array_filter($values['languages'])) as $langcode) {
      // We do not want to create jobs for languages that already exist in the
      // original request (ongoing ones or that have been translated).
      if ($extra_language_request && in_array($langcode, array_keys($ongoing_languages))) {
        continue;
      }

      $job = tmgmt_job_create($entity->language()->getId(), $langcode, $this->currentUser->id());
      // Set the Poetry translator on it.
      $job->translator = 'poetry';
      try {
        $job->addItem('content', $entity->getEntityTypeId(), $entity->id());
        $job->save();
        $job_queue->addJob($job);
      }
      catch (TMGMTException $e) {
        watchdog_exception('tmgmt', $e);
        $target_lang_name = $this->languageManager->getLanguage($langcode)->getName();
        $this->messenger()->addError($this->t('Unable to add job item for target language %name. Make sure the source content is not empty.', ['%name' => $target_lang_name]));
      }
    }

    if (!$job_queue->getAllJobsIds()) {
      $this->messenger()->addError('You need to select at least one extra language to add to the request.');
      return;
    }

    $url = Url::fromRoute($this->currentRouteMatch->getRouteName(), $this->currentRouteMatch->getRawParameters()->all());
    $job_queue->setDestination($url);

    // Remove the destination so that we can redirect to the checkout form.
    if ($this->request->query->has('destination')) {
      $this->request->query->remove('destination');
    }

    if ($extra_language_request) {
      $route = 'oe_translation_poetry.job_queue_checkout_add_languages';
    }
    else {
      $request_type = $this->getRequestType($entity);
      $route = $request_type->getType() === PoetryRequestType::UPDATE ? 'oe_translation_poetry.job_queue_checkout_update' : 'oe_translation_poetry.job_queue_checkout_new';
    }

    $redirect = Url::fromRoute($route, ['node' => $entity->id()]);

    // Redirect to the checkout form.
    $form_state->setRedirectUrl($redirect);
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job) {
    // We don't do anything here because requests to Poetry need to create and
    // maintain multiple jobs per request. So this is handled in
    // PoetryCheckoutForm.
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(JobInterface $job) {
    // For the moment we don't have any checkout settings.
    return FALSE;
  }

  /**
   * Get a list of unprocessed Poetry jobs for a given entity.
   *
   * These are the jobs which have been created when the user started the
   * translation request but has not submitted it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   *
   * @return array
   *   An array of unprocessed job IDs, keyed by the target language.
   */
  protected function getUnprocessedJobsByLanguage(ContentEntityInterface $entity): array {
    $query = $this->getEntityJobsQuery($entity);
    $query->condition('job.state', Job::STATE_UNPROCESSED, '=');
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
  }

  /**
   * Get a list of Poetry jobs that have been accepted for a given entity.
   *
   * These are the jobs which have been accepted by Poetry for translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   *
   * @return array
   *   An array of accepted job IDs, keyed by the target language.
   */
  protected function getAcceptedJobsByLanguage(ContentEntityInterface $entity): array {
    $query = $this->getEntityJobsQuery($entity);
    $query->condition('job.state', Job::STATE_ACTIVE, '=');
    $query->condition('job.poetry_state', static::POETRY_STATUS_ONGOING, '=');
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
  }

  /**
   * Get a list of Poetry jobs that have been submitted for a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   *
   * @return array
   *   An array of submitted job IDs, keyed by the target language.
   */
  protected function getSubmittedJobsByLanguage(ContentEntityInterface $entity): array {
    $query = $this->getEntityJobsQuery($entity);
    $query->condition('job.state', Job::STATE_ACTIVE, '=');
    $query->isNull('job.poetry_state');
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
  }

  /**
   * Get a list of Poetry jobs that have been translated for a given entity.
   *
   * These are the jobs which have been translated by Poetry and need to be
   * reviewed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   *
   * @return array
   *   An array of translated job IDs, keyed by the target language.
   */
  protected function getTranslatedJobsByLanguage(ContentEntityInterface $entity): array {
    $query = $this->getEntityJobsQuery($entity);
    $query->condition('job.state', Job::STATE_ACTIVE, '=');
    $query->condition('poetry_state', static::POETRY_STATUS_TRANSLATED);
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
  }

  /**
   * Get a list of Poetry jobs that are completed for a given entity.
   *
   * These are the jobs which have been translated by Poetry in a certain
   * request (of the given Job).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   * @param array $reference
   *   The Poetry request reference to filter the jobs by.
   *
   * @return array
   *   An array of completed job IDs, keyed by the target language.
   */
  protected function getCompletedJobsByLanguage(ContentEntityInterface $entity, array $reference): array {
    $query = $this->getEntityJobsQuery($entity);
    foreach ($reference as $name => $value) {
      $query->condition('job.poetry_request_id__' . $name, $value);
    }
    $query->condition('job.state', Job::STATE_FINISHED, '=');
    $query->isNull('job.poetry_state');
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
  }

  /**
   * Get a list of cancelled Poetry jobs for a given entity.
   *
   * These include only the jobs that were cancelled in the last request made
   * for the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   *
   * @return array
   *   An array of cancelled job IDs, keyed by the target language.
   */
  protected function getCancelledJobsByLanguage(ContentEntityInterface $entity): array {
    $query = $this->getEntityJobsQuery($entity);
    $query->condition('job.state', Job::STATE_ABORTED, '=');
    $query->condition('poetry_state', static::POETRY_STATUS_CANCELLED);
    $identifier_alias = $query->addExpression("CONCAT(poetry_request_id__code, '/', poetry_request_id__year, '/', poetry_request_id__number, '/', poetry_request_id__version, '/', poetry_request_id__part, '/', poetry_request_id__product)", 'identifier');
    $last_identifier = $this->poetry->getLastIdentifierForContent($entity);

    $result = $query->execute()->fetchAllAssoc('target_language');
    if (!$result) {
      return [];
    }

    if (!$last_identifier) {
      return [];
    }

    return array_filter($result, function ($item) use ($last_identifier, $identifier_alias) {
      return $item->{$identifier_alias} === $last_identifier->getFormattedIdentifier();
    });
  }

  /**
   * Prepares and returns a query for the jobs of a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The query.
   */
  protected function getEntityJobsQuery(ContentEntityInterface $entity): SelectInterface {
    $query = $this->database->select('tmgmt_job', 'job');
    $query->join('tmgmt_job_item', 'job_item', 'job.tjid = job_item.tjid');
    $query->fields('job', ['tjid', 'target_language']);
    $query->fields('job_item', ['tjiid']);
    $query->condition('job_item.item_id', $entity->id());
    $query->condition('job_item.item_type', $entity->getEntityTypeId());
    $query->condition('job.translator', 'poetry', '=');
    return $query;
  }

  /**
   * Returns what kind of request is to be made.
   *
   * This is to determine if the request that is made when pressing the big
   * Request button should be for a new translation or an update.
   *
   * By default, requests are for new translations. However, if there are
   * ongoing, accepted translations in Poetry, the request can only be for
   * an update.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which we are making the request for.
   *
   * @return \Drupal\oe_translation_poetry\PoetryRequestType
   *   The request type constant.
   */
  protected function getRequestType(ContentEntityInterface $entity): PoetryRequestType {
    $request_type = new PoetryRequestType(PoetryRequestType::NEW);
    $accepted_jobs = $this->getAcceptedJobsByLanguage($entity);
    if ($accepted_jobs) {
      $request_type->setRequestType(PoetryRequestType::UPDATE);
    }
    $job_info = $accepted_jobs ? reset($accepted_jobs) : NULL;
    $event = new PoetryRequestTypeEvent($entity, $request_type, $job_info);
    $this->eventDispatcher->dispatch(PoetryRequestTypeEvent::EVENT, $event);
    return $event->getRequestType();
  }

  /**
   * {@inheritdoc}
   */
  public function accessJob(JobInterface $job, string $operation, AccountInterface $account): ?AccessResultInterface {
    if ($operation !== 'delete') {
      return NULL;
    }

    // Allow the owners of the jobs to delete jobs if they are unprocessed.
    if ($job->isAuthor($account) && (int) $job->getState() === Job::STATE_UNPROCESSED && $job->getTranslatorPlugin() instanceof PoetryTranslator) {
      return AccessResult::allowed()->addCacheableDependency($job)->cachePerUser();
    }

    return NULL;
  }

}
