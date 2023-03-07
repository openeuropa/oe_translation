<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Status values set in the requirements.
 */
class RequirementsTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_epoetry',
    'oe_translation_remote',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings = Settings::getAll();
    $settings['epoetry.service_url'] = 'http://web:8080/build/epoetry-mock/server';
    $settings['epoetry.application_name'] = 'DIGIT';
    $settings['epoetry.auth.cert_service_url'] = 'test';
    $settings['epoetry.auth.cert_path'] = 'test';
    $settings['epoetry.auth.cert_password'] = 'test';
    $settings['epoetry.auth.eulogin_base_path'] = 'test';
    $settings['epoetry.notification.endpoint_prefix'] = 'http://example.com';
    new Settings($settings);
  }

  /**
   * Tests that the ePoetry requirements values are set.
   */
  public function testStatusRequirements(): void {
    \Drupal::moduleHandler()->loadInclude('oe_translation_epoetry', 'install');
    $requirements = oe_translation_epoetry_requirements('runtime');

    $values = [
      'epoetry.service_url' => 'http://web:8080/build/epoetry-mock/server',
      'epoetry.application_name' => 'DIGIT',
      'epoetry.notification.endpoint_prefix' => 'http://example.com',
      'epoetry.auth.cert_service_url' => t('Value set'),
      'epoetry.auth.cert_path' => t('Value set'),
      'epoetry.auth.cert_password' => t('Value set'),
      'epoetry.auth.eulogin_base_path' => t('Value set'),
    ];

    $this->assertCount(7, $requirements);
    foreach ($requirements as $name => $requirement) {
      $this->assertNotEmpty($requirement['value']);
      $this->assertEquals($values[$name], $requirement['value'], sprintf('The %s value is not set', $name));
    }
  }

}
