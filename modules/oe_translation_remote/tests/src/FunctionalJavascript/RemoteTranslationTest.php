<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_remote\FunctionalJavascript;

use Drupal\node\Entity\Node;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;

/**
 * Tests the remote translations.
 */
class RemoteTranslationTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_remote',
    'oe_translation_remote_test',
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

  /**
   * Test the remote translation provider configuration.
   */
  public function testRemoteTranslationProviderForm(): void {
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/remote-translation-provider');
    $this->assertSession()->pageTextContains('Remote Translator Provider entities');
    $this->assertSession()->linkExists('Add Remote Translator Provider');
    $this->clickLink('Add Remote Translator Provider');

    // Assert form elements.
    $this->assertSession()->pageTextContains('Add remote translator provider');
    $this->assertSession()->fieldExists('Name');
    $this->assertSession()->pageTextContains('Name of the Remote translation provider.');
    $this->assertSession()->elementAttributeContains('css', 'input#edit-label', 'required', 'required');
    $select = $this->assertSession()->selectExists('Plugin');
    // Assert that all the installed plugins are available in the select.
    $options = [];
    foreach ($select->findAll('xpath', '//option') as $element) {
      $options[$element->getValue()] = trim($element->getText());
    }
    $expected_options = [
      'Please select a plugin',
      'Remote one',
      'Remote two',
    ];
    $this->assertEqualsCanonicalizing($expected_options, $options);
    $this->assertSession()->pageTextContains('The plugin to be used with this translator.');
    $this->assertSession()->elementAttributeContains('css', 'select#edit-plugin', 'required', 'required');

    // Assert different configuration form based on selected plugin.
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'Remote one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for Remote one');
    $this->assertSession()->pageTextContains('This plugin does not have any configuration options.');
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'Remote two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for Remote two');
    $this->assertSession()->pageTextNotContains('Plugin configuration for Remote one');
    $this->assertSession()->pageTextNotContains('This plugin does not have any configuration options.');
    $this->assertSession()->fieldExists('A test configuration string');

    // Create a new remote translator provider.
    $this->getSession()->getPage()->fillField('Name', 'Test provider');
    $this->getSession()->getPage()->fillField('A test configuration string', 'Plugin configuration.');
    $this->getSession()->getPage()->pressButton('Save');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'test_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the Test provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $remote_translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('test_provider');
    $this->assertEquals('Test provider', $remote_translator->label());
    $this->assertEquals('remote_two', $remote_translator->getProviderPlugin());
    $this->assertEquals(['configuration_string' => 'Plugin configuration.'], $remote_translator->getProviderConfiguration());

    // Edit the provider.
    $this->clickLink('Edit');
    $this->assertSession()->fieldValueEquals('Name', 'Test provider');
    $this->assertSession()->fieldValueEquals('A test configuration string', 'Plugin configuration.');
    $this->getSession()->getPage()->fillField('Name', 'Test provider edited');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Test provider edited Remote Translator Provider.');

    // Delete the provider.
    $this->getSession()->getPage()->pressButton('List additional actions');
    $this->clickLink('Delete');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('The remote translator provider Test provider edited has been deleted.');
  }

}
