<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
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
    if ($request->bundle() !== 'epoetry') {
      return;
    }

    $links = $event->getOperations();

    $cache = CacheableMetadata::createFromRenderArray($links);
    $url = Url::fromRoute('oe_translation_epoetry.failed_to_finished', ['translation_request' => $request->id()], ['query' => ['destination' => Url::fromRoute('<current>')->toString()]]);
    $access = $url->access(NULL, TRUE);
    $cache->addCacheableDependency($access);
    if ($access->isAllowed()) {
      $links['#links']['edit'] = [
        'title' => t('Mark as finished'),
        'url' => $url,
      ];
    }

    $cache->applyTo($links);
    $event->setOperations($links);
  }

}
