<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Event for determining access to accept a translation request.
 */
class TranslationRequestAcceptAccessEvent extends TranslationRequestAccessEventBase {

  /**
   * Constructs a new TranslationRequestAcceptAccessEvent.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translationRequest
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access result.
   */
  public function __construct(protected TranslationRequestInterface $translationRequest, AccountInterface $account, AccessResultInterface $access) {
    parent::__construct($account, $access);
  }

  /**
   * Returns the translation request.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The translation request.
   */
  public function getTranslationRequest(): TranslationRequestInterface {
    return $this->translationRequest;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ?ContentEntityInterface {
    return $this->translationRequest->getContentEntity();
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle(): string {
    return $this->translationRequest->bundle();
  }

}
