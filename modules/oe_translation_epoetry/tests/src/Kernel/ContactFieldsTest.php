<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_epoetry\Kernel;

use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Poetry contact fields type and formatter.
 */
class ContactFieldsTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry_html_formatter',
    'oe_translation_epoetry',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    $this->container->get('entity_type.manager')->getStorage('field_storage_config')->create([
      'field_name' => 'field_epoetry_contact',
      'entity_type' => 'node',
      'type' => 'oe_transalation_epoetry_contact',
    ])->save();

    $this->container->get('entity_type.manager')->getStorage('field_config')->create([
      'field_name' => 'field_epoetry_contact',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the contact field and its formatter.
   */
  public function testContactField(): void {
    $cases = [
      'no-values' => [
        'field_values' => [
          'contact_type' => '',
          'contact' => '',
        ],
      ],
      'only-contact_type' => [
        'field_values' => [
          'contact_type' => '',
          'contact' => 'Contact information.',
        ],
        'expected' => '<br />Contact information.',
      ],
      'only-contact' => [
        'field_values' => [
          'contact_type' => 3,
          'contact' => '',
        ],
        'expected' => 'Webmaster<br />',
      ],
      'both-values' => [
        'field_values' => [
          'contact_type' => 2,
          'contact' => 'Contact information of the Editor.',
        ],
        'expected' => 'Editor<br />Contact information of the Editor.',
      ],
    ];

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page with contact field type',
    ]);
    $node->save();

    foreach ($cases as $case => $values) {
      $node->set('field_epoetry_contact', $values['field_values']);
      $node->save();
      $node_storage->resetCache();
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($node->id());
      if ($case === 'no-values') {
        $this->assertTrue($node->get('field_epoetry_contact')->isEmpty());
        continue;
      }
      $this->assertEquals($values['field_values'], $node->get('field_epoetry_contact')->first()->getValue());
      $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
      $build = $builder->viewField($node->get('field_epoetry_contact'));
      $output = $this->container->get('renderer')->renderRoot($build);
      $this->assertStringContainsString($values['expected'], (string) $output);
    }
  }

}
