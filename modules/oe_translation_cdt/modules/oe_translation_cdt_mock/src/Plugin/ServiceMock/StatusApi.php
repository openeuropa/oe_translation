<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use Drupal\oe_translation_cdt\Mapper\LanguageCodeMapper;
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
 *   weight = -1,
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
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    if (!$this->hasToken($request)) {
      return new Response(401, [], $this->getResponseFromFile('general_response_401.json'));
    }

    // The entity uses the same identifier as the mocked CDT request.
    $parameters = $this->getRequestParameters($request);
    $response = json_decode($this->getResponseFromFile('status_response_200.json'), TRUE);
    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface|null $entity */
    $entity = $this->entityTypeManager->getStorage('translation_request')->load($parameters[':requestnumber']);
    if (!$entity) {
      return new Response(400, [], $this->getResponseFromFile('status_response_400.json'));
    }

    // Change only the important parameters.
    $response['requestIdentifier'] = $entity->getCdtId();
    $response['status'] = match($entity->getRequestStatus()) {
      TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED => 'COMP',
      TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED => 'CANC',
      TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED => 'INPR',
      default => 'UNDE',
    };

    // Add source language and translated files.
    $cdt_source_language = LanguageCodeMapper::getCdtLanguageCode($entity->getSourceLanguageCode(), $entity);
    $response['sourceLanguage'] = $cdt_source_language;
    $response['targetFiles'] = [];
    $response['targetLanguages'] = [];
    foreach ($entity->getTargetLanguages() as $language) {
      $cdt_target_language = LanguageCodeMapper::getCdtLanguageCode($language->getLangcode(), $entity);
      $response['targetLanguages'][] = $cdt_target_language;
      if ($language->getStatus() != TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED) {
        $response['targetFiles'][] = [
          'sourceLanguage' => $cdt_source_language,
          'targetLanguage' => $cdt_target_language,
          'sourceDocument' => 'test.xml',
          'fileName' => 'test.xml',
          'isPrivate' => FALSE,
          '_links' => [
            'files' => [
              'href' => sprintf('https://example.com/api/files/%s/%s', $cdt_target_language, $entity->getCdtId()),
              'method' => 'GET',
            ],
          ],
        ];
      }
    }

    return new Response(200, [], (string) json_encode($response));
  }

}
