<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_test\Controller;

/**
 * Controller for testing purposes.
 */
class TestController {

  /**
   * Test controller.
   *
   * @return array
   *   Test markup.
   */
  public function testRoute(): array {
    return [
      '#markup' => 'Route works',
    ];
  }

}
