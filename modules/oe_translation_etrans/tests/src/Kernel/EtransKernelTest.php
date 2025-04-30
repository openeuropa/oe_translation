<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_etrans\Kernel;

use Drupal\oe_translation_etrans\TranslationRequestEtrans;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Various kernel tests for eTrans.
 *
 * @group batch1
 */
class EtransKernelTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_remote',
    'oe_translation_etrans',
    'oe_translation_content_formatter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('oe_translation_request');
    $this->installConfig('oe_translation_remote');
    $this->installConfig('oe_translation_etrans');
  }

  /**
   * Tests the automatic status setting of requests.
   */
  public function testAutomaticRequestStatus(): void {
    // Test the request is set to Translated if all languages are in review.
    $languages[] = [
      'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_REQUESTED,
      'langcode' => 'fr',
    ];
    $languages[] = [
      'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_REQUESTED,
      'langcode' => 'de',
    ];
    $request = TranslationRequestEtrans::create([
      'bundle' => 'etrans',
      'source_language_code' => 'en',
      'target_languages' => $languages,
      'translator_provider' => 'etrans',
      'request_status' => TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED,
    ]);
    $request->save();
    /** @var \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $request */
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
    $request->updateTargetLanguageStatus('fr', TranslationRequestEtransInterface::STATUS_LANGUAGE_REVIEW);
    $request->save();
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
    $request->updateTargetLanguageStatus('de', TranslationRequestEtransInterface::STATUS_LANGUAGE_REVIEW);
    $request->save();
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());

    $request->delete();

    // Test the request is set to Finished if all languages are synchronised.
    $languages = [];
    $languages[] = [
      'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_ACCEPTED,
      'langcode' => 'fr',
    ];
    $languages[] = [
      'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_ACCEPTED,
      'langcode' => 'de',
    ];
    $request = TranslationRequestEtrans::create([
      'bundle' => 'etrans',
      'source_language_code' => 'en',
      'target_languages' => $languages,
      'translator_provider' => 'etrans',
      'request_status' => TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED,
    ]);
    $request->save();
    /** @var \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $request */
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());

    $request->updateTargetLanguageStatus('fr', TranslationRequestEtransInterface::STATUS_LANGUAGE_SYNCHRONISED);
    $request->save();
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $request->updateTargetLanguageStatus('de', TranslationRequestEtransInterface::STATUS_LANGUAGE_SYNCHRONISED);
    $request->save();
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_FINISHED, $request->getRequestStatus());

    $request->delete();

    // Test the request is set to Failed if all languages are Failed.
    $languages = [];
    $languages[] = [
      'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_REVIEW,
      'langcode' => 'fr',
    ];
    $languages[] = [
      'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_REVIEW,
      'langcode' => 'de',
    ];
    $request = TranslationRequestEtrans::create([
      'bundle' => 'etrans',
      'source_language_code' => 'en',
      'target_languages' => $languages,
      'translator_provider' => 'etrans',
      'request_status' => TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED,
    ]);
    $request->save();
    /** @var \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $request */
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());

    $request->updateTargetLanguageStatus('fr', TranslationRequestEtransInterface::STATUS_REQUEST_FAILED);
    $request->save();
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $request->updateTargetLanguageStatus('de', TranslationRequestEtransInterface::STATUS_REQUEST_FAILED);
    $request->save();
    $request = TranslationRequestEtrans::load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_FAILED, $request->getRequestStatus());

  }

}
