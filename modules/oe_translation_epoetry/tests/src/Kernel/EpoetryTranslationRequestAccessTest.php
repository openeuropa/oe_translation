<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\Kernel;

use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\oe_translation_epoetry\EpoetryTranslationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\oe_translation_epoetry\Controller\EpoetryController;
use Drupal\oe_translation_epoetry\Form\ModifyLinguisticRequestForm;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;

/**
 * Tests the access to create ePoetry translation requests.
 *
 * @see \Drupal\oe_translation_epoetry\Plugin\RemoteTranslationProvider\Epoetry::createAccess()
 * @see \Drupal\oe_translation_epoetry\Controller\EpoetryController::finishFailedRequestAccess()
 * @see \Drupal\oe_translation_epoetry\Controller\EpoetryController::createNewVersionRequestAccess()
 * @see \Drupal\oe_translation_epoetry\Form\ModifyLinguisticRequestForm::access()
 * @see \Drupal\oe_translation_test\EventSubscriber\TranslationOperationAccessEventSubscriber
 *
 * @group batch2
 */
class EpoetryTranslationRequestAccessTest extends TranslationKernelTestBase {

  use UserCreationTrait;
  use EpoetryTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_remote',
    'oe_translation_epoetry',
  ];

  /**
   * The ePoetry plugin instance.
   *
   * @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['oe_translation_remote', 'oe_translation_epoetry']);

    /** @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager $manager */
    $manager = $this->container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager');
    $this->plugin = $manager->createInstance('epoetry', []);
  }

  /**
   * Tests that the TranslationRequestCreateAccessEvent can grant access.
   */
  public function testCreateAccessEvent(): void {
    $node = $this->createBasicTestNode();

    // Consume uid=1.
    $this->createUser();

    $with_permission = $this->createUser(['request epoetry translation']);
    $without_permission = $this->createUser([]);

    // Without an account, access is forbidden and the event never fires.
    $this->assertTrue($this->plugin->createAccess(NULL)->isForbidden(), 'Access should be forbidden if there is no account');

    // With the permission, access is allowed regardless of the entity or any
    // override.
    $this->plugin->setEntity($node);
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue($this->plugin->createAccess($with_permission)->isAllowed(), 'Access should be allowed when the user has global permission.');

    // Without the permission and without an entity set on the plugin, the
    // event cannot be dispatched, so the access remains forbidden.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin_without_entity */
    $manager = $this->container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager');
    $plugin_without_entity = $manager->createInstance('epoetry', []);
    $this->assertTrue($plugin_without_entity->createAccess($without_permission)->isForbidden(), 'Access should be forbidden if there is no global permission and entity.');

    // Without the permission but with an entity set and no override, access
    // remains forbidden.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($this->plugin->createAccess($without_permission)->isForbidden(), 'Access should be forbidden if there is no account and global permission.');

    // Without the permission but with an entity set, the event can grant
    // access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue($this->plugin->createAccess($without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($this->plugin->createAccess($without_permission)->isForbidden(), 'Access should not be overridden if there is no event subscriber.');
  }

  /**
   * Tests that the event can grant access to finish a failed request.
   */
  public function testFinishFailedRequestAccessEvent(): void {
    $node = $this->createBasicTestNode();

    // Consume uid=1.
    $this->createUser();

    $with_permission = $this->createUser(['translate any entity']);
    $without_permission = $this->createUser([]);

    $request = $this->createNodeTranslationRequest($node, TranslationRequestEpoetryInterface::STATUS_REQUEST_FAILED);
    $request->save();

    $controller = EpoetryController::create($this->container);

    // With the permission, access is allowed regardless of any override.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue($controller->finishFailedRequestAccess($request, $with_permission)->isAllowed(), 'Access should be allowed when the user has global permission.');

    // Without the permission and without an event subscriber override,
    // access remains forbidden.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isForbidden(), 'Access should be forbidden if there is no global permission and no override.');

    // Without the permission, the event can grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($controller->finishFailedRequestAccess($request, $without_permission)->isForbidden(), 'Access should not be overridden if there is no event subscriber.');
  }

  /**
   * Tests that the event can grant access to create a new version request.
   */
  public function testCreateNewVersionRequestAccessEvent(): void {
    $node = $this->createBasicTestNode();

    // Consume uid=1.
    $this->createUser();

    $with_permission = $this->createUser(['translate any entity', 'request epoetry translation']);
    $without_permission = $this->createUser([]);

    $provider = RemoteTranslatorProvider::load('epoetry');
    $provider->set('enabled', TRUE);
    $provider->save();

    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    // Create a new revision so that a new version request can be made.
    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    $controller = EpoetryController::create($this->container);

    // With the permissions, access is allowed regardless of any override.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue($controller->createNewVersionRequestAccess($request, $with_permission)->isAllowed(), 'Access should be allowed when the user has global permissions.');

    // Without the permissions and without an event subscriber override,
    // access remains forbidden.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($controller->createNewVersionRequestAccess($request, $without_permission)->isForbidden(), 'Access should be forbidden if there are no global permissions and no override.');

    // Without the permissions, the event can grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue($controller->createNewVersionRequestAccess($request, $without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($controller->createNewVersionRequestAccess($request, $without_permission)->isForbidden(), 'Access should not be overridden if there is no event subscriber.');
  }

  /**
   * Tests that the event can grant access to modify a linguistic request.
   */
  public function testModifyLinguisticRequestFormAccessEvent(): void {
    $node = $this->createBasicTestNode();

    // Consume uid=1.
    $this->createUser();

    $with_permission = $this->createUser(['translate any entity', 'request epoetry translation']);
    $without_permission = $this->createUser([]);

    $provider = RemoteTranslatorProvider::load('epoetry');
    $provider->set('enabled', TRUE);
    $provider->save();

    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    // With the permissions, access is allowed regardless of any override.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue(ModifyLinguisticRequestForm::access($request, $with_permission)->isAllowed(), 'Access should be allowed when the user has global permissions.');

    // Without the permissions and without an event subscriber override,
    // access remains forbidden.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue(ModifyLinguisticRequestForm::access($request, $without_permission)->isForbidden(), 'Access should be forbidden if there are no global permissions and no override.');

    // Without the permissions, the event can grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue(ModifyLinguisticRequestForm::access($request, $without_permission)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue(ModifyLinguisticRequestForm::access($request, $without_permission)->isForbidden(), 'Access should not be overridden if there is no event subscriber.');
  }

}
