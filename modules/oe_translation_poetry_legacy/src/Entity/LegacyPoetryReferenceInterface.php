<?php

declare(strict_types=1);

namespace Drupal\oe_translation_poetry_legacy\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for the 'Legacy Poetry reference' entity.
 */
interface LegacyPoetryReferenceInterface extends ContentEntityInterface {

  /**
   * Gets the node entity of the Poetry request.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node entity or null if not available.
   */
  public function getNode(): ?NodeInterface;

  /**
   * Sets the node entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\oe_translation_poetry_legacy\Entity\LegacyPoetryReference
   *   The called Legacy Poetry reference entity.
   */
  public function setNode(NodeInterface $node): LegacyPoetryReference;

  /**
   * Gets the Poetry request ID of the node.
   *
   * @return string|null
   *   The Poetry request ID.
   */
  public function getPoetryId(): ?string;

  /**
   * Sets the Poetry request ID of the node.
   *
   * @param string $poetry_id
   *   The Poetry request ID.
   *
   * @return \Drupal\oe_translation_poetry_legacy\Entity\LegacyPoetryReference
   *   The called Legacy Poetry reference entity.
   */
  public function setPoetryId(string $poetry_id): LegacyPoetryReference;

}
