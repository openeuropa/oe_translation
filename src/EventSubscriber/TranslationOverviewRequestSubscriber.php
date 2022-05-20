<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to the Kernel request event.
 *
 * If the user is trying to access the content translation overview page of a
 * given entity in a language other than the source (original) language of that
 * entity, we redirect to the same page in the source language. This is to
 * prevent users from making translations with the source language other than
 * the actual original language of the entity.
 */
class TranslationOverviewRequestSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The translation providers service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translatorProviders;

  /**
   * TranslationOverviewRequestSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\oe_translation\TranslatorProvidersInterface $translatorProviders
   *   The translation providers service.
   */
  public function __construct(RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, TranslatorProvidersInterface $translatorProviders) {
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->translatorProviders = $translatorProviders;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => 'onRequest',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onRequest(RequestEvent $event): void {
    if (!preg_match('/^entity.([^\.]+).content_translation_overview$/', $this->routeMatch->getRouteName(), $matches)) {
      return;
    }

    if (count($matches) < 2) {
      return;
    }

    $entity_type = $matches[1];
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      return;
    }

    $definition = $this->entityTypeManager->getDefinition($entity_type);
    if (!$this->translatorProviders->hasTranslators($definition)) {
      return;
    }

    $current_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->routeMatch->getParameter($entity_type);

    // We only redirect if the current language is not that of the entity
    // default language.
    if ($entity->getUntranslated()->language()->getId() === $current_language->getId()) {
      return;
    }

    $entity = $entity->getUntranslated();
    $event->setResponse(new RedirectResponse($entity->toUrl('drupal:content-translation-overview')->toString()));

    $destination = $event->getRequest()->query->get('destination');
    if ($destination) {
      $event->getRequest()->query->remove('destination');
    }
  }

}
