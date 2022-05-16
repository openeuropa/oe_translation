<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Kernel;

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
    'oe_translation_poetry',
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
      'field_name' => 'organisational_contact_info',
      'entity_type' => 'node',
      'type' => 'oe_translation_poetry_organisation_contact',
    ])->save();

    $this->container->get('entity_type.manager')->getStorage('field_config')->create([
      'field_name' => 'organisational_contact_info',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    $this->container->get('entity_type.manager')->getStorage('field_storage_config')->create([
      'field_name' => 'personal_contact_info',
      'entity_type' => 'node',
      'type' => 'oe_translation_poetry_personal_contact',
    ])->save();

    $this->container->get('entity_type.manager')->getStorage('field_config')->create([
      'field_name' => 'personal_contact_info',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the personal and organisational contact fields and formatters.
   */
  public function testContactFields(): void {
    $tests = [
      'organisational_contact_info' => [
        'no-values' => [
          'responsible' => '',
          'author' => '',
          'requester' => '',
        ],
        'only-author' => [
          'responsible' => '',
          'author' => 'Author',
          'requester' => '',
        ],
        'only-requester' => [
          'responsible' => '',
          'author' => '',
          'requester' => 'Requester',
        ],
        'only-responsible' => [
          'responsible' => 'Responsible',
          'author' => '',
          'requester' => '',
        ],
        'all-values' => [
          'responsible' => 'Responsible',
          'author' => 'Author',
          'requester' => 'Requester',
        ],
      ],
      'personal_contact_info' => [
        'no-values' => [
          'author' => '',
          'secretary' => '',
          'contact' => '',
          'responsible' => '',
        ],
        'only-author' => [
          'author' => 'Author',
          'secretary' => '',
          'contact' => '',
          'responsible' => '',
        ],
        'only-secretary' => [
          'author' => '',
          'secretary' => 'Secretary',
          'contact' => '',
          'responsible' => '',
        ],
        'only-contact' => [
          'author' => '',
          'secretary' => '',
          'contact' => 'Contact',
          'responsible' => '',
        ],
        'only-responsible' => [
          'author' => '',
          'secretary' => '',
          'contact' => '',
          'responsible' => 'Responsible',
        ],
        'all-values' => [
          'author' => 'Author',
          'secretary' => 'Secretary',
          'contact' => 'Contact',
          'responsible' => 'Responsible',
        ],
      ],
    ];

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    foreach ($tests as $field => $cases) {
      $node = $node_storage->create([
        'type' => 'page',
        'title' => 'Test page ' . $field,
      ]);
      $node->save();

      foreach ($cases as $case => $values) {
        $node->set($field, $values);
        $node->save();
        $node_storage->resetCache();
        /** @var \Drupal\node\NodeInterface $node */
        $node = $node_storage->load($node->id());
        if ($case === 'no-values') {
          $this->assertTrue($node->get('organisational_contact_info')->isEmpty());
          continue;
        }

        $this->assertEquals($values, $node->get($field)->first()->getValue());
        $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
        $build = $builder->viewField($node->get($field));
        $output = $this->container->get('renderer')->renderRoot($build);
        $this->assertContains(implode('<br />', $values), (string) $output);
      }
    }
  }

}
