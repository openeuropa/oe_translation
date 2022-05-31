<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_remote\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;

/**
 * Tests the remote translations.
 */
class RemoteTranslationTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_remote',
  ];

  /**
   * Tests the remote translation route.
   */
  public function testRemoteTranslationRoute(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test node',
    ]);
    $node->save();

    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->assertSession()->linkExists('Remote translations');
    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains('The remote translations overview page.');
  }

}
