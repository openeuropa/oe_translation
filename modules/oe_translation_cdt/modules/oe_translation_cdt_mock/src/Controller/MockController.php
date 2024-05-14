<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\oe_translation_cdt\Mapper\LanguageCodeMapper;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_cdt\TranslationRequestUpdaterInterface;
use OpenEuropa\CdtClient\Model\Callback\JobStatus;
use OpenEuropa\CdtClient\Model\Callback\RequestStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for OpenEuropa Translation CDT mock routes.
 */
final class MockController extends ControllerBase {

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
   * Changes the translation job status by mocking.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   * @param string $langcode
   *   The language code.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function changeJobStatus(TranslationRequestCdtInterface $translation_request, string $langcode, Request $request): Response {
    $job_status = new JobStatus();
    $job_status->setTargetLanguageCode(LanguageCodeMapper::getCdtLanguageCode($langcode, $translation_request));
    $job_status->setStatus((string) $request->query->get('status'));
    $job_status->setRequestIdentifier((string) $translation_request->getCdtId());
    $this->updater->updateFromJobStatus($translation_request, $job_status);

    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }
    return new RedirectResponse((string) $destination);
  }

  /**
   * Changes the translation request status by mocking.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function changeRequestStatus(TranslationRequestCdtInterface $translation_request, Request $request): Response {
    $request_status = new RequestStatus();
    $request_status->setRequestIdentifier((string) $translation_request->getCdtId());
    $request_status->setStatus((string) $request->query->get('status'));
    $this->updater->updateFromRequestStatus($translation_request, $request_status);

    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }
    return new RedirectResponse((string) $destination);
  }

}
