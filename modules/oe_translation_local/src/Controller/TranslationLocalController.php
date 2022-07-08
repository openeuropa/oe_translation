<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_local\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\Event\TranslationAccessEvent;
use Drupal\oe_translation_local\Event\TranslationLocalControllerAlterEvent;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller for the Local translation system.
 */
class TranslationLocalController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The translation source manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationSourceManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The translation providers service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translatorProviders;

  /**
   * Creates a new TranslationLocalController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation\TranslationSourceManagerInterface $translationSourceManager
   *   The translation source manager.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\oe_translation\TranslatorProvidersInterface $translatorProviders
   *   The translation providers service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TranslationSourceManagerInterface $translationSourceManager, Request $request, EventDispatcherInterface $eventDispatcher, TranslatorProvidersInterface $translatorProviders) {
    $this->entityTypeManager = $entityTypeManager;
    $this->translationSourceManager = $translationSourceManager;
    $this->request = $request;
    $this->eventDispatcher = $eventDispatcher;
    $this->translatorProviders = $translatorProviders;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('oe_translation.translation_source_manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('event_dispatcher'),
      $container->get('oe_translation.translator_providers')
    );
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
      '#markup' => $this->t('Local translations for @entity', ['@entity' => $entity->label()]),
    ];
  }

  /**
   * Renders the local translation overview page.
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    $build = [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);

    $cache = CacheableMetadata::createFromObject($entity);

    $languages = $this->languageManager()->getLanguages();
    $source_language = $entity->language();
    unset($languages[$source_language->getId()]);
    $rows = [];

    foreach ($languages as $language) {
      $language_name = $language->getName();
      $langcode = $language->getId();

      $operations = [
        'data' => $this->getOperationLinks($entity, $langcode),
      ];

      $rows[] = [
        'data' => [$language_name, $operations],
        'hreflang' => $language->getId(),
      ];
    }

    $header = [
      $this->t('Language'),
      $this->t('Operations'),
    ];

    $build['local_translation_overview'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    $cache->applyTo($build);

    // Dispatch an event to allow other modules to alter the overview.
    $entity_type_id = $entity_type_id ?? '';
    $event = new TranslationLocalControllerAlterEvent($build, $route_match, $entity_type_id);
    $this->eventDispatcher->dispatch(TranslationLocalControllerAlterEvent::NAME, $event);

    return $event->getBuild();
  }

  /**
   * Creates a local translation request entity and redirects to translate it.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Language\Language $source
   *   The source language.
   * @param \Drupal\Core\Language\Language $target
   *   The target language.
   */
  public function createLocalTranslationRequest(ContentEntityInterface $entity, Language $source, Language $target): RedirectResponse {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')
      ->create([
        'bundle' => 'local',
        'source_language_code' => $source->getId(),
        'target_language_codes' => [$target->getId()],
        'request_status' => TranslationRequestInterface::STATUS_DRAFT,
      ]);
    $request->setContentEntity($entity);

    $data = $this->translationSourceManager->extractData($entity->getUntranslated());
    $request->setData($data);
    $request->save();

    $url = $request->toUrl('local-translation');

    // Pass on any destinations to the actually intended form page.
    if ($destination = $this->request->query->get('destination')) {
      $this->request->query->remove('destination');
      $url->setOption('query', ['destination' => $destination]);
    }

    return new RedirectResponse($url->toString());
  }

  /**
   * Access callback for the translation request creation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Language\Language $source
   *   The source language.
   * @param \Drupal\Core\Language\Language $target
   *   The target language.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function createLocalTranslationRequestAccess(ContentEntityInterface $entity, Language $source, Language $target, AccountInterface $account): AccessResultInterface {
    // Check that the user has the permission.
    $has_permission = $account->hasPermission('translate any entity');
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    if (!$has_permission) {
      $access = AccessResult::forbidden('The user is missing the translation permission.')->addCacheableDependency($cache);
      return $this->dispatchLocalTranslationAccessEvent($entity, $account, $access, $source, $target);
    }

    // Check that the entity type is using our translation system and it has
    // local translation enabled.
    if (!$this->translatorProviders->hasLocal($entity->getEntityType())) {
      return AccessResult::forbidden('The entity type is not using local translations.')->addCacheableDependency($cache);
    }

    // Check that there are no translation requests already for this entity.
    $translation_requests = $this->getLocalTranslationRequests($entity, $target->getId());
    $cache->addCacheTags(['oe_translation_request_list']);
    if (!$translation_requests) {
      $access = AccessResult::allowed()->addCacheableDependency($cache);
      return $this->dispatchLocalTranslationAccessEvent($entity, $account, $access, $source, $target);
    }

    $access = AccessResult::forbidden('There is already a translation request for this entity version.')->addCacheableDependency($cache);
    return $this->dispatchLocalTranslationAccessEvent($entity, $account, $access, $source, $target);
  }

  /**
   * Dispatches an event to allow others to have a say in the access.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access.
   * @param \Drupal\Core\Language\Language $source
   *   The source language.
   * @param \Drupal\Core\Language\Language $target
   *   The target language.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  protected function dispatchLocalTranslationAccessEvent(ContentEntityInterface $entity, AccountInterface $account, AccessResultInterface $access, Language $source = NULL, Language $target = NULL): AccessResultInterface {
    $event = new TranslationAccessEvent($entity, $account, $access, $source, $target);
    $this->eventDispatcher->dispatch($event, TranslationAccessEvent::EVENT);
    return $event->getAccess();
  }

  /**
   * Title callback for the translation request form to translate locally.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request entity.
   *
   * @return array
   *   The title.
   */
  public function translateLocalFormTitle(TranslationRequestInterface $oe_translation_request): array {
    $entity = $oe_translation_request->getContentEntity();
    return [
      '#markup' => $this->t('Translate @title in @language', [
        '@title' => $entity->label(),
        '@language' => $this->getTargetLanguageFromRequest($oe_translation_request)->getName(),
      ]),
    ];
  }

  /**
   * Returns the local translation requests for this entity revision.
   *
   * It only includes the ones that have the give language as target and that
   * are not synced already. If they are synced, their job is officially done.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The target langcode.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface[]
   *   Translation requests for this entity revision.
   */
  protected function getLocalTranslationRequests(ContentEntityInterface $entity, string $langcode): array {
    /** @var \Drupal\oe_translation\TranslationRequestStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('oe_translation_request');
    $translation_requests = $storage->getTranslationRequestsForEntityRevision($entity, 'local');
    return array_filter($translation_requests, function (TranslationRequestInterface $translation_request) use ($langcode) {
      return in_array($langcode, $translation_request->getTargetLanguageCodes()) && $translation_request->getRequestStatus() !== TranslationRequestInterface::STATUS_SYNCHRONISED;
    });
  }

  /**
   * Creates the operation links for a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   The links.
   */
  protected function getOperationLinks(ContentEntityInterface $entity, string $langcode): array {
    $translation_requests = $this->getLocalTranslationRequests($entity, $langcode);

    if ($translation_requests) {
      // Normally there should only be 1 request. And we use the default
      // operations links for the entity.
      $translation_request = reset($translation_requests);
      $links = $translation_request->getOperationsLinks();
      $links['#links']['edit']['title'] = $this->t('Edit started translation request');
    }
    else {
      $links = [
        '#type' => 'operations',
        '#links' => [],
      ];
      // If there are no translations requests already for this language, we
      // can add a link to start one.
      $cache = new CacheableMetadata();
      $link = TranslationRequest::getCreateOperationLink($entity, $langcode, $cache);
      $cache->applyTo($links);
      if ($link) {
        $links['#links']['create'] = $link;
      }
    }

    return $links;
  }

  /**
   * Returns the target language from the translation request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The target language.
   */
  protected function getTargetLanguageFromRequest(TranslationRequestInterface $translation_request): LanguageInterface {
    $languages = $translation_request->getTargetLanguageCodes();
    $language = reset($languages);
    return $this->entityTypeManager->getStorage('configurable_language')->load($language);
  }

}
