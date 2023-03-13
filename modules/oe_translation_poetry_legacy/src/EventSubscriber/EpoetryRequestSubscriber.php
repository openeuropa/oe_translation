<?php

declare(strict_types=1);

namespace Drupal\oe_translation_poetry_legacy\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\oe_translation_epoetry\Event\EpoetryRequestEvent;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use OpenEuropa\EPoetry\Request\Type\AddNewPartToDossier;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\ResubmitRequest;
use Phpro\SoapClient\Type\RequestInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the ePoetry request event.
 */
class EpoetryRequestSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a EpoetryRequestSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [EpoetryRequestEvent::NAME => 'epoetryRequest'];
  }

  /**
   * Sets onto the request the legacy Poetry request ID.
   *
   * @param \Drupal\oe_translation_epoetry\Event\EpoetryRequestEvent $event
   *   The event.
   */
  public function epoetryRequest(EpoetryRequestEvent $event): void {
    $object = $event->getRequest();
    $request = $event->getTranslationRequest();
    $entity = $request->getContentEntity();
    if (!$entity instanceof NodeInterface) {
      // We only care about nodes.
      return;
    }

    // Check if this node has any previous translation requests that have not
    // failed or been rejected. If they have, it means that they could be
    // missing the legacy ID. If, however, any requests are found, it means
    // we don't do anything because the previous request is supposed to have
    // already included the legacy poetry ID in the first place.
    $statuses = [
      TranslationRequestEpoetryInterface::STATUS_REQUEST_FAILED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_FAILED_FINISHED,
    ];
    $ids = $this->entityTypeManager->getStorage('oe_translation_request')->getQuery()
      ->condition('content_entity__entity_type', $entity->getEntityTypeId())
      ->condition('content_entity__entity_id', $entity->id())
      ->condition('id', $request->id(), '!=')
      ->condition('request_status', $statuses, 'NOT IN')
      ->condition('epoetry_status', TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED, '!=')
      ->execute();

    if ($ids) {
      return;
    }

    $ids = $this->entityTypeManager->getStorage('poetry_legacy_reference')->getQuery()
      ->condition('node', $entity->id())
      ->execute();

    if (!$ids) {
      return;
    }

    // We only have 1 record per node.
    $id = reset($ids);
    $legacy_reference = $this->entityTypeManager->getStorage('poetry_legacy_reference')->load($id);
    if (!$legacy_reference->getPoetryId()) {
      // This should not happen.
      return;
    }
    $this->includeLegacyReference($object, $legacy_reference->getPoetryId());
  }

  /**
   * Sets the legacy Poetry ID onto the request.
   *
   * @param \Phpro\SoapClient\Type\RequestInterface $object
   *   The object.
   * @param string $poetry_id
   *   The Poetry ID.
   */
  protected function includeLegacyReference(RequestInterface $object, string $poetry_id): void {
    $class = get_class($object);
    switch ($class) {
      case CreateLinguisticRequest::class:
        /** @var \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest $object */
        $object->getRequestDetails()->setComment($object->getRequestDetails()->getComment() . '. Poetry legacy request ID: ' . $poetry_id);
        break;

      case AddNewPartToDossier::class:
        /** @var \OpenEuropa\EPoetry\Request\Type\AddNewPartToDossier $object */
        $object->getRequestDetails()->setComment($object->getRequestDetails()->getComment() . '. Poetry legacy request ID: ' . $poetry_id);
        break;

      case ResubmitRequest::class:
        /** @var \OpenEuropa\EPoetry\Request\Type\ResubmitRequest $object */
        $object->getResubmitRequest()->getRequestDetails()->setComment($object->getResubmitRequest()->getRequestDetails()->getComment() . '. Poetry legacy request ID: ' . $poetry_id);
        break;

      // For the new version requests, we don't need handling because it's
      // assumed there were previous requests already made for that node.
    }
  }

}
