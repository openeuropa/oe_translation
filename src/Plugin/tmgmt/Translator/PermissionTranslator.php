<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\tmgmt\Translator;

use Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Cache\CacheableMetadata;
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
use Drupal\oe_translation\LocalTranslatorInterface;
use Drupal\oe_translation\RouteProvidingTranslatorInterface;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt_local\LocalTaskInterface;
use Drupal\tmgmt_local\LocalTaskItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Drupal current user provider.
 *
 * @TranslatorPlugin(
 *   id = "permission",
 *   label = @Translation("Permission"),
 *   description = @Translation("Allows the users with a certain permission to translate content."),
 *   ui = "\Drupal\oe_translation\PermissionTranslatorUI",
 *   default_settings = {
 *     "permission" = "translate any entity"
 *   },
 *   map_remote_languages = FALSE
 * )
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PermissionTranslator extends TranslatorPluginBase implements ContinuousTranslatorInterface, AlterableTranslatorInterface, RouteProvidingTranslatorInterface, LocalTranslatorInterface, ContainerFactoryPluginInterface {

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
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

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
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, AccountProxyInterface $current_user, LanguageManagerInterface $language_manager, AccessManagerInterface $access_manager, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, RequestStack $requestStack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->accessManager = $access_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->request = $requestStack->getCurrentRequest();
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
      $container->get('language_manager'),
      $container->get('access_manager'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job) {
    $items = $job->getItems();
    $this->requestJobItemsTranslation($items);

    // The translation job has been successfully submitted.
    $job->submitted();
  }

  /**
   * {@inheritdoc}
   */
  public function requestJobItemsTranslation(array $job_items) {
    /** @var \Drupal\tmgmt\Entity\Job $job */
    $job = reset($job_items)->getJob();
    $tuid = $job->getSetting('translator');

    // Create local task for this job.
    /** @var \Drupal\tmgmt_local\LocalTaskInterface $local_task */
    $local_task = $this->entityTypeManager->getStorage('tmgmt_local_task')->create([
      'uid' => $job->getOwnerId(),
      'tuid' => $tuid,
      'tjid' => $job->id(),
      'title' => $job->label(),
    ]);
    // If we have translator then switch to pending state.
    if ($tuid) {
      $local_task->status = LocalTaskInterface::STATUS_PENDING;
    }
    $local_task->save();

    // Create task items.
    foreach ($job_items as $item) {
      $local_task->addTaskItem($item);
      $item->active();
    }
  }

  /**
   * {@inheritdoc}
   *
   * This plugin supports translation to all languages if there is at least
   * one user who has the correct permission to do so. Otherwise, it supports
   * no languages.
   */
  public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language) {
    $users = $this->getAllowedUsers();
    if (!$users) {
      return [];
    }

    $languages = tmgmt_available_languages();
    return array_combine(array_keys($languages), array_keys($languages));
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function localTaskItemFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\tmgmt_local\LocalTaskItemInterface $task_item */
    $task_item = $form_state->getBuildInfo()['callback_object']->getEntity();
    $data = $task_item->getData();
    $job = $task_item->getJobItem()->getJob();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($task_item->getJobItem()->getItemId());
    $existing_translation = $node->hasTranslation($job->getTargetLangcode()) ? $node->getTranslation($job->getTargetLangcode()) : NULL;
    $existing_translation_data = $existing_translation ? $this->createSourceData($existing_translation, $task_item) : [];

    foreach (Element::children($form['translation']) as $field_name) {
      foreach (Element::children($form['translation'][$field_name]) as $field_path) {
        $field = &$form['translation'][$field_name][$field_path];
        // Clean up the translation form element.
        $field['#theme'] = 'local_translation_form_element_group';
        $definition = $node->getFieldDefinition($field_name);
        $field['#field_name'] = $definition->getLabel();

        list($field_name, $delta, $column) = explode('|', $field_path);

        // In case the field has multiple columns, append the column name. The
        // only exception is the fields which have a format for which we don't
        // have a separate translation field.
        if (count(Element::children($data[$field_name][0])) > 1 && !isset($data[$field_name][0]['format'])) {
          $field['#field_name'] .= ' ' . ucfirst($column);
        }

        // Append the delta in case there are multiple field values.
        if (count(Element::children($data[$field_name])) > 1) {
          $field['#field_name'] .= ' (' . ($delta + 1) . ')';
        }

        $bracket_based_field_path = str_replace('|', '][', $field_path);

        // Hide the translation tick button.
        $field['actions']['#access'] = FALSE;

        // Copy over the source values to the translation fields to give
        // translators a start if there are no values in. If we are translating
        // a node which has already been translated, used the last translation
        // version instead.
        if ($field['translation']['#default_value'] === NULL) {
          if ($existing_translation_data && array_key_exists($field_name, $existing_translation_data)) {
            $flat = \Drupal::service('tmgmt.data')->flatten($existing_translation_data[$field_name], $field_name);

            if (isset($flat[$bracket_based_field_path]) && isset($flat[$bracket_based_field_path]['#text'])) {
              // It seems TMGMT only supports text based fields to translate.
              $field['translation']['#default_value'] = $flat[$bracket_based_field_path]['#text'];
              continue;
            }
          }

          if (array_key_exists('#value', $field['source'])) {
            $field['translation']['#default_value'] = $field['source']['#value'];
          }
        }
      }
    }

    // Improve the button labels.
    if (isset($form['actions']['save_as_completed'])) {
      $form['actions']['save_as_completed']['#value'] = t('Save and finish translation');
    }
    if (isset($form['actions']['save'])) {
      // @todo re-enable this until we have a handling for source language
      // updates.
      $form['actions']['save']['#access'] = FALSE;
      $form['actions']['save']['#value'] = t('Save and come back later');
    }

    if (isset($form['actions']['preview'])) {
      array_unshift($form['actions']['preview']['#submit'], [$this, 'localTaskItemPreview']);
    }
  }

  /**
   * Submit callback for the local item task preview button.
   *
   * We ensure that no other destination takes precedence over the intended
   * form redirect to the preview page.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function localTaskItemPreview(array &$form, FormStateInterface $form_state) {
    if ($destination = $this->request->query->get('destination')) {
      $this->request->query->remove('destination');
    }
  }

  /**
   * {@inheritdoc}
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
      $form['actions']['validate']['#value'] = t('Validate translation');
    }

    if (isset($form['actions']['validate_html'])) {
      $form['actions']['validate_html']['#access'] = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function contentTranslationOverviewAlter(array &$build, RouteMatchInterface $route_match, $entity_type_id): void {
    $cache = CacheableMetadata::createFromRenderArray($build);
    $cache->addCacheContexts(['user.permissions']);

    $permission = $this->getPermission();
    if (!$this->currentUser->hasPermission($permission)) {
      $cache->applyTo($build);
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $build['#entity'];
    $destination = $entity->toUrl('drupal:content-translation-overview');
    $original_langcode = $entity->getUntranslated()->language()->getId();
    /** @var \Drupal\tmgmt\Entity\JobItem[] $job_items */
    $job_items = tmgmt_job_item_load_latest('content', $entity->getEntityTypeId(), $entity->id(), $original_langcode);
    if (!$job_items) {
      $job_items = [];
    }

    // Languages are retrieved and processed in the same order as the parent
    // content overview.
    $languages = $this->languageManager->getLanguages();
    foreach (array_values($languages) as $i => $language) {
      // No links for the original entity language.
      if ($language->getId() === $original_langcode) {
        continue;
      }

      $links = &$build['content_translation_overview']['#rows'][$i][3]['data']['#links'];
      $url_options = ['language' => $language, 'query' => ['destination' => $destination->toString()]];

      // Check if a local task item exists and the current user can edit it.
      if (isset($job_items[$language->getId()]) && ($local_task_item = $this->getLocalTaskItemFromJobItem($job_items[$language->getId()]))) {
        $edit_access = $local_task_item->access('update', $this->currentUser, TRUE);
        $cache->addCacheableDependency($edit_access);
        $title = $local_task_item->isPending() ? $this->t('Edit local translation') : $this->t('View local translation');
        if ($edit_access->isAllowed()) {
          $links['tmgmt.translate_local.edit'] = [
            'url' => $local_task_item->toUrl('canonical', $url_options),
            'title' => $title,
          ];
        }

        continue;
      }

      $create_url = Url::fromRoute('oe_translation.permission_translator.create_local_task', [
        'entity_type' => $entity->getEntityTypeId(),
        'entity' => $entity->id(),
        'source' => $original_langcode,
        'target' => $language->getId(),
      ], $url_options);

      // Use the access manager to get the cache information back.
      $create_access = $this->accessManager->checkNamedRoute($create_url->getRouteName(), $create_url->getRouteParameters(), $this->currentUser, TRUE);
      $cache->addCacheableDependency($create_access);
      if ($create_access->isAllowed()) {
        $links['tmgmt.translate_local.add'] = [
          'url' => $create_url,
          'title' => $this->t('Translate locally'),
        ];
      }
    }

    // Wrap the form with the multiple translations request form.
    // @see \Drupal\tmgmt_content\Controller\ContentTranslationControllerOverride
    if ($this->entityTypeManager->getAccessControlHandler('tmgmt_job')->createAccess()) {
      $build = $this->formBuilder->getForm('Drupal\tmgmt_content\Form\ContentTranslateForm', $build);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): RouteCollection {
    $collection = new RouteCollection();

    $route = new Route(
      '/admin/oe_translation/translate-local/{entity_type}/{entity}/{source}/{target}',
      [
        '_controller' => '\Drupal\oe_translation\Controller\LocalTasksController::createLocalTranslationTask',
      ],
      [
        '_custom_access' => '\Drupal\oe_translation\Controller\LocalTasksController::createLocalTranslationTaskAccess',
      ],
      [
        'parameters' => [
          'entity' => [
            'type' => 'entity:{entity_type}',
          ],
          'source' => [
            'type' => 'language',
          ],
          'target' => [
            'type' => 'language',
          ],
        ],
      ]
    );

    $collection->add('oe_translation.permission_translator.create_local_task', $route);

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function localTaskItemAccess(LocalTaskItemInterface $task_item, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'update') {
      return AccessResult::neutral();
    }

    $permission = $this->getPermission();
    return AccessResult::allowedIfHasPermission($account, $permission);
  }

  /**
   * {@inheritdoc}
   */
  public function localTaskItemBreadcrumbAlter(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, array $context): void {
    if ($route_match->getRouteName() !== 'entity.tmgmt_local_task_item.canonical') {
      return;
    }

    // Remove all the extra local tasks items in the breadcrumb to which the
    // users will not have access in case the task is using this translator.
    /** @var \Drupal\tmgmt_local\LocalTaskItemInterface $local_task_item */
    $local_task_item = $route_match->getParameter('tmgmt_local_task_item');
    $job_item = $local_task_item->getJobItem();
    $job = $job_item->getJob();
    if ($job->getTranslator()->getPluginId() !== $this->getPluginId()) {
      return;
    }

    $links = $breadcrumb->getLinks();
    array_pop($links);
    array_pop($links);
    array_pop($links);
    array_pop($links);
    $entity = $this->entityTypeManager->getStorage('node')->load($job_item->getItemId());
    $links[] = $entity->toLink(NULL, 'drupal:content-translation-overview');
    $breadcrumb = new Breadcrumb();
    $breadcrumb->setLinks($links);
  }

  /**
   * Returns the users that could use this translator.
   *
   * @return \Drupal\user\UserInterface[]|array
   *   The users.
   */
  public function getAllowedUsers() {
    $roles = user_roles(TRUE, $this->getPermission());
    if (!$roles) {
      return [];
    }

    $role_ids = [];
    foreach ($roles as $role) {
      $role_ids[] = $role->id();
    }

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => $role_ids]);
    if (!$users) {
      return [];
    }

    return $users;
  }

  /**
   * Retrieves the local task item associated with a job item.
   *
   * This is conceptually wrong as multiple local task items can be associated.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item.
   *
   * @return \Drupal\tmgmt_local\LocalTaskItemInterface|null
   *   The local task item if found, NULL otherwise.
   */
  protected function getLocalTaskItemFromJobItem(JobItemInterface $job_item): ?LocalTaskItemInterface {
    $storage = $this->entityTypeManager->getStorage('tmgmt_local_task_item');

    $query = $storage->getQuery();
    $query->condition($job_item->getEntityType()->getKey('id'), $job_item->id());
    $results = $query->execute();

    return !empty($results) ? $storage->load(current($results)) : NULL;
  }

  /**
   * Creates the source data ready to be translated for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param \Drupal\tmgmt_local\LocalTaskItemInterface $task_item
   *   The local task item.
   *
   * @return array
   *   The source data.
   */
  protected function createSourceData(NodeInterface $node, LocalTaskItemInterface $task_item): array {
    $job_item = $task_item->getJobItem();
    $source_plugin = $job_item->getSourcePlugin();
    return $source_plugin instanceof ContentEntitySource ? $source_plugin->extractTranslatableData($node) : [];
  }

  /**
   * Returns the permission needed for this plugin.
   *
   * @return string
   *   The permission.
   */
  protected function getPermission(): string {
    $settings = $this->defaultSettings();
    $permission = $settings['permission'];

    return $permission;
  }

}
