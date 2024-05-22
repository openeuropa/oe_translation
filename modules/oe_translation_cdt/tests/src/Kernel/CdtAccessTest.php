<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_cdt\Access\CdtAccessCheck;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the access handler of CDT.
 *
 * The handler checks if the user has correct permissions,
 * and if the CDT plugin is enabled at all.
 *
 * @group batch1
 */
class CdtAccessTest extends TranslationKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * The access checker.
   */
  protected CdtAccessCheck $accessCheck;

  /**
   * The translator.
   */
  protected AccountInterface $translator;

  /**
   * The non-translator.
   */
  protected AccountInterface $nonTranslator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
   * Test the permissions.
   */
  public function testPermissions(): void {
    $this->assertFalse($this->accessCheck->access($this->nonTranslator)
      ->isAllowed(), 'Any user can access the CDT.');
    $this->assertTrue($this->accessCheck->access($this->translator)
      ->isAllowed(), 'Translators do not have access to the CDT.');
  }

  /**
   * Test the plugin settings by disabling CDT.
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

}
