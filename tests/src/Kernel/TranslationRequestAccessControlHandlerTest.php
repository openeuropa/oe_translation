<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test the translation request access control handler.
 */
class TranslationRequestAccessControlHandlerTest extends EntityKernelTestBase {

  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'options',
    'language',
    'content_translation',
    'oe_translation',
    'views',
  ];

  /**
   * The access control handler.
   *
   * @var \Drupal\oe_translation\Access\TranslationRequestAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'language',
      'oe_translation',
      'views',
    ]);

    $this->installEntitySchema('oe_translation_request');
    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('oe_translation_request');

    // Create a UID 1 user to be able to create test users with particular
    // permissions in the tests.
    $this->drupalCreateUser();

    // Create bundles for tests.
    $type_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_type');
    $type_storage->create([
      'id' => 'request',
      'label' => 'Request',
    ])->save();
    $type_storage->create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
  }

  /**
   * Ensures translation request access is properly working.
   */
  public function testAccess(): void {
    $scenarios = $this->accessDataProvider();
    $translation_request_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request');
    $values = [
      'bundle' => 'request',
    ];

    // Create a translation request.
    /** @var \Drupal\oe_translation\Entity\TranslationRequest $translation_request */
    $translation_request = $translation_request_storage->create($values);
    $translation_request->save();

    foreach ($scenarios as $scenario => $test_data) {
      // Update the published status based on the scenario.
      if ($test_data['published']) {
        $translation_request->setPublished();
      }
      else {
        $translation_request->setUnpublished();
      }
      $translation_request->save();

      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->access($translation_request, $test_data['operation'], $user, TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }
  }

  /**
   * Ensures translation request create access is properly working.
   */
  public function testCreateAccess(): void {
    $scenarios = $this->createAccessDataProvider();
    foreach ($scenarios as $scenario => $test_data) {
      $user = $this->drupalCreateUser($test_data['permissions']);
      $this->assertAccessResult(
        $test_data['expected_result'],
        $this->accessControlHandler->createAccess('request', $user, [], TRUE),
        sprintf('Failed asserting access for "%s" scenario.', $scenario)
      );
    }
  }

  /**
   * Asserts translation request access correctly grants or denies access.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $expected
   *   The expected result.
   * @param \Drupal\Core\Access\AccessResultInterface $actual
   *   The actual result.
   * @param string $message
   *   Failure message.
   */
  protected function assertAccessResult(AccessResultInterface $expected, AccessResultInterface $actual, string $message = ''): void {
    $this->assertEquals($expected->isAllowed(), $actual->isAllowed(), $message);
    $this->assertEquals($expected->isForbidden(), $actual->isForbidden(), $message);
    $this->assertEquals($expected->isNeutral(), $actual->isNeutral(), $message);

    $this->assertEquals($expected->getCacheMaxAge(), $actual->getCacheMaxAge(), $message);
    $cache_types = [
      'getCacheTags',
      'getCacheContexts',
    ];
    foreach ($cache_types as $type) {
      $expected_cache_data = $expected->{$type}();
      $actual_cache_data = $actual->{$type}();
      sort($expected_cache_data);
      sort($actual_cache_data);
      $this->assertEquals($expected_cache_data, $actual_cache_data, $message);
    }
  }

  /**
   * Data provider for testAccess().
   *
   * This method is not declared as a real PHPUnit data provider to speed up
   * test execution.
   *
   * @return array
   *   The data sets to test.
   */
  protected function accessDataProvider(): array {
    return [
      'user without permissions / published translation request' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => TRUE,
      ],
      'user without permissions / unpublished translation request' => [
        'permissions' => [],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => FALSE,
      ],
      'admin view' => [
        'permissions' => ['administer translation requests'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'admin view unpublished' => [
        'permissions' => ['administer translation requests'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => FALSE,
      ],
      'admin update' => [
        'permissions' => ['administer translation requests'],
        'operation' => 'update',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'admin delete' => [
        'permissions' => ['administer translation requests'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with view access / published translation request' => [
        'permissions' => ['view translation request'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => TRUE,
      ],
      'user with view access / unpublished translation request' => [
        'permissions' => ['view translation request'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => FALSE,
      ],
      'user with view unpublished access / published translation request' => [
        'permissions' => ['view unpublished translation request'],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => TRUE,
      ],
      'user with view unpublished access / unpublished translation request' => [
        'permissions' => ['view unpublished translation request'],
        'operation' => 'view',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => FALSE,
      ],
      'user with create, update, delete access / published translation request' => [
        'permissions' => [
          'create request translation request',
          'edit request translation request',
          'delete request translation request',
        ],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => TRUE,
      ],
      'user with create, update, delete access / unpublished translation request' => [
        'permissions' => [
          'create request translation request',
          'edit request translation request',
          'delete request translation request',
        ],
        'operation' => 'view',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions'])->addCacheTags(['oe_translation_request:1']),
        'published' => FALSE,
      ],
      'user with update access' => [
        'permissions' => ['edit request translation request'],
        'operation' => 'update',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with update access on different bundle' => [
        'permissions' => ['edit test translation request'],
        'operation' => 'update',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with create, view, delete access' => [
        'permissions' => [
          'create request translation request',
          'view translation request',
          'view unpublished translation request',
          'delete request translation request',
        ],
        'operation' => 'update',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with delete access' => [
        'permissions' => ['delete request translation request'],
        'operation' => 'delete',
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with delete access on different bundle' => [
        'permissions' => ['delete test translation request'],
        'operation' => 'delete',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
      'user with create, view, update access' => [
        'permissions' => [
          'create request translation request',
          'view translation request',
          'view unpublished translation request',
          'edit request translation request',
        ],
        'operation' => 'delete',
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
        'published' => TRUE,
      ],
    ];
  }

  /**
   * Data provider for testCreateAccess().
   *
   * This method is not declared as a real PHPUnit data provider to speed up
   * test execution.
   *
   * @return array
   *   The data sets to test.
   */
  protected function createAccessDataProvider(): array {
    return [
      'user without permissions' => [
        'permissions' => [],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'admin' => [
        'permissions' => ['administer translation requests'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user with view access' => [
        'permissions' => ['view translation request'],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with view, update and delete access' => [
        'permissions' => [
          'view translation request',
          'view unpublished translation request',
          'edit request translation request',
          'delete request translation request',
        ],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
      'user with create access' => [
        'permissions' => ['create request translation request'],
        'expected_result' => AccessResult::allowed()->addCacheContexts(['user.permissions']),
      ],
      'user with create access on different bundle' => [
        'permissions' => ['create test translation request'],
        'expected_result' => AccessResult::neutral()->addCacheContexts(['user.permissions']),
      ],
    ];
  }

}
