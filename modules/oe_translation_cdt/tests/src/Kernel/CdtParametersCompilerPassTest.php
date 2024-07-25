<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation_cdt\Compiler\CdtParametersCompilerPass;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the compiler pass that sets CDT configuration.
 *
 * @group batch1
 */
class CdtParametersCompilerPassTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * Tests that the CDT configuration is passed properly to the container.
   */
  public function testCdtCompilerPass(): void {
    // Define strings with "%" characters, that need to be escaped.
    $settings = [
      'cdt.base_api_url' => '%url%',
      'cdt.username' => '%username!@#$^&*()_+=-,./?;test',
      'cdt.client' => '%%client%%',
      'cdt.password' => 'test_%%password%',
      'cdt.api_key' => 'test_%api_key',
    ];
    new Settings($settings);

    // Define a simple inline class that will receive the parameters.
    $receiver_object = new class {

      /**
       * Constructs the parameter receiver object.
       */
      public function __construct(public array $configuration = []) {
      }

    };

    // Create a new container and register the compiler pass.
    $container = new ContainerBuilder();
    $container->addCompilerPass(new CdtParametersCompilerPass());
    $container->register('parameter_receiver', get_class($receiver_object))
      ->addArgument([
        'client' => '%cdt.client%',
        'username' => '%cdt.username%',
        'password' => '%cdt.password%',
        'apiBaseUrl' => '%cdt.base_api_url%',
      ]);
    $container->compile();

    // Assert the configuration values.
    $receiver_service = $container->get('parameter_receiver');
    assert($receiver_service instanceof $receiver_object);
    $this->assertEquals($receiver_service->configuration['apiBaseUrl'], '%url%');
    $this->assertEquals($receiver_service->configuration['username'], '%username!@#$^&*()_+=-,./?;test');
    $this->assertEquals($receiver_service->configuration['client'], '%%client%%');
    $this->assertEquals($receiver_service->configuration['password'], 'test_%%password%');
  }

}
