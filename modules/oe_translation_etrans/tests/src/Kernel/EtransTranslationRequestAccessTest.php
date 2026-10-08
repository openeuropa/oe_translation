<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_etrans\Kernel;

use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\oe_translation_etrans\Traits\EtransTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\oe_translation_etrans\Controller\EtransController;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;

/**
 * Tests the access to eTrans translation request operations.
 *
 * @see \Drupal\oe_translation_etrans\Plugin\RemoteTranslationProvider\Etrans::createAccess()
 * @see \Drupal\oe_translation_etrans\Controller\EtransController::finishFailedRequestAccess()
 * @see \Drupal\oe_translation_test\EventSubscriber\TranslationOperationAccessEventSubscriber
 *
 * @group batch1
 */
class EtransTranslationRequestAccessTest extends TranslationKernelTestBase {

  use UserCreationTrait;
  use EtransTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_remote',
    'oe_translation_etrans',
    'oe_translation_content_formatter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['oe_translation_remote', 'oe_translation_etrans']);

    RemoteTranslatorProvider::create([
      'id' => 'etrans',
      'label' => 'eTrans',
      'plugin' => 'etrans',
      'plugin_configuration' => [
        'language_mapping' => [],
        'domain' => 'GEN',
      ],
      'enabled' => TRUE,
    ])->save();
  }

  /**
   * Tests that the TranslationRequestCreateAccessEvent can grant access.
   */
  public function testCreateAccessEvent(): void {
    $node = $this->createBasicTestNode();

    // Consume uid=1.
    $this->createUser();

    $with_permission = $this->createUser(['translate any entity']);
    $without_permission = $this->createUser([]);

    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
    $plugin = $this->container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->createInstance('etrans', []);

    // Without an account, access is forbidden.
    $this->assertTrue($plugin->createAccess(NULL)->isForbidden(), 'Access should be forbidden if there is no account.');

    $plugin->setEntity($node);
    $this->assertTrue($plugin->createAccess($with_permission)->isAllowed(), 'Access should be allowed when the user has global permission.');

    // A subscriber can revoke access even with the global permission.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue($plugin->createAccess($with_permission)->isForbidden(), 'Access should be revocable by the event even when the user has global permission.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($plugin->createAccess($without_permission)->isForbidden(), 'Access should be forbidden if there is no global permission and no override.');

    // Without the permission, the event can grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue($plugin->createAccess($without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
  }

  /**
   * Tests the access to mark a failed request as finished.
   */
  public function testFinishFailedRequestAccess(): void {
    $node = $this->createBasicTestNode();

    // Consume uid=1.
    $this->createUser();

    $with_permission = $this->createUser(['translate any entity']);
    $without_permission = $this->createUser([]);

    $request = $this->createNodeTranslationRequest($node, 'remote-id', TranslationRequestEtransInterface::STATUS_REQUEST_FAILED);
    $request->save();

    $controller = EtransController::create($this->container);

    $this->assertTrue($controller->finishFailedRequestAccess($request, $with_permission)->isAllowed(), 'Access should be allowed when the user has global permission.');

    // A subscriber can revoke access even with the global permission.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue($controller->finishFailedRequestAccess($request, $with_permission)->isForbidden(), 'Access should be revocable by the event even when the user has global permission.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isForbidden(), 'Access should be forbidden if there is no global permission and no override.');

    // The synchronize event must not grant access to request operations.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['sync' => 'allowed']);
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isForbidden(), 'Access should not be granted by the synchronize event.');

    // Without the permission, the create event can grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');

    // Only failed requests can be marked as finished, even if the event allows.
    $request->setRequestStatus(TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED);
    $request->save();
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isForbidden(), 'Access should be forbidden for requests that are not failed.');
    $this->assertTrue($controller->finishFailedRequestAccess($request, $with_permission)->isForbidden(), 'Access should be forbidden for requests that are not failed.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
  }

}
