<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;

/**
 * Tests the Poetry Request ID field widget.
 */
class PoetryRequestIdFieldWidgetTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'poetry_request_id',
      'entity_type' => 'node',
      'type' => 'poetry_request_id',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'poetry_request_id',
      'bundle' => 'page',
    ])->save();

    $entity_form_display = EntityFormDisplay::collectRenderDisplay(Node::create(['type' => 'page']), 'default');
    $entity_form_display->setComponent('poetry_request_id', [
      'weight' => 1,
      'region' => 'content',
      'type' => 'poetry_request_id_widget',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $entity_form_display->save();
  }

  /**
   * Tests the Poetry Request ID field widget.
   */
  public function testPoetryRequestIdFieldWidget(): void {
    $user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($user);

    $this->drupalGet('/node/add/page');

    $values = [
      'title[0][value]' => 'My page',
      'poetry_request_id[0][poetry_request_id][code]' => 'WEB',
      'poetry_request_id[0][poetry_request_id][year]' => '2019',
      'poetry_request_id[0][poetry_request_id][number]' => '102',
      'poetry_request_id[0][poetry_request_id][version]' => '1',
      'poetry_request_id[0][poetry_request_id][part]' => '1',
      'poetry_request_id[0][poetry_request_id][product]' => 'TRA',
    ];

    $this->drupalPostForm('/node/add/page', $values, 'Save');
    $this->assertSession()->pageTextContains('Page My page has been created');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $this->assertEquals($node->get('poetry_request_id')->first()->getValue(), [
      'code' => 'WEB',
      'year' => '2019',
      'number' => 102,
      'version' => 1,
      'part' => 1,
      'product' => 'TRA',
    ]);
  }

}
