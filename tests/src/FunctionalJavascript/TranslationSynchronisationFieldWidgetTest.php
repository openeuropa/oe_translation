<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;

/**
 * Tests the Translation Synchronisation field widget.
 */
class TranslationSynchronisationFieldWidgetTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'user',
    'field',
    'text',
    'options',
    'oe_translation',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

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

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->entityTypeManager->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();

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

    ConfigurableLanguage::create(['id' => 'fr'])->save();
    ConfigurableLanguage::create(['id' => 'it'])->save();

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
    $this->drupalGet('/node/add/page');

    $assert = $this->assertSession();
    $assert->fieldExists('title[0][value]')->setValue('My page');

    // Assert the select is correct.
    $select = $assert->selectExists('translation_sync[0][type]');
    $assert->optionExists('Select type', 'None');
    $assert->optionExists('Select type', 'Manual');
    $assert->optionExists('Select type', 'Automatic with minimum threshold');
    $this->assertEquals('None', $select->find('xpath', "//option[@selected='selected']")->getText());

    // Assert we don't see a configuration fieldset.
    $this->assertFalse($this->getSession()->getPage()->find('css', '#edit-translation-sync-0-configuration')->isVisible());

    // Save the node with no value.
    $this->getSession()->getPage()->pressButton('Save');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $this->assertTrue($node->get('translation_sync')->isEmpty());
    $this->assertEmpty($node->get('translation_sync')->getValue());

    // Edit the node again and configure it to be Manual.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->selectFieldOption('Select type', 'manual');
    $this->getSession()->wait(10);
    $this->assertFalse($this->getSession()->getPage()->find('css', '#edit-translation-sync-0-configuration')->isVisible());
    $this->getSession()->getPage()->pressButton('Save');
    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $expected_values = [
      'type' => 'manual',
      'configuration' => [],
    ];
    $this->assertEquals($expected_values, $node->get('translation_sync')->first()->getValue());

    // Edit the node again and configure it to be Automatic without a date.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->selectFieldOption('Select type', 'automatic');
    $assert->waitForField('Languages');
    $this->assertTrue($this->getSession()->getPage()->find('css', '#edit-translation-sync-0-configuration')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->find('css', '#edit-translation-sync-0-configuration-languages')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->find('css', '#edit-translation-sync-0-configuration-date')->isVisible());
    $this->getSession()->getPage()->selectFieldOption('Languages', 'en', TRUE);
    $this->getSession()->getPage()->selectFieldOption('Languages', 'fr', TRUE);
    $this->getSession()->getPage()->pressButton('Save');
    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);
    $expected_values = [
      'type' => 'automatic',
      'configuration' => [
        'languages' => ['en', 'fr'],
        'date' => NULL,
      ],
    ];
    $this->assertEquals($expected_values, $node->get('translation_sync')->first()->getValue());

    // Edit the node again and configure it to be Automatic with a date.
    $this->drupalGet($node->toUrl('edit-form'));
    $date = new DrupalDateTime('now');
    $this->getSession()->getPage()->fillField('edit-translation-sync-0-configuration-date-date', $date->format('m/d/Y'));
    $this->getSession()->getPage()->fillField('edit-translation-sync-0-configuration-date-time', $date->format('h:i:sa'));
    $this->getSession()->getPage()->pressButton('Save');

    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);

    $expected_values = [
      'type' => 'automatic',
      'configuration' => [
        'languages' => ['en', 'fr'],
        'date' => $date->getTimestamp(),
      ],
    ];
    $this->assertEquals($expected_values, $node->get('translation_sync')->first()->getValue());
  }

}
