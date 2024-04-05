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
   * @param string $instance_id
   *   The instance ID.
   */
  public function setInstanceId(string $instance_id): self {
    $this->instanceId = $instance_id;
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
   * @param string $resource_name
   *   The resource name.
   */
  public function setResourceName(string $resource_name): self {
    $this->resourceName = $resource_name;
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
   * @param string $resource_type
   *   The resource type.
   */
  public function setResourceType(string $resource_type): self {
    $this->resourceType = $resource_type;
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
   * @param string $resource_max_size
   *   The resource max size.
   */
  public function setResourceMaxSize(string $resource_max_size): self {
    $this->resourceMaxSize = $resource_max_size;
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
   * @param string $resource_label
   *   The resource label.
   */
  public function setResourceLabel(string $resource_label): self {
    $this->resourceLabel = $resource_label;
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
