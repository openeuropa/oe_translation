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
   * @param string $drupal_version
   *   The Drupal version.
   */
  public function setDrupalVersion(string $drupal_version): self {
    $this->drupalVersion = $drupal_version;
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
   * @param string $module_version
   *   The module version.
   */
  public function setModuleVersion(string $module_version): self {
    $this->moduleVersion = $module_version;
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
   * @param \DateTimeInterface $producer_date_time
   *   The producer datetime.
   */
  public function setProducerDateTime(\DateTimeInterface $producer_date_time): self {
    $this->producerDateTime = $producer_date_time;
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
   * @param string $transaction_id
   *   The transaction identifier.
   */
  public function setTransactionId(string $transaction_id): self {
    $this->transactionId = $transaction_id;
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
   * @param string $transaction_code
   *   The transaction code.
   */
  public function setTransactionCode(string $transaction_code): self {
    $this->transactionCode = $transaction_code;
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
   * @param TransactionItem[] $transaction_items
   *   The transaction items.
   */
  public function setTransactionItems(array $transaction_items): self {
    $this->transactionItems = $transaction_items;
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
   * @param int $total_character_length
   *   The total character length.
   */
  public function setTotalCharacterLength(int $total_character_length): self {
    $this->totalCharacterLength = $total_character_length;
    return $this;
  }

}
