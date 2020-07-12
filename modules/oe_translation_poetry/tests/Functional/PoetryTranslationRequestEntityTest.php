<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Poetry Translation Request forms.
 */
class PoetryTranslationRequestEntityTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'oe_translation',
    'oe_translation_poetry',
  ];

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

    $permissions[] = 'administer translation requests';
    $user = $this->drupalCreateUser($permissions);

    $this->drupalLogin($user);
  }

  /**
   * Tests that the Poetry translation request form works properly.
   */
  public function testCreateTranslationRequest() {
    $this->drupalGet(Url::fromRoute('entity.oe_translation_request.add_form', ['oe_translation_request_type' => 'poetry']));

    // Assert that the deadline field is available and its default value
    // is the current date +14 days.
    $this->assertSession()->fieldExists('Date');
    $deadline_value = $this->getSession()->getPage()->find('css', '#edit-oe-translation-deadline-0-value-date')->getValue();
    $this->assertEqual($deadline_value, date('Y-m-d', strtotime('+14 day')));

    // Assert that contact fields are available.
    $fields = [
      '#edit-oe-contact-information-wrapper' => [
        'Author' => 'Contact Author',
        'Secretary' => 'Contact Secretary',
        'Contact' => 'Contact Contact',
        'Responsible' => 'Contact Responsible',
      ],
      '#edit-oe-organisation-information-wrapper' => [
        'Responsible' => 'Organisation Responsible',
        'Author' => 'Organisation Author',
        'Requester' => 'Organisation Requester',
      ],
    ];

    foreach ($fields as $wrapper_id => $field_values) {
      $wrapper = $this->getSession()->getPage()->find('css', $wrapper_id);
      foreach ($field_values as $label => $value) {
        $this->assertSession()->fieldExists($label, $wrapper);
        $wrapper->fillField($label, $value);
      }
    }
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('New translation request 1 has been created.');

    // Assert the values where correctly stored.
    $requests = $this->entityTypeManager->getStorage('oe_translation_request')->loadMultiple();
    $request = reset($requests);
    $this->drupalGet(Url::fromRoute('entity.oe_translation_request.edit_form', ['oe_translation_request' => $request->id()]));
    foreach ($fields as $wrapper_id => $field_values) {
      $wrapper = $this->getSession()->getPage()->find('css', $wrapper_id);
      foreach ($field_values as $label => $value) {
        $this->assertSession()->fieldExists($label, $wrapper);
        $this->assertEqual($wrapper->findField($label)->getValue(), $value);
      }
    }
  }

}
