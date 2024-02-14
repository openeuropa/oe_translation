<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines remote_translation_provider annotation object.
 *
 * @Annotation
 */
class RemoteTranslationProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the translation provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The description of the translation provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * An array with settings of the translator.
   *
   * @var array
   */
  public $settings = [];

}
