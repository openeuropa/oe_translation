<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Model;

use Symfony\Component\Serializer\Attribute\SerializedPath;

/**
 * Defines the DTO class for transaction.
 */
class Transaction {

  #[SerializedPath('[TransactionHeader][SenderDetails][@drupalVersion]')]
  /**
   * The version of Drupal core.
   */
  protected string $drupalVersion;

  #[SerializedPath('[TransactionHeader][SenderDetails][@moduleVersion]')]
  /**
   * The version of the module.
   */
  protected string $moduleVersion;

  #[SerializedPath('[TransactionHeader][SenderDetails][ProducerDateTime]')]
  /**
   * The date of the file.
   *
   * @var \DateTimeInterface
   */
  protected \DateTimeInterface $producerDateTime;

  #[SerializedPath('[TransactionIdentifier]')]
  /**
   * The transaction ID.
   */
  protected string $transactionId;

  #[SerializedPath('[TransactionCode]')]
  /**
   * The transaction code.
   */
  protected string $transactionCode;

  #[SerializedPath('[TransactionData][TranslationDetails][Translation]')]
  /**
   * The language items to translate.
   *
   * @var \Drupal\oe_translation_cdt\Model\TransactionItem[]
   */
  protected array $transactionItems;

  #[SerializedPath('[TransactionData][TranslationDetails][TotalCharacterLength]')]
  /**
   * The total character length.
   */
  protected int $totalCharacterLength;

  /**
   * Gets the Drupal version.
   *
   * @return string
   *   The Drupal version.
   */
  public function getDrupalVersion(): string {
    return $this->drupalVersion;
  }

  /**
   * Sets the Drupal version.
   *
   * @param string $drupalVersion
   *   The Drupal version.
   */
  public function setDrupalVersion(string $drupalVersion): self {
    $this->drupalVersion = $drupalVersion;
    return $this;
  }

  /**
   * Gets the module version.
   *
   * @return string
   *   The module version.
   */
  public function getModuleVersion(): string {
    return $this->moduleVersion;
  }

  /**
   * Sets the module version.
   *
   * @param string $moduleVersion
   *   The module version.
   */
  public function setModuleVersion(string $moduleVersion): self {
    $this->moduleVersion = $moduleVersion;
    return $this;
  }

  /**
   * Gets the producer datetime.
   *
   * @return \DateTimeInterface
   *   The producer datetime.
   */
  public function getProducerDateTime(): \DateTimeInterface {
    return $this->producerDateTime;
  }

  /**
   * Sets the producer datetime.
   *
   * @param \DateTimeInterface $producerDateTime
   *   The producer datetime.
   */
  public function setProducerDateTime(\DateTimeInterface $producerDateTime): self {
    $this->producerDateTime = $producerDateTime;
    return $this;
  }

  /**
   * Gets the transaction identifier.
   *
   * @return string
   *   The transaction identifier.
   */
  public function getTransactionId(): string {
    return $this->transactionId;
  }

  /**
   * Sets the transaction identifier.
   *
   * @param string $transactionId
   *   The transaction identifier.
   */
  public function setTransactionId(string $transactionId): self {
    $this->transactionId = $transactionId;
    return $this;
  }

  /**
   * Gets the transaction code.
   *
   * @return string
   *   The transaction code.
   */
  public function getTransactionCode(): string {
    return $this->transactionCode;
  }

  /**
   * Sets the transaction code.
   *
   * @param string $transactionCode
   *   The transaction code.
   */
  public function setTransactionCode(string $transactionCode): self {
    $this->transactionCode = $transactionCode;
    return $this;
  }

  /**
   * Gets the transaction items.
   *
   * @return TransactionItem[]
   *   The transaction items.
   */
  public function getTransactionItems(): array {
    return $this->transactionItems;
  }

  /**
   * Sets the transaction items.
   *
   * @param TransactionItem[] $transactionItems
   *   The transaction items.
   */
  public function setTransactionItems(array $transactionItems): self {
    $this->transactionItems = $transactionItems;
    return $this;
  }

  /**
   * Gets the total character length.
   *
   * @return int
   *   The total character length.
   */
  public function getTotalCharacterLength(): int {
    return $this->totalCharacterLength;
  }

  /**
   * Sets the total character length.
   *
   * @param int $totalCharacterLength
   *   The total character length.
   */
  public function setTotalCharacterLength(int $totalCharacterLength): self {
    $this->totalCharacterLength = $totalCharacterLength;
    return $this;
  }

}
