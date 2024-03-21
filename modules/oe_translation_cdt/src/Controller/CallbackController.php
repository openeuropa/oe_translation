<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use OpenEuropa\CdtClient\Serializer\CallbackSerializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles CDT callbacks.
 */
final class CallbackController extends ControllerBase {

  public function isApiKeyValid(Request $request): bool {
    $request_api_key = $request->headers->get('apikey');
    $site_api_key = Settings::get('cdt.api_key');
    return $request_api_key === $site_api_key;
  }

  /**
   * Request status callback.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function requestStatus(Request $request): Response {
    if (!$this->isApiKeyValid($request)) {
      throw new AccessDeniedHttpException('Invalid API key');
    }
    $request_status = CallbackSerializer::deserializeRequestStatus($request->getContent());
    // Process the $request_status...

    return new Response();
  }

  /**
   * Job status callback.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function jobStatus(Request $request): Response {
    if (!$this->isApiKeyValid($request)) {
      throw new AccessDeniedHttpException('Invalid API key');
    }
    $job_status = CallbackSerializer::deserializeJobStatus($request->getContent());
    // Process the $job_status...

    return new Response();
  }

}
