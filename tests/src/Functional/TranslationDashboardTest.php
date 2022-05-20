<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;

/**
 * Tests the translation dashboard of a node.
 */
class TranslationDashboardTest extends TranslationTestBase {

  /**
   * Tests the dashboard route works.
   */
  public function testTranslationDashboardRoute(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test node',
    ]);
    $node->save();

    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->assertSession()->pageTextContains('The translation dashboard to come.');
    $this->assertSession()->linkExists('Dashboard');
    $this->assertSession()->linkExists('Local translations');

    // Test that we redirect to the source language if we try to access it in
    // another language.
    // @see TranslationOverviewRequestSubscriber
    $url = $node->toUrl('drupal:content-translation-overview');
    $language = ConfigurableLanguage::load('fr');
    $url->setOption('language', $language);
    $this->drupalGet($url);
    $this->assertSession()->addressEquals('/en/node/1/translations');

  }

}
