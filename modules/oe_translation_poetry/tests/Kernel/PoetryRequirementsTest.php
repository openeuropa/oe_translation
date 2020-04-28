<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests that the Poetry requirements values are set.
 */
class PoetryRequirementsTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
    'oe_translation_poetry_html_formatter',
    'system',
    'tmgmt',
    'tmgmt_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $settings = Settings::getAll();
    $settings['poetry.service.endpoint'] = 'http://web:8080/build/poetry-mock/wsdl';
    $settings['poetry.service.username'] = 'service username';
    $settings['poetry.service.password'] = 'service password';
    $settings['poetry.notification.username'] = 'notification username';
    $settings['poetry.notification.password'] = 'notification password';
    $settings['poetry.identifier.sequence'] = 'sequence';
    new Settings($settings);
  }

  /**
   * Tests that the Poetry requirements values are set.
   */
  public function testStatusRequirements(): void {
    \module_load_install('oe_translation_poetry');
    $requirements = oe_translation_poetry_requirements('runtime');

    $values = [
      'poetry.service.endpoint' => 'http://web:8080/build/poetry-mock/wsdl',
      'poetry.service.username' => t('Value set'),
      'poetry.service.password' => t('Value set'),
      'poetry.notification.username' => t('Value set'),
      'poetry.notification.password' => t('Value set'),
      'poetry.identifier.sequence' => 'sequence',
    ];

    $this->assertCount(6, $requirements);
    foreach ($requirements as $name => $requirement) {
      $this->assertNotEmpty($requirement['value']);
      $this->assertEquals($values[$name], $requirement['value'], sprintf('The %s value is not set', $name));
    }
  }

}
