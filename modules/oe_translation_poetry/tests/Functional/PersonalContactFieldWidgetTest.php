<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;

/**
 * Tests the Personal Contact field widget.
 */
class PersonalContactFieldWidgetTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'personal_contact_info',
      'entity_type' => 'node',
      'type' => 'oe_translation_poetry_personal_contact',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'personal_contact_info',
      'bundle' => 'page',
    ])->save();

    $entity_form_display = EntityFormDisplay::collectRenderDisplay(Node::create(['type' => 'page']), 'default');
    $entity_form_display->setComponent('personal_contact_info', [
      'weight' => 1,
      'region' => 'content',
      'type' => 'oe_translation_poetry_personal_contact_widget',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $entity_form_display->save();

    $user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($user);
  }

  /**
   * Tests the field widget.
   */
  public function testPersonalContactFieldWidget(): void {
    $this->drupalGet('/node/add/page');

    $values = [
      'title[0][value]' => 'My page',
      'personal_contact_info[0][author]' => '',
      'personal_contact_info[0][secretary]' => '',
      'personal_contact_info[0][contact]' => '',
      'personal_contact_info[0][responsible]' => '',
    ];

    $this->drupalPostForm('/node/add/page', $values, 'Save');
    $this->assertSession()->pageTextContains('Page My page has been created');

    $this->entityTypeManager->getStorage('node')->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1);

    $this->assertTrue($node->get('personal_contact_info')->isEmpty());

    // Edit the node and fill in author field.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->fillField('edit-personal-contact-info-0-author', 'Author');
    $this->getSession()->getPage()->pressButton('Save');

    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);

    $expected_values = [
      'author' => 'Author',
      'secretary' => '',
      'contact' => '',
      'responsible' => '',
    ];

    $this->assertEquals($expected_values, $node->get('personal_contact_info')->first()->getValue());

    // Edit the node again and fill in secretary field.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->fillField('edit-personal-contact-info-0-secretary', 'Secretary');
    $this->getSession()->getPage()->pressButton('Save');

    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);

    $expected_values = [
      'author' => 'Author',
      'secretary' => 'Secretary',
      'contact' => '',
      'responsible' => '',
    ];

    $this->assertEquals($expected_values, $node->get('personal_contact_info')->first()->getValue());

    // Edit the node again and fill in contact field.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->fillField('edit-personal-contact-info-0-contact', 'Contact');
    $this->getSession()->getPage()->pressButton('Save');

    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);

    $expected_values = [
      'author' => 'Author',
      'secretary' => 'Secretary',
      'contact' => 'Contact',
      'responsible' => '',
    ];

    $this->assertEquals($expected_values, $node->get('personal_contact_info')->first()->getValue());

    // Edit the node again and fill in responsible field.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->getSession()->getPage()->fillField('edit-personal-contact-info-0-responsible', 'Responsible');
    $this->getSession()->getPage()->pressButton('Save');

    $this->entityTypeManager->getStorage('node')->resetCache();
    $node = $this->entityTypeManager->getStorage('node')->load(1);

    $expected_values = [
      'author' => 'Author',
      'secretary' => 'Secretary',
      'contact' => 'Contact',
      'responsible' => 'Responsible',
    ];

    $this->assertEquals($expected_values, $node->get('personal_contact_info')->first()->getValue());
  }

}
