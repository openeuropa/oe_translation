<?php

declare(strict_types=1);

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
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * Tests that the CDT requirements values are set.
   */
  public function testCdtRequirements(): void {
    // Test correct settings.
    $settings = [
      'cdt.token_api_endpoint' => 'https://example.com/token',
      'cdt.main_api_endpoint' => 'https://example.com/v2/CheckConnection',
      'cdt.reference_data_api_endpoint' => 'https://example.com/v2/requests/businessReferenceData',
      'cdt.validate_api_endpoint' => 'https://example.com/v2/requests/validate',
      'cdt.requests_api_endpoint' => 'https://example.com/v2/requests',
      'cdt.identifier_api_endpoint' => 'https://example.com/v2/requests/requestIdentifierByCorrelationId/:correlationId',
      'cdt.status_api_endpoint' => 'https://example.com/v2/requests/:requestyear/:requestnumber',
      'cdt.username' => 'test_username',
      'cdt.client' => 'test_client',
      'cdt.password' => 'test_password',
      'cdt.api_key' => 'test_api_key',
    ];
    new Settings($settings);
    \Drupal::moduleHandler()->loadInclude('oe_translation_cdt', 'install');
    $requirements = oe_translation_cdt_requirements('runtime');
    foreach ($settings as $name => $value) {
      $this->assertArrayHasKey($name, $requirements);
      if (in_array($name, ['cdt.password', 'cdt.api_key'])) {
        $this->assertEquals('Value set', $requirements[$name]['value']);
      }
      else {
        $this->assertEquals($value, $requirements[$name]['value']);
      }
      $this->assertEquals(REQUIREMENT_OK, $requirements[$name]['severity']);
    }

    // Test missing settings.
    new Settings([]);
    $requirements = oe_translation_cdt_requirements('runtime');
    foreach ($settings as $name => $value) {
      $this->assertArrayHasKey($name, $requirements);
      $this->assertEquals('Value not set', $requirements[$name]['value']);
      $this->assertEquals(REQUIREMENT_WARNING, $requirements[$name]['severity']);
    }
  }

}