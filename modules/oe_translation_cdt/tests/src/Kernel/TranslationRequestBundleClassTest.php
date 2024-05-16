<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the CDT bundle class for "Translation Request" entity.
 *
 * @group batch1
 */
class TranslationRequestBundleClassTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('oe_translation_request');
    $this->installConfig([
      'oe_translation_remote',
      'oe_translation_cdt',
    ]);
  }

  /**
   * Tests the CDT bundle class setters and getters.
   */
  public function testCdtBundleClass(): void {
    $request = TranslationRequest::create([
      'bundle' => 'cdt',
    ]);
    assert($request instanceof TranslationRequestCdtInterface);

    $request->setCdtId('test_cdt_id');
    $this->assertEquals('test_cdt_id', $request->getCdtId());

    $request->setComments('test_comments');
    $this->assertEquals('test_comments', $request->getComments());

    $request->setConfidentiality('test_confidentiality');
    $this->assertEquals('test_confidentiality', $request->getConfidentiality());

    $request->setContactUsernames([
      'test_contact1',
      'test_contact2',
    ]);
    $this->assertEquals([
      'test_contact1',
      'test_contact2',
    ], $request->getContactUsernames());

    $request->setDeliverTo([
      'test_deliver_to1',
      'test_deliver_to2',
    ]);
    $this->assertEquals([
      'test_deliver_to1',
      'test_deliver_to2',
    ], $request->getDeliverTo());

    $request->setCorrelationId('test_correlation_id');
    $this->assertEquals('test_correlation_id', $request->getCorrelationId());

    $request->setDepartment('test_department');
    $this->assertEquals('test_department', $request->getDepartment());

    $request->setPhoneNumber('test_phone_number');
    $this->assertEquals('test_phone_number', $request->getPhoneNumber());

    $request->setPriority('test_priority');
    $this->assertEquals('test_priority', $request->getPriority());
  }

}
