<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_local\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\oe_translation\Event\TranslationRequestOperationsProviderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translations request operations provider event.
 */
class TranslationRequestOperationsProviderSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [TranslationRequestOperationsProviderEvent::NAME => 'addOperations'];
  }

  /**
   * Adds the local translations operations.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestOperationsProviderEvent $event
   *   The event.
   */
  public function addOperations(TranslationRequestOperationsProviderEvent $event) {
    $request = $event->getRequest();
    $links = $event->getOperations();

    $cache = CacheableMetadata::createFromRenderArray($links);

    $edit = $request->toUrl('local-translation');
    $edit_access = $edit->access(NULL, TRUE);
    $cache->addCacheableDependency($edit_access);
    if ($edit_access->isAllowed()) {
      // Put the edit link first.
      $existing = $links['#links'];
      $links['#links'] = [];
      $links['#links']['edit'] = [
        'title' => t('Edit started translation request'),
        'url' => $edit,
      ];
      $links['#links'] += $existing;
    }

    $cache->applyTo($links);
    $event->setOperations($links);
  }

}
