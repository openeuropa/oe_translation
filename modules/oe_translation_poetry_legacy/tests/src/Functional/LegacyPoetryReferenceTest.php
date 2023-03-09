<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry_legacy\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;

/**
 * Tests the Legacy Poetry reference entity.
 */
class LegacyPoetryReferenceTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry_legacy',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests the view of the entity type.
   */
  public function testLegacyPoetryReferenceView(): void {
    // Create two nodes to be referenced.
    $node1 = Node::create([
      'type' => 'page',
      'title' => 'Test node 1',
    ]);
    $node1->save();
    $node2 = Node::create([
      'type' => 'page',
      'title' => 'Test node 2',
    ]);
    $node2->save();
    // Create 2 Legacy Poetry reference entities.
    $poetry_legacy_reference_storage = $this->container->get('entity_type.manager')->getStorage('poetry_legacy_reference');
    /** @var \Drupal\oe_translation_poetry_legacy\Entity\LegacyPoetryReference $legacy_poetry_reference */
    $poetry_legacy_reference_storage->create([
      'node' => ['target_id' => $node1->id()],
      'poetry_request_id' => 'WEB/2022/1000/0/0/TRA',
    ])->save();
    $poetry_legacy_reference_storage->create([
      'node' => ['target_id' => $node2->id()],
      'poetry_request_id' => 'WEB/2022/2000/0/0/TRA',
    ])->save();

    $this->drupalLogout();
    $this->drupalGet('admin/content/legacy-poetry-references');
    // Anonymous users do not have access to the view.
    $this->assertSession()->pageTextContains('Access denied');
    $user = $this->createUser();
    $this->drupalLogin($user);
    // Authenticated users without the required permission do not have access
    // to the view either.
    $this->drupalGet('admin/content/legacy-poetry-references');
    $this->assertSession()->pageTextContains('Access denied');
    // Create a user with access to translate any entity.
    $user = $this->createUser(['translate any entity']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/content/legacy-poetry-references');
    // Assert the filter fields.
    $this->assertSession()->fieldExists('Node');
    $this->assertSession()->fieldExists('Poetry request ID');
    $this->assertSession()->buttonExists('Filter');
    $this->assertSession()->buttonNotExists('Reset');
    // Assert the values of the created entities.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Test the filters.
    $this->getSession()->getPage()->fillField('Node', 'Test');
    $this->getSession()->getPage()->pressButton('Filter');
    // Both entities should still be displayed.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Filter using the full title of the node.
    $this->getSession()->getPage()->fillField('Node', 'Test node 1');
    $this->getSession()->getPage()->pressButton('Filter');
    // Only the first entity should be displayed.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextNotContains('Test node 2');
    $this->assertSession()->pageTextNotContains('WEB/2022/2000/0/0/TRA');
    // Reset the filters.
    $this->getSession()->getPage()->pressButton('Reset');
    // Both entities should be displayed again.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Filter by Poetry ID.
    $this->getSession()->getPage()->fillField('Poetry request ID', 'WEB/2022/2000/0/0/TRA');
    $this->getSession()->getPage()->pressButton('Filter');
    // Only the second entity should be displayed.
    $this->assertSession()->pageTextNotContains('Test node 1');
    $this->assertSession()->pageTextNotContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Filter by Poetry ID using a common string between the two values.
    $this->getSession()->getPage()->fillField('Poetry request ID', 'web');
    $this->getSession()->getPage()->pressButton('Filter');
    // Both entities should be displayed again.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
  }

}
