<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_active_revision\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_translation_active_revision\Access\ActiveRevisionMappingAccessCheck;
use Drupal\oe_translation_active_revision\Event\ActiveRevisionMappingAccessEvent;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the ActiveRevisionMappingAccessCheck service.
 *
 * @group batch3
 */
class ActiveRevisionMappingAccessCheckTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'entity_test',
    'language',
    'content_translation',
  ];

  /**
   * The access check service.
   *
   * @var \Drupal\oe_translation_active_revision\Access\ActiveRevisionMappingAccessCheck
   */
  protected $accessCheck;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    $this->accessCheck = new ActiveRevisionMappingAccessCheck($this->container->get('entity_type.manager'), $this->container->get('event_dispatcher'));

    // Consume uid=1.
    $this->createUser();
  }

  /**
   * Tests that the event can grant access on top of the global permission.
   */
  public function testAccess(): void {
    $entity = EntityTest::create(['name' => 'Test entity']);
    $entity->save();

    $with_permission = $this->createUser(['translate any entity']);
    $without_permission = $this->createUser([]);

    // With the permission, access is allowed as long as there is no event
    // subscriber overriding it.
    $this->assertTrue($this->accessCheck->access('entity_test', (string) $entity->id(), $with_permission)->isAllowed(), 'Access should be allowed when the user has global permission.');

    // Without the permission and without an event subscriber, access is
    // forbidden.
    $this->assertTrue($this->accessCheck->access('entity_test', (string) $entity->id(), $without_permission)->isForbidden(), 'Access should be forbidden if there is no global permission and no override.');

    // A subscriber can grant access on top of the default permission check,
    // e.g. based on a more granular, entity-scoped access model.
    $listener = function (ActiveRevisionMappingAccessEvent $event) {
      $event->setAccess(AccessResult::allowed());
    };
    $this->container->get('event_dispatcher')->addListener(ActiveRevisionMappingAccessEvent::class, $listener);
    $this->assertTrue($this->accessCheck->access('entity_test', (string) $entity->id(), $without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');

    // A subscriber can also revoke access, even for a user with the global
    // permission.
    $this->container->get('event_dispatcher')->removeListener(ActiveRevisionMappingAccessEvent::class, $listener);
    $revoke_listener = function (ActiveRevisionMappingAccessEvent $event) {
      $event->setAccess(AccessResult::forbidden('Forbidden by the test subscriber.'));
    };
    $this->container->get('event_dispatcher')->addListener(ActiveRevisionMappingAccessEvent::class, $revoke_listener);
    $this->assertTrue($this->accessCheck->access('entity_test', (string) $entity->id(), $with_permission)->isForbidden(), 'Access should be revocable by the event even when the user has global permission.');
  }

  /**
   * Tests that no event is dispatched, and access is denied, without an entity.
   */
  public function testAccessWithoutEntity(): void {
    $without_permission = $this->createUser([]);

    $listener = function (ActiveRevisionMappingAccessEvent $event) {
      $event->setAccess(AccessResult::allowed());
    };
    $this->container->get('event_dispatcher')->addListener(ActiveRevisionMappingAccessEvent::class, $listener);

    $this->assertTrue($this->accessCheck->access('entity_test', '999', $without_permission)->isForbidden(), 'Access should be forbidden when the entity cannot be loaded, regardless of subscribers.');
  }

}
