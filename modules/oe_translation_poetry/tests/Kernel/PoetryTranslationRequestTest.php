<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Kernel;

use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests Poetry Translation Request bundles.
 */
class PoetryTranslationRequestTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry_html_formatter',
    'oe_translation_poetry',
    'oe_translation',
    'system',
    'tmgmt',
    'tmgmt_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['oe_translation_poetry']);
  }

  /**
   * Tests the Poetry Translation Request bundle stores its values properly.
   */
  public function testPoetryTranslationRequest() {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $translation_request_storage */
    $translation_request_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request');
    // Create an empty translation request to be referenced.
    $referenced_request = $translation_request_storage->create(['bundle' => 'poetry_translation_request']);
    $referenced_request->save();
    // Create a translation request of Poetry type and assert all values are
    // properly stored.
    $poetry_id = [
      'code' => 'WEB',
      'year' => '2019',
      'number' => 122,
      'version' => 1,
      'part' => 1,
      'product' => 'TRA',
    ];
    $personal_contact_info = [
      'author' => 'Author',
      'secretary' => 'Secretary',
      'contact' => 'Contact',
      'responsible' => 'Responsible',
    ];
    $organisation_info = [
      'responsible' => 'Responsible',
      'author' => 'Author',
      'requester' => 'Requester',
    ];
    $poetry_translation_request = $translation_request_storage->create([
      'bundle' => 'poetry_translation_request',
      'oe_poetry_request_id' => $poetry_id,
      'oe_contact_information' => $personal_contact_info,
      'oe_organisation_information' => $organisation_info,
      'oe_update_of' => $referenced_request->id(),
      'oe_translation_deadline' => '10-12-2020',
    ]);
    $poetry_translation_request->save();
    $translation_request_storage->resetCache();
    $poetry_translation_request = $translation_request_storage->load($poetry_translation_request->id());
    $this->assertEqual($poetry_translation_request->bundle(), 'poetry_translation_request');
    $this->assertEqual($poetry_translation_request->get('oe_poetry_request_id')->first()->getValue(), $poetry_id);
    $this->assertEqual($poetry_translation_request->get('oe_contact_information')->first()->getValue(), $personal_contact_info);
    $this->assertEqual($poetry_translation_request->get('oe_organisation_information')->first()->getValue(), $organisation_info);
    $this->assertEqual($poetry_translation_request->get('oe_update_of')->first()->getValue()['target_id'], $referenced_request->id());
    $this->assertEqual($poetry_translation_request->get('oe_translation_deadline')->first()->getValue()['value'], '10-12-2020');
  }

}
