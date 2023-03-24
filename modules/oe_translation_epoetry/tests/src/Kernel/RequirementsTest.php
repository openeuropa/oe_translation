<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Status values set in the requirements.
 *
 * @group batch1
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
    $settings['epoetry.ticket_validation.eulogin_base_path'] = 'http://example.com/ticket';
    $settings['epoetry.ticket_validation.eulogin_job_account'] = '3rwefdf';
    $settings['epoetry.ticket_validation.callback_url'] = 'http://callback';
    $settings['epoetry.ticket_validation.on'] = '1';
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
      'epoetry.ticket_validation.eulogin_base_path' => t('Value set'),
      'epoetry.ticket_validation.eulogin_job_account' => t('Value set'),
      'epoetry.ticket_validation.callback_url' => t('Value set'),
      'epoetry.ticket_validation.on' => 1,
    ];

    $this->assertCount(11, $requirements);
    foreach ($requirements as $name => $requirement) {
      $this->assertNotEmpty($requirement['value']);
      $this->assertEquals($values[$name], $requirement['value'], sprintf('The %s value is not set', $name));
    }
  }

}
