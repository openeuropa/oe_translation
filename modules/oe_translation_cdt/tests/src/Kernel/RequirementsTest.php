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
   *
   * @param string[] $settings
   *   The settings to test.
   * @param mixed[] $expected_requirements
   *   The expected requirements.
   *
   * @dataProvider cdtRequirementsProvider
   */
  public function testCdtRequirements(array $settings, array $expected_requirements): void {
    new Settings($settings);
    \Drupal::moduleHandler()->loadInclude('oe_translation_cdt', 'install');
    $requirements = oe_translation_cdt_requirements('runtime');

    $this->assertCount(10, $requirements);
    foreach ($requirements as $name => $requirement) {
      $this->assertNotEmpty($expected_requirements[$name], sprintf('The %s requirement is not expected', $name));
      $expected_requirement = $expected_requirements[$name];
      $this->assertEquals($expected_requirement['value'], $requirement['value'], sprintf('The %s value is incorrect', $name));
      $this->assertEquals($expected_requirement['severity'], $requirement['severity'], sprintf('The %s severity is incorrect', $name));
    }
  }

  /**
   * Data provider for testCdtRequirements.
   *
   * @return mixed[]
   *   The test data.
   */
  public static function cdtRequirementsProvider(): array {
    return [
      'correct settings' => [
        [
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
        ],
        [
          'cdt.token_api_endpoint' => [
            'value' => 'https://example.com/token',
            'severity' => 0,
          ],
          'cdt.main_api_endpoint' => [
            'value' => 'https://example.com/v2/CheckConnection',
            'severity' => 0,
          ],
          'cdt.reference_data_api_endpoint' => [
            'value' => 'https://example.com/v2/requests/businessReferenceData',
            'severity' => 0,
          ],
          'cdt.validate_api_endpoint' => [
            'value' => 'https://example.com/v2/requests/validate',
            'severity' => 0,
          ],
          'cdt.requests_api_endpoint' => [
            'value' => 'https://example.com/v2/requests',
            'severity' => 0,
          ],
          'cdt.identifier_api_endpoint' => [
            'value' => 'https://example.com/v2/requests/requestIdentifierByCorrelationId/:correlationId',
            'severity' => 0,
          ],
          'cdt.status_api_endpoint' => [
            'value' => 'https://example.com/v2/requests/:requestyear/:requestnumber',
            'severity' => 0,
          ],
          'cdt.username' => [
            'value' => 'test_username',
            'severity' => 0,
          ],
          'cdt.client' => [
            'value' => 'test_client',
            'severity' => 0,
          ],
          'cdt.password' => [
            'value' => 'Value set',
            'severity' => 0,
          ],
        ],
      ],
      'missing settings' => [
        [
          'cdt.token_api_endpoint' => 'https://example.com/token',
        ],
        [
          'cdt.token_api_endpoint' => [
            'value' => 'https://example.com/token',
            'severity' => 0,
          ],
          'cdt.main_api_endpoint' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.reference_data_api_endpoint' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.validate_api_endpoint' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.requests_api_endpoint' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.identifier_api_endpoint' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.status_api_endpoint' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.username' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.client' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
          'cdt.password' => [
            'value' => 'Value not set',
            'severity' => 1,
          ],
        ],
      ],
    ];
  }

}
