<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the TranslationRequestAccessCheck service.
 *
 * @group batch1
 */
class TranslationRequestAccessCheckTest extends TranslationKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
  ];

  /**
   * The access check service.
   *
   * @var \Drupal\oe_translation\TranslationRequestAccessCheck
   */
  protected $accessCheck;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');

    $type_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_type');
    $type_storage->create([
      'id' => 'request',
      'label' => 'Request',
    ])->save();

    $this->accessCheck = $this->container->get('oe_translation.access_check');

    // Consume uid=1.
    $this->createUser();
  }

  /**
   * Tests that preview access mirrors the accept-or-synchronise access.
   */
  public function testCheckPreviewAccess(): void {
    $node = $this->createBasicTestNode();
    $translation_request_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request');
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $translation_request_storage->create(['bundle' => 'request']);
    $translation_request->setContentEntity($node);
    $translation_request->save();

    $with_permission = $this->createUser(['accept translation request']);
    $without_permission = $this->createUser([]);

    // With the "accept" permission, access is allowed as long as there is
    // no event subscriber overriding it.
    $this->assertTrue($this->accessCheck->checkPreviewAccess($translation_request, $with_permission)->isAllowed(), 'Access should be allowed when the user can accept the request.');

    // A subscriber can revoke access even for a user with the global
    // permission.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['accept' => 'forbidden']);
    $this->assertTrue($this->accessCheck->checkPreviewAccess($translation_request, $with_permission)->isForbidden(), 'Access should be revocable by the event even when the user has global permission.');

    // Without the permission and without an event subscriber override,
    // access remains forbidden.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($this->accessCheck->checkPreviewAccess($translation_request, $without_permission)->isForbidden(), 'Access should be forbidden if there is no global permission and no override.');

    // Without the permission, the synchronise event alone can also grant
    // access, e.g. based on a more granular, entity-scoped access model.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['sync' => 'allowed']);
    $this->assertTrue($this->accessCheck->checkPreviewAccess($translation_request, $without_permission)->isAllowed(), 'Access should be overridden to allowed by either the accept or the synchronise event.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($this->accessCheck->checkPreviewAccess($translation_request, $without_permission)->isForbidden(), 'Access should not be overridden if there is no event subscriber.');
  }

}
