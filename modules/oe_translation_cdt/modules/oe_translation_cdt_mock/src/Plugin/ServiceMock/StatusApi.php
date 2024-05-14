<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use Drupal\oe_translation_cdt\Api\CdtApiWrapperInterface;
use Drupal\oe_translation_cdt\Mapper\LanguageCodeMapper;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts status request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_status_api",
 *   label = @Translation("CDT mocked status responses for testing."),
 *   weight = 0,
 * )
 */
class StatusApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.status_api_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointResponse(RequestInterface $request): ResponseInterface {
    // The entity uses the same identifier as the mocked CDT request.
    $parameters = $this->getRequestParameters($request);
    $response = json_decode($this->getResponseFromFile('status_response_200.json'), TRUE);
    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface|null $entity */
    $entity = $this->entityTypeManager->getStorage('oe_translation_request')->load($parameters['requestnumber']);
    if (!$entity) {
      $this->log('400: Failed to get the mock status. The translation request does not exist.', $request);
      return new Response(400, [], $this->getResponseFromFile('status_response_400.json'));
    }

    // Change only the important parameters.
    $response['status'] = match($entity->getRequestStatus()) {
      TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED => CdtApiWrapperInterface::STATUS_REQUEST_COMPLETED,
      TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED => CdtApiWrapperInterface::STATUS_REQUEST_CANCELLED,
      TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED => CdtApiWrapperInterface::STATUS_REQUEST_IN_PROGRESS,
      default => CdtApiWrapperInterface::STATUS_REQUEST_PENDING_APPROVAL,
    };
    $response['comments'] = [];
    if ($entity->getComments()) {
      $response['comments'][] = [
        'comment' => (string) $entity->getComments(),
        'isHTML' => FALSE,
        'from' => 'Client',
      ];
    }
    $response['phoneNumber'] = $entity->getPhoneNumber();
    $response['department'] = match($entity->getDepartment()) {
      '123' => 'Department 1',
      default => 'Department 2',
    };

    // Update the contact lists.
    $response['contacts'] = [];
    foreach ($entity->getContactUsernames() as $contact) {
      $response['contacts'][] = match($contact) {
        'TESTUSER1' => 'John Smith',
        default => 'Jane Doe',
      };
    }
    $response['deliverToContacts'] = [];
    foreach ($entity->getDeliverTo() as $contact) {
      $response['deliverToContacts'][] = match($contact) {
        'TESTUSER1' => 'John Smith',
        default => 'Jane Doe',
      };
    }

    // Add source language, jobs, and translated files.
    $cdt_source_language = LanguageCodeMapper::getCdtLanguageCode($entity->getSourceLanguageCode(), $entity);
    $response['sourceLanguage'] = $cdt_source_language;
    $response['targetFiles'] = [];
    $response['targetLanguages'] = [];
    $default_job_summary = $response['pricing']['jobSummary'][0];
    $response['pricing']['jobSummary'] = [];
    foreach ($entity->getTargetLanguages() as $language) {
      $cdt_target_language = LanguageCodeMapper::getCdtLanguageCode($language->getLangcode(), $entity);
      $response['targetLanguages'][] = $cdt_target_language;

      $response['pricing']['jobSummary'][] = [
        'targetLanguage' => $cdt_target_language,
        'fileName' => 'translation_job_5_request.xml',
        'priorityCode' => $entity->getPriority(),
        'status' => match($language->getStatus()) {
            TranslationRequestCdtInterface::STATUS_LANGUAGE_FAILED => CdtApiWrapperInterface::STATUS_JOB_FAILED,
            TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED => CdtApiWrapperInterface::STATUS_JOB_CANCELLED,
            TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED, TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED => CdtApiWrapperInterface::STATUS_JOB_COMPLETED,
            default => CdtApiWrapperInterface::STATUS_JOB_IN_PROGRESS
        },
      ] + $default_job_summary;

      if ($language->getStatus() != TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED) {
        $response['targetFiles'][] = [
          'sourceLanguage' => $cdt_source_language,
          'targetLanguage' => $cdt_target_language,
          'sourceDocument' => 'test.xml',
          'fileName' => 'test.xml',
          'isPrivate' => FALSE,
          '_links' => [
            'files' => [
              'href' => sprintf('https://example.com/api/files/%s/%s', $cdt_target_language, $entity->id()),
              'method' => 'GET',
            ],
          ],
        ];
      }
    }

    $this->log('200: Getting the mocked status.', $request);
    return new Response(200, [], (string) json_encode($response));
  }

}
