<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Tests the Translation Synchronisation field widget.
 */
class TranslationSynchronisationFieldWidgetTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime'];

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

    FieldConfig::create([
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
    $date_time = new DrupalDateTime('2020-05-08');
    // Get date and time formats.
    $date_format = DateFormat::load('html_date')->getPattern();
    $time_format = DateFormat::load('html_time')->getPattern();

    $this->drupalGet('/node/add/page');
    $assert = $this->assertSession();
    $assert->fieldExists('title[0][value]')->setValue('My page');

    // Verify that fieldset is displayed.
    $assert->assertVisibleInViewport('css', '#edit-translation-sync-0');

    // Verify that configuration items are not visible for manual type.
    $assert->selectExists('translation_sync[0][type]')->selectOption('manual');
    $assert->elementTextNotContains('css', '#edit-translation-sync-0', 'Language');
    $assert->elementTextNotContains('css', '#edit-translation-sync-0', 'Date');

    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Page My page has been created.');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $expected_values = [
      'type' => 'manual',
      'configuration' => [],
    ];
    $this->assertEquals($expected_values, $node->get('translation_sync')->first()->getValue());

    // Edit the node to use the automatic type.
    $this->drupalGet('/node/1/edit');
    $assert->selectExists('translation_sync[0][type]')->selectOption('automatic');
    // Verify that configuration items are visible.
    $assert->waitForField('translation_sync[0][configuration][languages][]');
    $assert->waitForField('translation_sync[0][configuration][date][date]');
    $assert->waitForField('translation_sync[0][configuration][date][time]');
    $assert->elementTextContains('css', '#edit-translation-sync-0', 'Language');
    $assert->elementTextContains('css', '#edit-translation-sync-0', 'Date');

    $assert->selectExists('translation_sync[0][configuration][languages][]')->selectOption('en');
    $assert->fieldExists('translation_sync[0][configuration][date][date]')->setValue($date_time->format($date_format));
    $assert->fieldExists('translation_sync[0][configuration][date][time]')->setValue($date_time->format($time_format));
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Page My page has been updated.');

    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $expected_values = [
      'type' => 'automatic',
      'configuration' => [
        'language' => 'en',
        'date' => $date_time->getTimestamp(),
      ],
    ];
    $this->assertEquals($expected_values, $node->get('translation_sync')->first()->getValue());
  }

}
