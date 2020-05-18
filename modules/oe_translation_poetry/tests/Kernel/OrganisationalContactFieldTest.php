<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Translation Synchronisation field type and formatter.
 */
class OrganisationalContactFieldTest extends TranslationKernelTestBase {

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
      'field_name' => 'organisational_contact_info',
      'entity_type' => 'node',
      'type' => 'oe_translation_poetry_organisation_contact',
    ])->save();

    FieldConfig::create([
      'field_name' => 'organisational_contact_info',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the field type and formatter.
   */
  public function testOrganisationalContactItemField(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $tests = [
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
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node->save();

    foreach ($tests as $case => $values) {
      $node->set('organisational_contact_info', $values);
      $node->save();
      $node_storage->resetCache();
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($node->id());
      if ($case === 'no-values') {
        $this->assertTrue($node->get('organisational_contact_info')->isEmpty());
        continue;
      }

      $this->assertEquals($values, $node->get('organisational_contact_info')->first()->getValue());
      $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
      $build = $builder->viewField($node->get('organisational_contact_info'));
      $output = $this->container->get('renderer')->renderRoot($build);
      $this->assertContains(implode('<br />', $values), (string) $output);
    }
  }

}
