<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that we are allowing Translator plugins to make alterations.
 */
class ExtensibilityTest extends TranslationTestBase {

  /**
   * Tests that the plugins can alter pages and forms, and propose routes.
   */
  public function testCustomPluginAlterations(): void {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'My node',
    ]);
    $node->save();

    // Assert that we are overriding the overview page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->responseContains('Content overview altered');
    // Assert that our custom route was provided.
    $this->drupalGet(Url::fromRoute('oe_translation_test.test_route', ['node' => $node->id()]));
    $this->assertSession()->responseContains('Route works');
    // Assert that our custom access control works.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity_type' => 'node',
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'bg',
    ]));
    $local_task_items = $this->entityTypeManager->getStorage('tmgmt_local_task_item')->loadMultiple();
    $local_task_item = reset($local_task_items);
    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $local_task_item->access('test', NULL, TRUE);
    $this->assertEquals('Access control works', $access->getReason());
    // Assert that our breadcrumb would show up on the local task page.
    $request_stack = new RequestStack();
    $request = new Request();
    $request_stack->push($request);
    $current_route_match = new CurrentRouteMatch($request_stack);
    $route = $this->container->get('router.route_provider')->getRouteByName('entity.tmgmt_local_task_item.canonical');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'entity.tmgmt_local_task_item.canonical');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tmgmt_local_task_item', $local_task_item);
    $breadcrumb = $this->container->get('breadcrumb')->build($current_route_match);
    $links = $breadcrumb->getLinks();
    /** @var \Drupal\Core\Link $link */
    $link = end($links);
    $this->assertEquals($node->label(), $link->getText());
  }

  /**
   * Tests the access to the Drupal core translation page.
   */
  public function testTranslationAccess(): void {
    \Drupal::service('module_installer')->install(['menu_link_content']);
    \Drupal::service('content_translation.manager')->setEnabled('menu_link_content', 'menu_link_content', TRUE);
    \Drupal::service('router.builder')->rebuild();

    // Check that the route for creating a menu link translation is accessible.
    $menu_link_content = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'http://example.com'],
      'title' => 'Link test',
    ]);
    $menu_link_content->save();

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
    $this->assertResponse(403);
  }

}
