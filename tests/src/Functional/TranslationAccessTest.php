<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Core\Url;

/**
 * Tests access rules.
 */
class TranslationAccessTest extends TranslationTestBase {

  /**
   * Tests that certain routes are not accessible to any user.
   *
   * Some routes are never supposed to be accessed because they can break the
   * correct functioning of the translation system.
   */
  public function testAccessDenied(): void {
    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    $routes = [
      // The TMGMT sources page.
      'tmgmt.source_overview_default',
      // The TMGT cart page.
      'tmgmt.cart',
    ];
    foreach ($routes as $route) {
      $this->drupalGet(Url::fromRoute($route));
      $this->assertSession()->statusCodeEquals(403);
    }
  }

}
