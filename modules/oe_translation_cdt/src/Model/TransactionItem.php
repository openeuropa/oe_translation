<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Model;

use Symfony\Component\Serializer\Attribute\SerializedPath;

/**
 * Defines the DTO class for transaction's item.
 */
class TransactionItem {

  #[SerializedPath('[PreviewLink]')]
  /**
   * The preview link.
   */
  protected string $previewLink;

  #[SerializedPath('[SourceReference]')]
  /**
   * The source reference.
   */
  protected string $sourceReference;

  #[SerializedPath('[StaticContent]')]
  /**
   * The transaction item fields.
   *
   * @var \Drupal\oe_translation_cdt\Model\TransactionItemField[]
   */
  protected array $transactionItemFields;

  /**
   * Gets the preview link.
   *
   * @return string
   *   The preview link.
   */
  public function getPreviewLink(): string {
    return $this->previewLink;
  }

  /**
   * Sets the preview link.
   *
   * @param string $preview_link
   *   The preview link.
   */
  public function setPreviewLink(string $preview_link): self {
    $this->previewLink = $preview_link;
    return $this;
  }

  /**
   * Gets the source reference.
   *
   * @return string
   *   The source reference.
   */
  public function getSourceReference(): string {
    return $this->sourceReference;
  }

  /**
   * Sets the source reference.
   *
   * @param string $source_reference
   *   The source reference.
   */
  public function setSourceReference(string $source_reference): self {
    $this->sourceReference = $source_reference;
    return $this;
  }

  /**
   * Gets the transaction item fields.
   *
   * @return TransactionItemField[]
   *   The transaction item fields.
   */
  public function getTransactionItemFields(): array {
    return $this->transactionItemFields;
  }

  /**
   * Sets the transaction item fields.
   *
   * @param TransactionItemField[] $transaction_item_fields
   *   The transaction item fields.
   */
  public function setTransactionItemFields(array $transaction_item_fields): self {
    $this->transactionItemFields = $transaction_item_fields;
    return $this;
  }

}
