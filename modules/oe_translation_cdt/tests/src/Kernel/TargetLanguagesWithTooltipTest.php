<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\oe_translation_cdt\Plugin\views\field\TargetLanguagesWithTooltip;
use Drupal\oe_translation_cdt\TranslationRequestCdt;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\views\ResultRow;

/**
 * Tests the views field plugin for the target languages.
 *
 * The plugin displays the target languages with a formatted tooltip
 * showing the language name and translation status.
 *
 * @group batch1
 */
class TargetLanguagesWithTooltipTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * The field plugin.
   */
  protected TargetLanguagesWithTooltip $fieldPlugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['oe_translation_remote']);
    $this->installConfig(['oe_translation_cdt']);

    $this->fieldPlugin = TargetLanguagesWithTooltip::create($this->container, [], 'test_plugin_id', []);
    $this->fieldPlugin->options = [
      'relationship' => 'none',
    ];
  }

  /**
   * Test the rendering of the field.
   */
  public function testRender(): void {
    $request = TranslationRequestCdt::create([
      'bundle' => 'cdt',
    ]);
    assert($request instanceof TranslationRequestCdtInterface);
    $request->updateTargetLanguageStatus('fr', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $request->updateTargetLanguageStatus('es', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW);
    $request->updateTargetLanguageStatus('pl', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW);

    $row = new ResultRow(['_entity' => $request]);
    $markup = $this->fieldPlugin->render($row);
    $plain_text = strip_tags((string) $markup);

    $this->assertStringContainsString('Requested: French', $plain_text);
    $this->assertStringContainsString('Review: Spanish, Polish', $plain_text);
  }

}
