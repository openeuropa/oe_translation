<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Personal Contact field type and formatter.
 */
class PersonalContactFieldTest extends TranslationKernelTestBase {

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
  protected function setUp() {
    parent::setUp();

    $this->installSchema('node', ['node_access']);

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'personal_contact_info',
      'entity_type' => 'node',
      'type' => 'oe_translation_poetry_personal_contact',
    ])->save();

    FieldConfig::create([
      'field_name' => 'personal_contact_info',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the field type and formatter.
   */
  public function testPersonalContactItemField(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $tests = [
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
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node->save();

    foreach ($tests as $case => $values) {
      $node->set('personal_contact_info', $values);
      $node->save();
      $node_storage->resetCache();
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($node->id());
      if ($case === 'no-values') {
        $this->assertTrue($node->get('personal_contact_info')->isEmpty());
        continue;
      }

      $this->assertEquals($values, $node->get('personal_contact_info')->first()->getValue());
      $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
      $build = $builder->viewField($node->get('personal_contact_info'));
      $output = $this->container->get('renderer')->renderRoot($build);
      $this->assertContains(implode('<br />', $values), (string) $output);
    }
  }

}
