<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Used for gathering operations links for translation requests.
 */
class TranslationRequestOperationsProviderEvent extends Event {

  /**
   * The event name.
   */
  const NAME = 'oe_translation.operations_provider_event';

  /**
   * The translation request.
   *
   * @var \Drupal\oe_translation\Entity\TranslationRequestInterface
   */
  protected $request;

  /**
   * The operations array.
   *
   * @var array
   */
  protected $operations;

  /**
   * Constructs a TranslationRequestOperationsProviderEvent.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request.
   * @param array $operations
   *   The operations array.
   */
  public function __construct(TranslationRequestInterface $request, array $operations) {
    $this->request = $request;
    $this->operations = $operations;
  }

  /**
   * Returns the request.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The translation request.
   */
  public function getRequest(): TranslationRequestInterface {
    return $this->request;
  }

  /**
   * Returns the operations.
   *
   * @return array
   *   The operations.
   */
  public function getOperations(): array {
    return $this->operations;
  }

  /**
   * Sets the operations.
   *
   * @param array $operations
   *   The operations.
   */
  public function setOperations(array $operations): void {
    $this->operations = $operations;
  }

}
