<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Remote translator provider configuration entity interface.
 */
interface RemoteTranslatorProviderInterface extends ConfigEntityInterface {

  /**
   * Retrieves the remote translation provider plugin.
   *
   * @return string
   *   The plugin.
   */
  public function getProviderPlugin(): ?string;

  /**
   * Sets the remote translation provider plugin.
   *
   * @param string $plugin
   *   The plugin to be set.
   *
   * @return RemoteTranslatorProviderInterface
   *   The called plugin.
   */
  public function setProviderPlugin(string $plugin): RemoteTranslatorProviderInterface;

  /**
   * Retrieves the remote translator provider plugin configuration.
   *
   * @return array
   *   Array of provider settings data.
   */
  public function getProviderConfiguration(): ?array;

  /**
   * Sets the remote translation provider plugin configuration.
   *
   * @param array $configuration
   *   The provider settings to be set.
   *
   * @return RemoteTranslatorProviderInterface
   *   The called plugin.
   */
  public function setProviderConfiguration(array $configuration): RemoteTranslatorProviderInterface;

  /**
   * Returns whether the translator is enabled.
   *
   * @return bool
   *   Whether it's enabled or not.
   */
  public function isEnabled(): bool;

}
