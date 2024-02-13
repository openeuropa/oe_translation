<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Event\TranslationRequestOperationsProviderEvent;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translations request operations provider event.
 */
class TranslationRequestOperationsProviderSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The remote translation provider manager.
   *
   * @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager
   */
  protected $providerManager;

  /**
   * Constructs a TranslationRequestOperationsProviderSubscriber.
   *
   * @param \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager $providerManager
   *   The remote translation provider manager.
   */
  public function __construct(RemoteTranslationProviderManager $providerManager) {
    $this->providerManager = $providerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [TranslationRequestOperationsProviderEvent::NAME => 'addOperations'];
  }

  /**
   * Adds the view link to the operations.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestOperationsProviderEvent $event
   *   The event.
   */
  public function addOperations(TranslationRequestOperationsProviderEvent $event) {
    $request = $event->getRequest();
    $bundles = $this->providerManager->getRemoteTranslationBundles();
    if (!in_array($request->bundle(), $bundles)) {
      return;
    }

    $links = $event->getOperations();

    $cache = CacheableMetadata::createFromRenderArray($links);
    $view = $request->toUrl();
    $view_access = $view->access(NULL, TRUE);
    if ($view_access->isAllowed()) {
      // Put the view link first.
      $existing = $links['#links'];
      $links['#links'] = [];
      $links['#links']['view'] = [
        'title' => $this->t('View'),
        'url' => $view,
      ];
      $links['#links'] += $existing;
    }

    $cache->applyTo($links);
    $event->setOperations($links);
  }

}
