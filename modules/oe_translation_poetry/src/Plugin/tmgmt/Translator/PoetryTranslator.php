<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\tmgmt\Translator;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\oe_translation\AlterableTranslatorInterface;
use Drupal\oe_translation_poetry\Poetry;
use Drupal\oe_translation_poetry\PoetryJobQueue;
use Drupal\tmgmt\JobCheckoutManager;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobQueue;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\TranslatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
class PoetryTranslator extends TranslatorPluginBase implements AlterableTranslatorInterface, ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var PoetryJobQueue
   */
  protected $jobQueue;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

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
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *
   * @param \Drupal\oe_translation_poetry\PoetryJobQueue $job_queue
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, AccountProxyInterface $current_user, Poetry $poetry, LanguageManagerInterface $language_manager, AccessManagerInterface $access_manager, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, FormBuilderInterface $form_builder, PoetryJobQueue $job_queue, RouteMatchInterface $route_match) {
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
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function jobItemFormAlter(array &$form, FormStateInterface $form_state): void {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function contentTranslationOverviewAlter(array &$build, RouteMatchInterface $route_match, $entity_type_id): void {
    if ($this->currentUser->hasPermission('translate any entity')) {
      $build = $this->formBuilder->getForm('Drupal\tmgmt_content\Form\ContentTranslateForm', $build);
      if (isset($build['actions']['add_to_cart'])) {
        $build['actions']['add_to_cart']['#access'] = FALSE;
      }

      if (isset($build['actions']['request'])) {
        $build['actions']['request']['#value'] = $this->t('Request DGT translation for the selected languages');
      }
    }
  }

  /**
   * Submit handler for the TMGMT content translation overview form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @see oe_translation_poetry_form_tmgmt_content_translate_form_alter()
   */
  public function submitPoetryTranslationRequest(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    $values = $form_state->getValues();

    $this->jobQueue->reset();
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
}
