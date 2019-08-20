<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for Kernel tests that test translation functionality.
 */
class TranslationKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'field',
    'link',
    'text',
    'options',
    'user',
    'language',
    'oe_multilingual',
    'tmgmt',
    'content_translation',
    'oe_translation',
    'views',
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'system',
      'node',
      'field',
      'link',
      'language',
      'oe_multilingual',
      'tmgmt',
      'oe_translation',
      'views',
      'user',
    ]);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
  }

}
