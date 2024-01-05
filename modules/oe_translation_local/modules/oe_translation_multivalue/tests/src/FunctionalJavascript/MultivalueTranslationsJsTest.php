<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_multivalue\FunctionalJavascript;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;

/**
 * Tests the multivalue translations.
 *
 * @group batch1
 */
class MultivalueTranslationsJsTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'paragraphs',
    'entity_reference_revisions',
    'menu_link_content',
    'views',
    'oe_translation_multivalue',
    'oe_translation_multivalue_test',
    'typed_link',
    'oe_content_timeline_field',
    'description_list_field',
    'link_description',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Tests the translation of multivalue fields using the form.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testMultivalueFieldTranslationsForm(): void {
    // Enable the multivalue translation on our fields. We only cover 2 fields,
    // the rest are covered with the API in the non-JS test.
    $field_names = [
      'field_textfield',
      'field_address',
    ];

    foreach ($field_names as $field_name) {
      $storage = FieldStorageConfig::load('node.' . $field_name);
      $storage->setSetting('translation_multivalue', TRUE);
      $storage->save();
    }
    $storage = FieldStorageConfig::load('paragraph.field_textfield_paragraph');
    $storage->setSetting('translation_multivalue', TRUE);
    $storage->save();

    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    $this->drupalGet('/node/add/multivalue');
    $this->getSession()->getPage()->fillField('Title', 'Test title');
    // Textfield.
    $this->getSession()->getPage()->fillField('field_textfield[0][value]', 'Value 1');
    $this->getSession()->getPage()->pressButton('field_textfield_add_more');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('field_textfield[1][value]', 'Value 2');
    // Address.
    $this->getSession()->getPage()->selectFieldOption('field_address[0][address][country_code]', 'BE');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('field_address[0][address][given_name]', 'First name 1');
    $this->getSession()->getPage()->fillField('field_address[0][address][family_name]', 'Last name 1');
    $this->getSession()->getPage()->fillField('field_address[0][address][address_line1]', 'Street 1');
    $this->getSession()->getPage()->fillField('field_address[0][address][postal_code]', '1000');
    $this->getSession()->getPage()->fillField('field_address[0][address][locality]', 'Brussels');
    $this->getSession()->getPage()->pressButton('field_address_add_more');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('field_address[1][address][country_code]', 'BE');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('field_address[1][address][given_name]', 'First name 2');
    $this->getSession()->getPage()->fillField('field_address[1][address][family_name]', 'Last name 2');
    $this->getSession()->getPage()->fillField('field_address[1][address][address_line1]', 'Street 2');
    $this->getSession()->getPage()->fillField('field_address[1][address][postal_code]', '1000');
    $this->getSession()->getPage()->fillField('field_address[1][address][locality]', 'Brussels');
    $this->getSession()->executeScript('window.scrollTo(0,0);');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Multivalue Test title has been created.');

    // Translate the node.
    $node = $this->drupalGetNodeByTitle('Test title');
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();

    // Translate the values.
    $tables = $this->getSession()->getPage()->findAll('css', 'table.responsive-enabled');
    foreach ($tables as $table) {
      $textarea = $table->find('xpath', '//td[2]//textarea');
      $value = $textarea->getValue();
      if (in_array($value, ['BE', 'Brussels', '1000'])) {
        continue;
      }
      $textarea->setValue($value . '/FR');
    }
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $node = $this->drupalGetNodeByTitle('Test title', TRUE);
    $this->assertTrue($node->hasTranslation('fr'));

    // Reorder the values.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->find('css', '.field--name-field-textfield')->pressButton('Show row weights');
    $this->assertSession()->waitForElementVisible('css', '[name="field_textfield[0][_weight]"]');
    $this->getSession()->getPage()->selectFieldOption('field_textfield[0][_weight]', '1');
    $this->getSession()->getPage()->selectFieldOption('field_textfield[1][_weight]', '0');
    $this->getSession()->getPage()->selectFieldOption('field_address[0][_weight]', '1');
    $this->getSession()->getPage()->selectFieldOption('field_address[1][_weight]', '0');
    $this->getSession()->executeScript('window.scrollTo(0,0);');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Multivalue Test title has been updated.');
    // Assert the order change took effect.
    $node = $this->drupalGetNodeByTitle('Test title', TRUE);
    $this->assertEquals('Value 2', $node->get('field_textfield')->getValue()[0]['value']);
    $this->assertEquals('Value 1', $node->get('field_textfield')->getValue()[1]['value']);
    $this->assertEquals('Street 2', $node->get('field_address')->getValue()[0]['address_line1']);
    $this->assertEquals('Street 1', $node->get('field_address')->getValue()[1]['address_line1']);
    // The translation value are the other way.
    $translation = $node->getTranslation('fr');
    $this->assertEquals('Value 1/FR', $translation->get('field_textfield')->getValue()[0]['value']);
    $this->assertEquals('Value 2/FR', $translation->get('field_textfield')->getValue()[1]['value']);
    $this->assertEquals('Street 1/FR', $translation->get('field_address')->getValue()[0]['address_line1']);
    $this->assertEquals('Street 2/FR', $translation->get('field_address')->getValue()[1]['address_line1']);

    // Translate again the node and assert the pre-filled translation values
    // match the source values.
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();

    $value_test_keys = [
      'field_textfield' => 'value',
      'field_address' => 'address_line1',
    ];
    foreach ($field_names as $field_name) {
      $key = $value_test_keys[$field_name];
      foreach ([0, 1] as $delta) {
        $source = $this->getSession()->getPage()->find('xpath', sprintf('//table//td//textarea[@name="%s|%s|%s[source]"]', $field_name, $delta, $key))->getValue();
        $translation = $this->getSession()->getPage()->find('xpath', sprintf('//table//td//textarea[@name="%s|%s|%s[translation]"]', $field_name, $delta, $key))->getValue();
        $this->assertEquals($source . '/FR', $translation);
      }
    }
    // Save the translation.
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $node = $this->drupalGetNodeByTitle('Test title', TRUE);
    $this->assertEquals('Value 2', $node->get('field_textfield')->getValue()[0]['value']);
    $this->assertEquals('Value 1', $node->get('field_textfield')->getValue()[1]['value']);
    $this->assertEquals('Street 2', $node->get('field_address')->getValue()[0]['address_line1']);
    $this->assertEquals('Street 1', $node->get('field_address')->getValue()[1]['address_line1']);
    // The translation value are now in the same order as the source.
    $translation = $node->getTranslation('fr');
    $this->assertEquals('Value 2/FR', $translation->get('field_textfield')->getValue()[0]['value']);
    $this->assertEquals('Value 1/FR', $translation->get('field_textfield')->getValue()[1]['value']);
    $this->assertEquals('Street 2/FR', $translation->get('field_address')->getValue()[0]['address_line1']);
    $this->assertEquals('Street 1/FR', $translation->get('field_address')->getValue()[1]['address_line1']);
  }

}
