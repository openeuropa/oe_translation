<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the Translation Synchronisation field widget.
 */
class TranslationSynchronisationFieldWidget extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['oe_translation'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'translation_sync',
      'entity_type' => 'node',
      'type' => 'oe_translation_translation_sync',
    ])->save();

    $this->field = FieldConfig::create([
      'field_name' => 'translation_sync',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    $entity_form_display = EntityFormDisplay::collectRenderDisplay(Node::create(['type' => 'page']), 'default');
    $entity_form_display->setComponent('translation_sync', [
      'weight' => 1,
      'region' => 'content',
      'type' => 'oe_translation_translation_sync_widget',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $entity_form_display->save();

    $user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($user);
  }

  /**
   * Tests the Translation Synchronisation field widget.
   */
  public function testTranslationSynchronisationFieldWidget(): void {
    $date = new \DateTime('2020-05-08');

    $this->drupalGet('/node/add/page');
    $session = $this->getSession();
    $page = $session->getPage();
    $page->selectFieldOption('translation_sync[0][type]', 'automatic');
    $page->selectFieldOption('translation_sync[0][configuration][language]', 'en');
    $page->selectFieldOption('translation_sync[0][configuration][date]', $date);
    $this->drupalPostForm('/node/add/page', [], 'Save');
    $this->assertSession()->pageTextContains('Page My page has been created');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $expected_values = [
      'type' => 'automatic',
      'configuration' => [
        'language' => 'en',
        'date' => $date->getTimestamp(),
      ],
    ];
    $this->assertEquals($expected_values, $node->get('translation_sync')->first()->getValue());
  }

}
