<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

/**
 * Various tests of the translation system.
 *
 * @group batch1
 */
class TranslationGenericTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('content_translation.manager')->setEnabled('menu_link_content', 'menu_link_content', TRUE);
    \Drupal::service('router.builder')->rebuild();
  }

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
    $this->assertSession()->pageTextContains('Translation dashboard for Test node');
    $this->assertSession()->pageTextContains('There are no open local translation requests');
    $this->assertSession()->linkExists('Dashboard');
    $this->assertSession()->linkExists('Local translations');
  }

  /**
   * Tests the access to the Drupal core translation page.
   */
  public function testTranslationAccess(): void {
    \Drupal::service('module_installer')->install(['oe_translation_menu_link_test']);
    // Check that the route for creating a menu link translation is accessible
    // even if remote translation is enabled for it (but not local).
    $menu_link_content = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'http://example.com'],
      'title' => 'Link test',
    ]);
    $menu_link_content->save();

    $this->drupalGet($menu_link_content->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');
    $this->assertSession()->linkExists('Remote translations');
    $this->assertSession()->linkExists('Add');

    // Menu links should be translatable using the regular core way.
    $url = $menu_link_content->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'bg');
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('Create Bulgarian translation of Link test');

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'My node',
    ]);

    $node->save();

    // Nodes should not be translatable using the regular core way.
    $url = $node->toUrl('drupal:content-translation-add');
    $url->setRouteParameter('source', 'en');
    $url->setRouteParameter('target', 'bg');
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    // Create a translation to both and assert we can only edit the one of the
    // menu link content.
    $node_translation = $node->addTranslation('fr', ['title' => 'My node FR']);
    $node_translation->save();
    $menu_link_content_translation = $menu_link_content->addTranslation('fr', ['title' => 'Link test FR']);
    $menu_link_content_translation->save();

    $this->drupalGet($node_translation->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($menu_link_content_translation->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Link test FR [French translation]');
  }

  /**
   * Tests that we can only access the translation overview in English.
   */
  public function testTranslationOverviewRedirect(): void {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'My node',
    ]);

    $node->save();

    $url = $node->toUrl('drupal:content-translation-overview');
    $this->drupalGet($url);
    $this->assertSession()->addressEquals('/en/node/1/translations');

    // If we attempt this URL in French, it should redirect to EN.
    $url = $node->toUrl('drupal:content-translation-overview');
    $language = ConfigurableLanguage::load('fr');
    $url->setOption('language', $language);
    $this->drupalGet($url);
    $this->assertSession()->addressEquals('/en/node/1/translations');

    $menu_link_content = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'http://example.com'],
      'title' => 'Link test',
    ]);
    $menu_link_content->save();

    // For entity types that do not use our system, there should be no redirect.
    $url = $menu_link_content->toUrl('drupal:content-translation-overview');
    $url->setOption('language', $language);
    $this->drupalGet($url);
    $this->assertSession()->addressEquals('/fr/admin/structure/menu/item/1/edit/translations');
  }

}
