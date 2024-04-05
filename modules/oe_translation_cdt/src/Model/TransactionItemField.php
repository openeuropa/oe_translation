<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Model;

use Symfony\Component\Serializer\Attribute\SerializedPath;

/**
 * Defines the DTO class for transaction item's field.
 */
class TransactionItemField {

  #[SerializedPath('[@indexType]')]
  /**
   * The index type.
   */
  protected string $indexType;

  #[SerializedPath('[@instanceId]')]
  /**
   * The instance ID.
   */
  protected string $instanceId;

  #[SerializedPath('[@resName]')]
  /**
   * The resource name.
   */
  protected string $resourceName;

  #[SerializedPath('[@resType]')]
  /**
   * The resource type.
   */
  protected string $resourceType;

  #[SerializedPath('[@resMaxSize]')]
  /**
   * The resource max size.
   */
  protected string $resourceMaxSize;

  #[SerializedPath('[@resLabel]')]
  /**
   * The resource label.
   */
  protected string $resourceLabel;

  #[SerializedPath('[#]')]
  /**
   * The content.
   */
  protected string $content;

  /**
   * Gets the index type.
   *
   * @return string
   *   The index type.
   */
  public function getIndexType(): string {
    return $this->indexType;
  }

  /**
   * Sets the index type.
   *
   * @param string $indexType
   *   The index type.
   */
  public function setIndexType(string $indexType): self {
    $this->indexType = $indexType;
    return $this;
  }

  /**
   * Gets the instance ID.
   *
   * @return string
   *   The instance ID.
   */
  public function getInstanceId(): string {
    return $this->instanceId;
  }

  /**
   * Sets the instance ID.
   *
   * @param string $instanceId
   *   The instance ID.
   */
  public function setInstanceId(string $instanceId): self {
    $this->instanceId = $instanceId;
    return $this;
  }

  /**
   * Gets the resource name.
   *
   * @return string
   *   The resource name.
   */
  public function getResourceName(): string {
    return $this->resourceName;
  }

  /**
   * Sets the resource name.
   *
   * @param string $resourceName
   *   The resource name.
   */
  public function setResourceName(string $resourceName): self {
    $this->resourceName = $resourceName;
    return $this;
  }

  /**
   * Gets the resource type.
   *
   * @return string
   *   The resource type.
   */
  public function getResourceType(): string {
    return $this->resourceType;
  }

  /**
   * Sets the resource type.
   *
   * @param string $resourceType
   *   The resource type.
   */
  public function setResourceType(string $resourceType): self {
    $this->resourceType = $resourceType;
    return $this;
  }

  /**
   * Gets the resource max size.
   *
   * @return string
   *   The resource max size.
   */
  public function getResourceMaxSize(): ?string {
    return $this->resourceMaxSize;
  }

  /**
   * Sets the resource max size.
   *
   * @param string $resourceMaxSize
   *   The resource max size.
   */
  public function setResourceMaxSize(string $resourceMaxSize): self {
    $this->resourceMaxSize = $resourceMaxSize;
    return $this;
  }

  /**
   * Gets the resource label.
   *
   * @return string
   *   The resource label.
   */
  public function getResourceLabel(): string {
    return $this->resourceLabel;
  }

  /**
   * Sets the resource label.
   *
   * @param string $resourceLabel
   *   The resource label.
   */
  public function setResourceLabel(string $resourceLabel): self {
    $this->resourceLabel = $resourceLabel;
    return $this;
  }

  /**
   * Gets the content.
   *
   * @return string
   *   The content.
   */
  public function getContent(): string {
    return $this->content;
  }

  /**
   * Sets the content.
   *
   * @param string $content
   *   The content.
   */
  public function setContent(string $content): self {
    $this->content = $content;
    return $this;
  }

}
