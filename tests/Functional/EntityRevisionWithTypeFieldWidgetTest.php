<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Tests the Entity revision with type field widget.
 */
class EntityRevisionWithTypeFieldWidgetTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'entity_revision_type_item',
      'entity_type' => 'node',
      'type' => 'oe_translation_entity_revision_type_item',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'entity_revision_type_item',
      'bundle' => 'page',
    ])->save();

    $entity_form_display = EntityFormDisplay::collectRenderDisplay(Node::create(['type' => 'page']), 'default');
    $entity_form_display->setComponent('entity_revision_type_item', [
      'weight' => 1,
      'region' => 'content',
      'type' => 'oe_translation_entity_revision_type_widget',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $entity_form_display->save();
  }

  /**
   * Tests the Entity revision with type field widget.
   */
  public function testEntityRevisionWithTypeFieldWidget(): void {
    $user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($user);

    // Create a node to reference.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->create([
      'type' => 'page',
      'title' => 'Referenced node page',
    ]);

    $this->drupalGet('/node/add/page');

    $values = [
      'title[0][value]' => 'My page',
      'entity_revision_type_item[0][entity_id]' => '1',
      'entity_revision_type_item[0][entity_revision_id]' => '1',
      'entity_revision_type_item[0][entity_type]' => 'node',
    ];

    $this->drupalPostForm('/node/add/page', $values, 'Save');
    $this->assertSession()->pageTextContains('Page My page has been created');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $this->assertEquals($node->get('entity_revision_type_item')->first()->getValue(), [
      'entity_id' => '1',
      'entity_revision_id' => '1',
      'entity_type' => 'node',
    ]);
  }

}
