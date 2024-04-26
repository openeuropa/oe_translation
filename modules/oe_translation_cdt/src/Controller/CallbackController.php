<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_cdt\TranslationRequestUpdaterInterface;
use OpenEuropa\CdtClient\Serializer\CallbackSerializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles endpoints for CDT callbacks.
 */
class CallbackController extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestUpdaterInterface $updater
   *   The translation request updater.
   */
  public function __construct(
    private readonly TranslationRequestUpdaterInterface $updater,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('oe_translation_cdt.translation_request_updater'),
    );
  }

  /**
   * Verifies if the API key matches the one from module settings.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   TRUE if the API key is valid, FALSE otherwise.
   */
  private function isApiKeyValid(Request $request): bool {
    $request_api_key = $request->headers->get('apikey');
    $site_api_key = Settings::get('cdt.api_key');
    return $request_api_key === $site_api_key;
  }

  /**
   * Loads translation request.
   *
   * Load the translation request by request ID. If it does not exist,
   * try to load it by correlation ID.
   *
   * @param string $cdt_id
   *   The request ID.
   * @param string|null $correlation_id
   *   The correlation ID (optional).
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface|null
   *   The translation request or NULL if it doesn't exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadTranslationRequest(string $cdt_id, ?string $correlation_id = NULL): ?TranslationRequestCdtInterface {
    $request_storage = $this->entityTypeManager()->getStorage('oe_translation_request');
    $translation_requests = $request_storage->loadByProperties([
      'cdt_id' => $cdt_id,
    ]);
    if (count($translation_requests) === 0 && !is_null($correlation_id)) {
      $translation_requests = $request_storage->loadByProperties([
        'correlation_id' => $correlation_id,
      ]);
    }

    if ($request = reset($translation_requests)) {
      assert($request instanceof TranslationRequestCdtInterface);
      return $request;
    }

    return NULL;
  }

  /**
   * Request status callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function requestStatus(Request $request): Response {
    if (!$this->isApiKeyValid($request)) {
      throw new AccessDeniedHttpException('Invalid API key');
    }
    $request_status = CallbackSerializer::deserializeRequestStatus($request->getContent());
    $translation_request = $this->loadTranslationRequest($request_status->getRequestIdentifier(), $request_status->getCorrelationId());
    if (!$translation_request) {
      throw new NotFoundHttpException('Translation request not found');
    }
    $this->updater->updateFromRequestStatus($translation_request, $request_status);

    return new Response();
  }

  /**
   * Job status callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function jobStatus(Request $request): Response {
    if (!$this->isApiKeyValid($request)) {
      throw new AccessDeniedHttpException('Invalid API key');
    }
    $job_status = CallbackSerializer::deserializeJobStatus($request->getContent());
    $translation_request = $this->loadTranslationRequest($job_status->getRequestIdentifier());
    if (!$translation_request) {
      throw new NotFoundHttpException('Translation request not found');
    }
    $this->updater->updateFromJobStatus($translation_request, $job_status);

    return new Response();
  }

}
