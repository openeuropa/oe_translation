<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation_etrans\Event\EtransTranslationRequestResolverEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Resolves the translation request entity from the Etrans remote ID.
 */
class EtransTranslationRequestRemoteIdResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a EtransTranslationRequestRemoteIdResolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher) {
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Resolves the translation request from the remote ID and token.
   *
   * @param string $request_id
   *   The remote request ID.
   * @param string|null $token
   *   The token.
   *
   * @return \Drupal\oe_translation_etrans\TranslationRequestEtransInterface|null
   *   The translation request entity.
   */
  public function resolveTranslationRequest(string $request_id, ?string $token = NULL): ?TranslationRequestEtransInterface {
    $request = NULL;

    $query = $this->entityTypeManager->getStorage('oe_translation_request')
      ->getQuery()
      ->condition('remote_id', $request_id);
    if ($token) {
      $query->condition('etrans_access_token', $token);
    }
    $ids = $query->accessCheck(FALSE)->execute();

    if ($ids) {
      $id = reset($ids);
      $request = $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
    }

    $event = new EtransTranslationRequestResolverEvent($request_id, $token);
    $event->setRequest($request);
    $this->eventDispatcher->dispatch($event, EtransTranslationRequestResolverEvent::class);

    return $event->getRequest();
  }

}
