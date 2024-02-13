<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\Event\TranslationRequestOperationsProviderEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provider service for translation request operation links.
 */
class TranslationRequestOperationsProvider {

  use StringTranslationTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a TranslationRequestOperationsProvider.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Returns the operations links render array.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request.
   *
   * @return array
   *   The links array.
   */
  public function getOperationsLinks(TranslationRequestInterface $request): array {
    $links = [
      '#type' => 'operations',
      '#links' => [],
    ];
    $cache = new CacheableMetadata();

    // By default, we only have the delete link.
    $delete = $request->toUrl('delete-form');
    $query = $delete->getOption('query');
    $query['destination'] = Url::fromRoute('<current>')->toString();
    $delete->setOption('query', $query);
    $delete_access = $delete->access(NULL, TRUE);
    $cache->addCacheableDependency($delete_access);
    if ($delete_access->isAllowed()) {
      $links['#links']['delete'] = [
        'title' => $this->t('Delete'),
        'url' => $delete,
      ];
    }

    $cache->applyTo($links);

    $event = new TranslationRequestOperationsProviderEvent($request, $links);
    $this->eventDispatcher->dispatch($event, TranslationRequestOperationsProviderEvent::NAME);

    return $event->getOperations();
  }

}
