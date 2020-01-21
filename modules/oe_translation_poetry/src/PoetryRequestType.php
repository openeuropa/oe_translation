<?php

namespace Drupal\oe_translation_poetry;

/**
 * Represents a request type that can be made to Poetry.
 */
class PoetryRequestType {

  /**
   * Indicates a request for a new translation for an entity.
   */
  const NEW = 'NEW';

  /**
   * Indicates a request for an update of a  translation for an entity.
   */
  const UPDATE = 'UPDATE';

  /**
   * The reason for the request type.
   *
   * @var string|null
   */
  protected $message = NULL;

  /**
   * The request type.
   *
   * @var string
   */
  protected $requestType = self::NEW;

  /**
   * PoetryRequestType constructor.
   *
   * @param string $requestType
   *   The default request type.
   */
  public function __construct(string $requestType) {
    $this->requestType = $requestType;
    $this->message = NULL;
  }

  /**
   * Returns the reason for the request type.
   *
   * @return string|null
   *   The message.
   */
  public function getMessage(): ?string {
    return $this->message;
  }

  /**
   * Sets the reason for the request type.
   *
   * @param string $message
   *   The reason.
   */
  public function setMessage(string $message): void {
    $this->message = $message;
  }

  /**
   * Returns the request type.
   *
   * @return string
   *   The request type.
   */
  public function getType(): string {
    return $this->requestType;
  }

  /**
   * Sets the request type.
   *
   * @param string $requestType
   *   The request type.
   */
  public function setRequestType(string $requestType): void {
    $this->requestType = $requestType;
    $this->message = NULL;
  }

}
