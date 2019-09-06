<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\tmgmt\Translator;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\oe_translation\AlterableTranslatorInterface;
use Drupal\oe_translation\Event\TranslationAccessEvent;
use Drupal\oe_translation\RouteProvidingTranslatorInterface;
use Drupal\oe_translation_poetry\Poetry;
use Drupal\oe_translation_poetry\PoetryJobQueue;
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
 *   map_remote_languages = FALSE
 * )
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PoetryTranslator extends TranslatorPluginBase implements AlterableTranslatorInterface, ContainerFactoryPluginInterface, RouteProvidingTranslatorInterface {

  /**
   * Status indicating that the translation is ongoing in Poetry.
   */
  const POETRY_STATUS_ONGOING = 'ongoing';

  /**
   * Status indicating that the translation has been received from Poetry.
   */
  const POETRY_STATUS_TRANSLATED = 'translated';

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
   * The current user job queue.
   *
   * @var \Drupal\oe_translation_poetry\PoetryJobQueue
   */
  protected $jobQueue;

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
   * @param \Drupal\oe_translation_poetry\PoetryJobQueue $job_queue
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
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, AccountProxyInterface $current_user, Poetry $poetry, LanguageManagerInterface $language_manager, AccessManagerInterface $access_manager, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, FormBuilderInterface $form_builder, PoetryJobQueue $job_queue, RouteMatchInterface $route_match, Connection $database, EventDispatcherInterface $eventDispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->poetry = $poetry;
    $this->languageManager = $language_manager;
    $this->accessManager = $access_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->formBuilder = $form_builder;
    $this->jobQueue = $job_queue;
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
      $container->get('oe_translation_poetry.job_queue'),
      $container->get('current_route_match'),
      $container->get('database'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): RouteCollection {
    $collection = new RouteCollection();

    $route = new Route(
      '/admin/content/dgt/send-request',
      [
        '_form' => '\Drupal\oe_translation_poetry\Form\NewTranslationRequestForm',
        '_title_callback' => '\Drupal\oe_translation_poetry\Form\NewTranslationRequestForm::getPageTitle',
      ],
      [
        '_permission' => 'translate any entity',
        '_custom_access' => 'oe_translation_poetry.job_queue::access',
      ]
    );

    $collection->add('oe_translation_poetry.job_queue_checkout', $route);

    $route = new Route(
      '/poetry/notifications',
      [
        '_controller' => '\Drupal\oe_translation_poetry\Controller\NotificationsController::handle',
      ],
      [
        '_custom_access' => '\Drupal\oe_translation_poetry\Controller\NotificationsController::access',
      ]
    );

    $collection->add('oe_translation_poetry.notifications', $route);
    return $collection;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function jobItemFormAlter(array &$form, FormStateInterface $form_state): void {
    // Improve the button labels.
    if (isset($form['actions']['accept'])) {
      $form['actions']['accept']['#value'] = t('Accept translation');
    }

    if (isset($form['actions']['save'])) {
      $form['actions']['save']['#value'] = t('Update translation');
    }

    if (isset($form['actions']['validate'])) {
      $form['actions']['validate']['#access'] = FALSE;
    }

    if (isset($form['actions']['validate_html'])) {
      $form['actions']['validate_html']['#access'] = FALSE;
    }

    if (isset($form['actions']['abort_job_item'])) {
      unset($form['actions']['abort_job_item']);
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
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function contentTranslationOverviewAlter(array &$build, RouteMatchInterface $route_match, $entity_type_id): void {
    if (!$this->currentUser->hasPermission('translate any entity')) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $build['#entity'];

    $event = new TranslationAccessEvent($entity, 'poetry', $this->currentUser, $entity->language());
    $this->eventDispatcher->dispatch(TranslationAccessEvent::EVENT, $event);
    if ($event->getAccess() instanceof AccessResultForbidden) {
      return;
    }

    $entity = $build['#entity'];
    $destination = $entity->toUrl('drupal:content-translation-overview');
    // Add a link to delete any unprocessed job for this entity.
    $languages = $this->languageManager->getLanguages();
    $unprocessed_languages = $this->getUnprocessedJobsByLanguage($entity);
    $accepted_languages = $this->getAcceptedJobsByLanguage($entity);
    $submitted_languages = $this->getSubmittedJobsByLanguage($entity);
    $translated_languages = $this->getTranslatedJobsByLanguage($entity);

    // Build the TMGMT translation request form.
    $build = $this->formBuilder->getForm('Drupal\tmgmt_content\Form\ContentTranslateForm', $build);
    if (isset($build['actions']['add_to_cart'])) {
      $build['actions']['add_to_cart']['#access'] = FALSE;
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
      if (array_key_exists($langcode, $submitted_languages)) {
        $build['languages']['#options'][$langcode][3] = $this->t('Submitted to Poetry');
      }
      if (array_key_exists($langcode, $translated_languages)) {
        $job_item = $this->entityTypeManager->getStorage('tmgmt_job_item')->load($translated_languages[$language->getId()]->tjiid);
        $build['languages']['#options'][$langcode][3] = Link::fromTextAndUrl($this->t('Review translation'), $job_item->toUrl());
      }
    }

    if (isset($build['actions']['request'])) {
      /** @var \Drupal\tmgmt\JobInterface[] $current_jobs */
      $current_jobs = $this->jobQueue->getAllJobs();
      if (empty($current_jobs)) {
        // If there are no jobs in the queue, it means the user can select
        // the languages it wants to translate.
        $build['actions']['request']['#value'] = $this->t('Request DGT translation for the selected languages');
      }
      else {
        $current_target_languages = $this->jobQueue->getTargetLanguages();
        $language_list = count($current_target_languages) > 1 ? implode(', ', $current_target_languages) : array_shift($current_target_languages);
        $build['actions']['request']['#value'] = $this->t('Finish translation request to DGT for @language_list', ['@language_list' => $language_list]);
      }
    }
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
   */
  public function submitPoetryTranslationRequest(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    $entity = !$entity->isDefaultTranslation() ? $entity->getUntranslated() : $entity;
    $values = $form_state->getValues();

    $this->jobQueue->setEntityId($entity->getEntityTypeId(), $entity->getRevisionId());

    foreach (array_keys(array_filter($values['languages'])) as $langcode) {
      $job = tmgmt_job_create($entity->language()->getId(), $langcode, $this->currentUser->id());
      // Set the Poetry translator on it.
      $job->translator = 'poetry';
      try {
        $job->addItem('content', $entity->getEntityTypeId(), $entity->id());
        $job->save();
        $this->jobQueue->addJob($job);
      }
      catch (TMGMTException $e) {
        watchdog_exception('tmgmt', $e);
        $target_lang_name = $this->languageManager->getLanguage($langcode)->getName();
        $this->messenger()->addError(t('Unable to add job item for target language %name. Make sure the source content is not empty.', ['%name' => $target_lang_name]));
      }
    }

    $url = Url::fromRoute($this->currentRouteMatch->getRouteName(), $this->currentRouteMatch->getRawParameters()->all());
    $this->jobQueue->setDestination($url);

    // Remove the destination so that we can redirect to the checkout form.
    if ($this->request->query->has('destination')) {
      $this->request->query->remove('destination');
    }

    $redirect = Url::fromRoute('oe_translation_poetry.job_queue_checkout');

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
   *   An array of unprocessed job IDs, keyed by the target language.
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
   *   An array of unprocessed job IDs, keyed by the target language.
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
   *   An array of unprocessed job IDs, keyed by the target language.
   */
  protected function getTranslatedJobsByLanguage(ContentEntityInterface $entity): array {
    $query = $this->getEntityJobsQuery($entity);
    $query->condition('job.state', Job::STATE_ACTIVE, '=');
    $query->condition('poetry_state', static::POETRY_STATUS_TRANSLATED);
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
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
    $query->condition('job.translator', 'poetry', '=');
    return $query;
  }

}
