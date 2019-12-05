<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Entity\ContentEntityInterface;
use EC\Poetry\Messages\Components\Identifier;

/**
 * Interface for the Poetry Drupal client.
 */
interface PoetryInterface {

  /**
   * Gets the global identification number.
   *
   * @return string|null
   *   The number.
   */
  public function getGlobalIdentifierNumber(): ?string;

  /**
   * Sets the global identification number.
   *
   * @param string $number
   *   The number.
   */
  public function setGlobalIdentifierNumber(string $number): void;

  /**
   * Returns the identifier for making a translation request for a content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \EC\Poetry\Messages\Components\Identifier
   *   The identifier.
   */
  public function getIdentifierForContent(ContentEntityInterface $entity): Identifier;

  /**
   * Returns the settings configured in the translator.
   *
   * @return array
   *   The settings.
   */
  public function getTranslatorSettings(): array;

  /**
   * Checks if the Poetry service is configured properly to be used.
   *
   * If there are missing settings or configuration, Poetry cannot be used
   * so we use this method to determine this.
   *
   * @return bool
   *   Whether it's available or not.
   */
  public function isAvailable(): bool;

}
