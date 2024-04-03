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
   * @param string $previewLink
   *   The preview link.
   */
  public function setPreviewLink(string $previewLink): self {
    $this->previewLink = $previewLink;
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
   * @param string $sourceReference
   *   The source reference.
   */
  public function setSourceReference(string $sourceReference): self {
    $this->sourceReference = $sourceReference;
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
   * @param TransactionItemField[] $transactionItemFields
   *   The transaction item fields.
   */
  public function setTransactionItemFields(array $transactionItemFields): self {
    $this->transactionItemFields = $transactionItemFields;
    return $this;
  }

}
