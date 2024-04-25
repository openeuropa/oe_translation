<?php

declare(strict_types=1);

use Drupal\Core\Site\Settings;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_cdt\Controller\CallbackController;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the callback controller.
 *
 * @group batch1
 */
class CallbackControllerTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
  ];

  /**
   * The callback controller.
   *
   * @var \Drupal\oe_translation_cdt\Controller\CallbackController
   */
  protected CallbackController $controller;

  /**
   * Reloads the translation request and clears static cache.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface
   *   The reloaded translation request.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function reloadTranslationRequest(TranslationRequestCdtInterface $translation_request): TranslationRequestCdtInterface {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $storage = $entityTypeManager->getStorage('oe_translation_request');
    $storage->resetCache([$translation_request->id()]);
    $reloaded_request = $storage->load($translation_request->id());
    assert($reloaded_request instanceof TranslationRequestCdtInterface);
    return $reloaded_request;
  }

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
    $this->controller = new CallbackController();
    new Settings([
      'cdt.api_key' => '12345',
    ]);
  }

  /**
   * Tests the endpoints for empty api key, when it is set in settings.
   */
  public function testEmptyApiKey(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $request = new Request();
    $response_request_status = $this->controller->requestStatus($request);
    $this->assertEquals(403, $response_request_status->getStatusCode(), 'The empty api key passes the validation.');
    $response_job_status = $this->controller->jobStatus($request);
    $this->assertEquals(403, $response_job_status->getStatusCode(), 'The empty api key passes the validation.');
  }

  /**
   * Tests the endpoints for invalid api key.
   */
  public function testInvalidApiKey(): void {
    $this->expectException(AccessDeniedHttpException::class);
    $request = new Request();
    $request->headers->set('apikey', 'some_invalid_key');
    $response_request_status = $this->controller->requestStatus($request);
    $this->assertEquals(403, $response_request_status->getStatusCode(), 'The invalid api key passes the validation.');
    $response_job_status = $this->controller->jobStatus($request);
    $this->assertEquals(403, $response_job_status->getStatusCode(), 'The invalid api key passes the validation.');
  }

  /**
   * Tests the "request status" callback.
   */
  public function testRequestStatusCallback(): void {
    // Test the request based on permanent ID.
    $request_with_id = new Request(
      [], [], [], [], [], [], (string) json_encode([
        'RequestIdentifier' => '2024/12345a',
        'Status' => 'COMP',
        'Date' => '2024-02-28T12:03:03.6239422',
        'CorrelationId' => 'aaa',
      ])
    );
    $request_with_id->headers->set('apikey', '12345');
    $translation_request_with_id = TranslationRequest::create([
      'bundle' => 'cdt',
      'cdt_id' => '2024/12345a',
      'request_status' => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    ]);
    assert($translation_request_with_id instanceof TranslationRequestCdtInterface);
    $translation_request_with_id->updateTargetLanguageStatus('es', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $translation_request_with_id->save();
    $response = $this->controller->requestStatus($request_with_id);
    $this->assertEquals(200, $response->getStatusCode(), 'The response code is not 200.');
    $translation_request_with_id = $this->reloadTranslationRequest($translation_request_with_id);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $translation_request_with_id->getRequestStatus(), 'The request status (with id) was not changed.');

    // Test the request based on correlation ID.
    $request_without_id = new Request(
      [], [], [], [], [], [], (string) json_encode([
        'RequestIdentifier' => '2024/12345b',
        'Status' => 'COMP',
        'Date' => '2024-02-28T12:03:03.6239422',
        'CorrelationId' => 'bbb',
      ])
    );
    $request_without_id->headers->set('apikey', '12345');
    $translation_request_without_id = TranslationRequest::create([
      'bundle' => 'cdt',
      'cdt_status' => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
      'correlation_id' => 'bbb',
    ]);
    assert($translation_request_without_id instanceof TranslationRequestCdtInterface);
    $translation_request_without_id->updateTargetLanguageStatus('es', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $translation_request_without_id->save();

    $response = $this->controller->requestStatus($request_without_id);
    $this->assertEquals(200, $response->getStatusCode(), 'The response code is not 200.');
    $translation_request_without_id = $this->reloadTranslationRequest($translation_request_without_id);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $translation_request_without_id->getRequestStatus(), 'The request status (without id) was not changed.');
    $this->assertEquals('2024/12345b', $translation_request_without_id->getCdtId(), 'The CDT ID was not set.');

    // Test the invalid request.
    $request_404 = new Request(
      [], [], [], [], [], [], (string) json_encode([
        'RequestIdentifier' => 'non_existing_cdt_id',
        'Status' => 'COMP',
        'Date' => '2024-02-28T12:03:03.6239422',
        'CorrelationId' => 'non_existing_correlation_id',
      ])
    );
    $request_404->headers->set('apikey', '12345');
    $this->expectException(NotFoundHttpException::class);
    $response = $this->controller->requestStatus($request_404);
    $this->assertEquals(404, $response->getStatusCode(), 'The response code is not 404.');
  }

  /**
   * Tests the "job status" callback.
   */
  public function testJobStatusCallback(): void {
    $request = new Request(
      [], [], [], [], [], [], (string) json_encode([
        'RequestIdentifier' => '2024/12345a',
        'Status' => 'CMP',
        'SourceDocumentName' => 'text.xml',
        'SourceLanguageCode' => 'EN',
        'TargetLanguageCode' => 'ES',
      ])
    );
    $request->headers->set('apikey', '12345');
    $translation_request = TranslationRequest::create([
      'bundle' => 'cdt',
      'cdt_id' => '2024/12345a',
    ]);
    assert($translation_request instanceof TranslationRequestCdtInterface);
    $translation_request->updateTargetLanguageStatus('es', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $translation_request->updateTargetLanguageStatus('fr', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $translation_request->save();
    $response = $this->controller->jobStatus($request);
    $this->assertEquals(200, $response->getStatusCode(), 'The response code is not 200.');
    $translation_request = $this->reloadTranslationRequest($translation_request);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $translation_request->getTargetLanguage('es')?->getStatus(), 'The language status was not changed.');
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $translation_request->getTargetLanguage('fr')?->getStatus(), 'The language status was not changed.');
  }

}
