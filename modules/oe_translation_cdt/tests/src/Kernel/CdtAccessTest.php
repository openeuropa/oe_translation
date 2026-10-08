<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\oe_translation_cdt\Traits\CdtTranslationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\oe_translation_cdt\Access\CdtAccessCheck;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface;

/**
 * Tests the access handler of CDT.
 *
 * The handler checks if the user has correct permissions,
 * and if the CDT plugin is enabled at all.
 *
 * @see \Drupal\oe_translation_cdt\Plugin\RemoteTranslationProvider\Cdt::createAccess()
 * @see \Drupal\oe_translation_cdt\Controller\OperationController::requestOperationAccess()
 * @see \Drupal\oe_translation_test\EventSubscriber\TranslationOperationAccessEventSubscriber
 *
 * @group batch1
 */
class CdtAccessTest extends TranslationKernelTestBase {

  use UserCreationTrait;
  use CdtTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * The access checker service.
   */
  protected CdtAccessCheck $accessCheck;

  /**
   * The translator user.
   */
  protected AccountInterface $translator;

  /**
   * The non-translator user.
   */
  protected AccountInterface $nonTranslator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['oe_translation_remote']);
    $this->installConfig(['oe_translation_cdt']);

    $this->accessCheck = $this->container->get('oe_translation_cdt.access_check');
    $non_translator = $this->createUser(['access content'], NULL, FALSE, ['uid' => 2]);
    assert($non_translator instanceof AccountInterface);
    $this->nonTranslator = $non_translator;
    $translator = $this->createUser(['translate any entity'], NULL, FALSE, ['uid' => 3]);
    assert($translator instanceof AccountInterface);
    $this->translator = $translator;
  }

  /**
   * Tests the access by permissions.
   */
  public function testPermissions(): void {
    $this->assertFalse($this->accessCheck->access($this->nonTranslator)
      ->isAllowed(), 'Any user can access the CDT.');
    $this->assertTrue($this->accessCheck->access($this->translator)
      ->isAllowed(), 'Translators do not have access to the CDT.');

    // The access events must not grant access to the dashboard.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', [
      'create' => 'allowed',
      'overview' => 'allowed',
      'accept' => 'allowed',
      'sync' => 'allowed',
    ]);
    $this->assertFalse($this->accessCheck->access($this->nonTranslator)
      ->isAllowed(), 'The events should not grant access to the dashboard.');
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
  }

  /**
   * Tests the access by disabling the provider in plugin settings.
   */
  public function testPluginSettings(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $translators = $entity_type_manager->getStorage('remote_translation_provider')->loadByProperties([
      'plugin' => 'cdt',
    ]);
    $this->assertNotEmpty($translators, 'The CDT plugin is enabled.');
    $cdt = reset($translators);
    assert($cdt instanceof RemoteTranslatorProviderInterface);
    $cdt->set('enabled', FALSE);
    $cdt->save();
    $this->assertFalse($this->accessCheck->access($this->nonTranslator)
      ->isAllowed(), 'Any user can access the CDT.');
    $this->assertFalse($this->accessCheck->access($this->translator)
      ->isAllowed(), 'Translator can access CDT when it is disabled.');
  }

  /**
   * Tests that the TranslationRequestCreateAccessEvent can grant access.
   */
  public function testCreateAccessEvent(): void {
    $node = $this->createBasicTestNode();

    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
    $plugin = $this->container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->createInstance('cdt', []);

    // Without an account, access is forbidden.
    $this->assertTrue($plugin->createAccess(NULL)->isForbidden(), 'Access should be forbidden if there is no account.');

    $plugin->setEntity($node);
    $this->assertTrue($plugin->createAccess($this->translator)->isAllowed(), 'Access should be allowed when the user has global permission.');

    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertTrue($plugin->createAccess($this->translator)->isForbidden(), 'Access should be revocable by the event even when the user has global permission.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertTrue($plugin->createAccess($this->nonTranslator)->isForbidden(), 'Access should be forbidden if there is no global permission and no override.');

    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertTrue($plugin->createAccess($this->nonTranslator)->isAllowed(), 'Access should be overridden to allowed by the dispatched event.');
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
  }

  /**
   * Tests the access to the request operation routes.
   */
  public function testOperationRoutesAccess(): void {
    $request = $this->createTranslationRequest(self::getCommonTranslationRequestData(), ['fr'], $this->createBasicTestNode());
    $request->save();

    // The global permission grants access when no subscriber overrides it.
    $this->assertRouteAccess($request, TRUE);

    // A subscriber can revoke access even with the global permission.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'forbidden']);
    $this->assertRouteAccess($request, FALSE);

    // Without the global permission, access is forbidden.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $this->assertRouteAccess($request, FALSE, $this->nonTranslator);

    // The synchronize and accept events must not grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', [
      'sync' => 'allowed',
      'accept' => 'allowed',
    ]);
    $this->assertRouteAccess($request, FALSE, $this->nonTranslator);

    // The create event can grant access.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);
    $this->assertRouteAccess($request, TRUE, $this->nonTranslator);

    // A disabled provider forbids access even if the event allows it.
    $provider = RemoteTranslatorProvider::load('cdt');
    $provider->set('enabled', FALSE);
    $provider->save();
    // Make sure the request references the updated provider.
    $this->container->get('entity_type.manager')->getStorage('oe_translation_request')->resetCache();
    $this->assertRouteAccess($request, FALSE, $this->nonTranslator);
    $this->assertRouteAccess($request, FALSE);

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
  }

  /**
   * Asserts the access to the CDT request operation routes.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request
   *   The translation request.
   * @param bool $expected
   *   Whether access is expected to be allowed.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, defaults to the translator.
   */
  protected function assertRouteAccess(TranslationRequestCdtInterface $request, bool $expected, ?AccountInterface $account = NULL): void {
    $account = $account ?? $this->translator;
    /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
    $access_manager = $this->container->get('access_manager');
    $routes = [
      'oe_translation_cdt.get_permanent_id' => ['translation_request' => $request->id()],
      'oe_translation_cdt.refresh_status' => ['translation_request' => $request->id()],
      'oe_translation_cdt.fetch_translation' => ['translation_request' => $request->id(), 'langcode' => 'fr'],
    ];
    foreach ($routes as $route => $parameters) {
      $this->assertSame($expected, $access_manager->checkNamedRoute($route, $parameters, $account), sprintf('Unexpected access to the %s route.', $route));
    }
  }

}
