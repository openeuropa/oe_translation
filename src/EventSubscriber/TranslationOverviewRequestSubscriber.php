<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
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
   * TranslationOverviewRequestSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager) {
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
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
  public function onRequest(GetResponseEvent $event): void {
    if (!preg_match('/^entity.([^\.]+).content_translation_overview$/', $this->routeMatch->getRouteName(), $matches)) {
      return;
    }

    if (count($matches) < 2) {
      return;
    }

    $entity_type = $matches[1];
    if (!$this->entityTypeManager->getDefinition($entity_type)) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->routeMatch->getParameter($entity_type);
    if ($entity->isDefaultTranslation()) {
      return;
    }

    $entity = $entity->getUntranslated();
    $event->setResponse(new RedirectResponse($entity->toUrl('drupal:content-translation-overview')->toString()));
  }

}
