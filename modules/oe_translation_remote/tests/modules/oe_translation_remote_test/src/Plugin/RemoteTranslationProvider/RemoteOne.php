<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote_test\Plugin\RemoteTranslationProvider;

use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderBase;

/**
 * Provides a test Remote translator provider plugin.
 *
 * @RemoteTranslationProvider(
 *   id = "remote_one",
 *   label = @Translation("Remote one"),
 *   description = @Translation("Remote one translator provider plugin."),
 * )
 */
class RemoteOne extends RemoteTranslationProviderBase {}
