<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\oe_translation_cdt\Api\CdtApiWrapperInterface;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_cdt\TranslationRequestUpdaterInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\oe_translation_cdt\CdtTranslationTestTrait;
use OpenEuropa\CdtClient\Model\Callback\JobStatus;
use OpenEuropa\CdtClient\Model\Callback\RequestStatus;
use OpenEuropa\CdtClient\Model\Response\Comment;
use OpenEuropa\CdtClient\Model\Response\JobSummary;
use OpenEuropa\CdtClient\Model\Response\ReferenceContact;
use OpenEuropa\CdtClient\Model\Response\ReferenceData;
use OpenEuropa\CdtClient\Model\Response\ReferenceItem;
use OpenEuropa\CdtClient\Model\Response\Translation;

/**
 * Tests the CDT translation request updater.
 *
 * This is the service that updates the translation request based on
 * the information received from the CDT.
 *
 * @group batch1
 */
class TranslationRequestUpdaterTest extends TranslationKernelTestBase {

  use CdtTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * The translation request updater.
   */
  protected TranslationRequestUpdaterInterface $updater;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installEntitySchema('oe_translation_request_log');
    $this->installConfig(['oe_translation_remote']);
    $this->installConfig(['oe_translation_cdt']);
    $this->updater = $this->container->get('oe_translation_cdt.translation_request_updater');
  }

  /**
   * Tests updating from the request status callback.
   */
  public function testUpdateFromRequestStatus(): void {
    $request = $this->createTranslationRequest($this->getCommonTranslationRequestData(), NULL, ['fr']);
    $request_status = (new RequestStatus())
      ->setStatus(CdtApiWrapperInterface::STATUS_REQUEST_COMPLETED)
      ->setCorrelationId('12345')
      ->setRequestIdentifier('12345/2024')
      ->setDate(new \DateTime());

    $this->updater->updateFromRequestStatus($request, $request_status);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $this->assertEquals('12345/2024', $request->getCdtId());
    $this->assertTranslationRequestLog($request, [
      'Received CDT callback, updating the request...Updated cdt_id field to 12345/2024.Updated request_status field from Requested to Translated.',
    ]);
  }

  /**
   * Tests updating from the job status callback.
   */
  public function testUpdateFromJobStatus(): void {
    $request = $this->createTranslationRequest($this->getCommonTranslationRequestData(), NULL, ['fr']);
    $valid_job_status = (new JobStatus())
      ->setStatus(CdtApiWrapperInterface::STATUS_JOB_COMPLETED)
      ->setRequestIdentifier('12345/2024')
      ->setSourceLanguageCode('EN')
      ->setTargetLanguageCode('FR')
      ->setSourceDocumentName('source.xml');

    $this->updater->updateFromJobStatus($request, $valid_job_status);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguages()['fr']->getStatus());
    $this->assertTranslationRequestLog($request, [
      'Received CDT callback, updating the job...The following languages are updated: fr (Requested =&gt; Review).',
    ]);
  }

  /**
   * Tests updating from the full translation status.
   */
  public function testUpdateFromTranslationResponse(): void {
    $request = $this->createTranslationRequest($this->getCommonTranslationRequestData(), NULL, ['fr']);
    $user1 = (new ReferenceContact())
      ->setFirstName('John')
      ->setLastName('Smith')
      ->setUserName('TEST1');
    $user2 = (new ReferenceContact())
      ->setFirstName('Jane')
      ->setLastName('Doe')
      ->setUserName('TEST2');
    $reference_data = (new ReferenceData())
      ->setContacts([$user1, $user2])
      ->setDepartments([(new ReferenceItem())->setCode('DEP2')->setDescription('Department 2')]);
    $translation_response = (new Translation())
      ->setStatus(CdtApiWrapperInterface::STATUS_REQUEST_COMPLETED)
      ->setRequestIdentifier('123/2024')
      ->setComments([(new Comment())->setComment('COMMENT2')])
      ->setContacts(['Jane Doe'])
      ->setDeliverToContacts(['John Smith', 'Jane Doe'])
      ->setPhoneNumber('123456789')
      ->setDepartment('Department 2')
      ->setJobSummary([
        (new JobSummary())
          ->setTargetLanguage('FR')
          ->setStatus(CdtApiWrapperInterface::STATUS_JOB_COMPLETED)
          ->setPriorityCode('PRIO2'),
      ]);

    // Do the update of the translation request.
    $this->updater->updateFromTranslationResponse($request, $translation_response, $reference_data);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguages()['fr']->getStatus());
    $this->assertEquals('COMMENT2', $request->getComments());
    $this->assertEquals(['TEST2'], $request->getContactUsernames());
    $this->assertEquals(['TEST1', 'TEST2'], $request->getDeliverTo());
    $this->assertEquals('123456789', $request->getPhoneNumber());
    $this->assertEquals('DEP2', $request->getDepartment());
    $this->assertEquals('PRIO2', $request->getPriority());
    $this->assertTranslationRequestLog($request, [
      'Manually updated the status.' .
      'Updated request_status field from Requested to Translated.' .
      'The following languages are updated: fr (Requested =&gt; Review).' .
      'Updated comments field from COMMENT1 to COMMENT2.' .
      'Updated phone_number field from 999999999 to 123456789.' .
      'Updated priority field from PRIO1 to PRIO2.' .
      'Updated department field from DEP1 to DEP2.' .
      'Updated contact_usernames field from TEST1 to TEST2.' .
      'Updated deliver_to field from TEST2 to TEST1, TEST2.',
    ]);

    // Try the second update with the same data.
    $this->updater->updateFromTranslationResponse($request, $translation_response, $reference_data);
    $this->assertCount(1, $request->getLogMessages(), 'No new log messages should be added.');

    // Update single value.
    $translation_response->setContacts(['John Smith']);
    $this->updater->updateFromTranslationResponse($request, $translation_response, $reference_data);
    $this->assertEquals(['TEST1'], $request->getContactUsernames());
    $this->assertTranslationRequestLog($request, [
      '*',
      'Manually updated the status.' .
      'Updated contact_usernames field from TEST2 to TEST1.',
    ]);

    // Ignore optional language status and request priority.
    $translation_response->setJobSummary([]);
    $this->updater->updateFromTranslationResponse($request, $translation_response, $reference_data);
    $this->assertCount(2, $request->getLogMessages(), 'No new log messages should be added.');

    // Update language status.
    $translation_response->setJobSummary([
      (new JobSummary())
        ->setTargetLanguage('FR')
        ->setStatus(CdtApiWrapperInterface::STATUS_JOB_FAILED)
        ->setPriorityCode('PRIO2'),
    ]);
    $this->updater->updateFromTranslationResponse($request, $translation_response, $reference_data);
    $this->assertEquals(TranslationRequestCdtInterface::STATUS_LANGUAGE_FAILED, $request->getTargetLanguages()['fr']->getStatus());
    $this->assertTranslationRequestLog($request, [
      '*',
      '*',
      'Manually updated the status.' .
      'The following languages are updated: fr (Review =&gt; Failed).',
    ]);
  }

  /**
   * Tests updating the CDT ID only.
   */
  public function testUpdatePermanentId(): void {
    $request = $this->createTranslationRequest($this->getCommonTranslationRequestData(), NULL, ['fr']);
    $this->updater->updatePermanentId($request, '123/2024');
    $this->assertEquals('123/2024', $request->getCdtId());
  }

  /**
   * Asserts the translation request log messages.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request
   *   The translation request.
   * @param array $logs
   *   The expected log messages. There may be wildcards "*" inside.
   */
  protected function assertTranslationRequestLog(TranslationRequestCdtInterface $request, array $logs): void {
    $this->assertSameSize($logs, $request->getLogMessages(), 'The log size doesn\'t match.');
    foreach ($request->getLogMessages() as $index => $log) {
      if ($logs[$index] !== '*') {
        $this->assertEquals($logs[$index], strip_tags($log->getMessage()->__toString()));
      }
    }
  }

}
