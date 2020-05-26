<?php

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the contact field widgets.
 */
class ContactFieldsWidgetTest extends BrowserTestBase {

  /**
   * The Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'oe_translation_poetry',
    'node',
    'user',
    'field',
    'text',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    foreach ($this->getTestCases() as $case) {
      FieldStorageConfig::create([
        'field_name' => $case['field'],
        'entity_type' => 'node',
        'type' => $case['field_type'],
      ])->save();

      NodeType::create([
        'name' => $case['bundle'],
        'type' => $case['bundle'],
      ])->save();

      FieldConfig::create([
        'entity_type' => 'node',
        'field_name' => $case['field'],
        'bundle' => $case['bundle'],
      ])->save();

      $entity_form_display = EntityFormDisplay::collectRenderDisplay($this->nodeStorage->create(['type' => $case['bundle']]), 'default');
      $entity_form_display->setComponent($case['field'], [
        'weight' => 1,
        'region' => 'content',
        'type' => $case['widget'],
        'settings' => [],
        'third_party_settings' => [],
      ]);
      $entity_form_display->save();
    }

    $user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($user);
  }

  /**
   * Prepare the test cases for the two field types.
   *
   * @return array
   *   The test cases.
   */
  protected function getTestCases(): array {
    return [
      'organisational' => [
        'bundle' => 'organisational',
        'field' => 'organisational_contact_info',
        'widget' => 'oe_translation_poetry_organisation_contact_widget',
        'field_type' => 'oe_translation_poetry_organisation_contact',
        'types' => [
          'responsible' => [
            'label' => 'Responsible',
            'value' => 'organisation responsible',
          ],
          'author' => [
            'label' => 'Author',
            'value' => 'organisation author',
          ],
          'requester' => [
            'label' => 'Requester',
            'value' => 'organisation requester',
          ],
        ],
      ],
      'personal' => [
        'bundle' => 'personal',
        'field' => 'personal_contact_info',
        'widget' => 'oe_translation_poetry_personal_contact_widget',
        'field_type' => 'oe_translation_poetry_personal_contact',
        'types' => [
          'responsible' => [
            'label' => 'Responsible',
            'value' => 'personal responsible',
          ],
          'secretary' => [
            'label' => 'Secretary',
            'value' => 'personal secretary',
          ],
          'contact' => [
            'label' => 'Contact',
            'value' => 'personal contact',
          ],
          'author' => [
            'label' => 'Author',
            'value' => 'personal author',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests the field widgets.
   */
  public function testPoetryContactFields(): void {
    foreach ($this->getTestCases() as $case) {
      $this->drupalGet('/node/add/' . $case['bundle']);

      $values = [
        'title[0][value]' => 'My test ' . $case['bundle'],
      ];

      $this->drupalPostForm('/node/add/' . $case['bundle'], $values, 'Save');

      $this->nodeStorage->resetCache();
      /** @var \Drupal\node\NodeInterface $node */
      $nodes = $this->nodeStorage->loadByProperties(['title' => 'My test ' . $case['bundle']]);
      $node = reset($nodes);

      $this->assertTrue($node->get($case['field'])->isEmpty());
      $this->drupalGet($node->toUrl('edit-form'));

      $expected_values = [];
      foreach ($case['types'] as $key => $type_info) {
        $expected_values[$key] = '';
      }

      $this->assertContactValues($expected_values, $case, $node);
    }
  }

  /**
   * Fills in the contact field values recursively and asserts their values.
   *
   * @param array $expected_values
   *   The expected values.
   * @param array $case
   *   The test case.
   * @param \Drupal\node\NodeInterface $node
   *   The node being tested.
   */
  protected function assertContactValues(array &$expected_values, array $case, NodeInterface $node): void {
    foreach ($expected_values as $key => $expected_value) {
      if ($expected_value !== '') {
        continue;
      }

      $type_case = $case['types'][$key];

      $this->drupalGet($node->toUrl('edit-form'));
      $this->getSession()->getPage()->fillField($type_case['label'], $type_case['value']);
      $this->getSession()->getPage()->pressButton('Save');
      $expected_values[$key] = $type_case['value'];

      $this->nodeStorage->resetCache();
      $node = $this->nodeStorage->load($node->id());

      $this->assertEquals($expected_values, $node->get($case['field'])->first()->getValue());
      $this->assertContactValues($expected_values, $case, $node);
    }
  }

}
