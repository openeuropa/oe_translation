<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\oe_translation_cdt\Api\CdtApiWrapperInterface;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_cdt\TranslationRequestUpdaterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for OpenEuropa Translation CDT routes.
 */
final class OperationController extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\oe_translation_cdt\Api\CdtApiWrapperInterface $apiWrapper
   *   The CDT API wrapper.
   * @param \Drupal\oe_translation_cdt\TranslationRequestUpdaterInterface $updater
   *   The translation request updater.
   */
  public function __construct(
    private readonly CdtApiWrapperInterface $apiWrapper,
    private readonly TranslationRequestUpdaterInterface $updater
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('oe_translation_cdt.api_wrapper'),
      $container->get('oe_translation_cdt.translation_request_updater'),
    );
  }

  /**
   * Refresh the request status.
   */
  public function refreshStatus(TranslationRequestCdtInterface $translation_request, Request $request): RedirectResponse {
    $translation_response = $this->apiWrapper->getClient()->getRequestStatus((string) $translation_request->getCdtId());
    if ($this->updater->updateFromTranslationResponse($translation_request, $translation_response)) {
      $this->messenger()->addStatus($this->t('The request status has been updated.'));
    }
    else {
      $this->messenger()->addStatus($this->t('The request status did not change.'));
    }

    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }

    return new RedirectResponse((string) $destination);
  }

  /**
   * Get permanent ID.
   */
  public function getPermanentId(TranslationRequestCdtInterface $translation_request, Request $request): RedirectResponse {
    $permanent_id = $this->apiWrapper->getClient()->getPermanentIdentifier($translation_request->getCorrelationId());
    if ($permanent_id) {
      if ($this->updater->updatePermanentId($translation_request, $permanent_id)) {
        $this->messenger()->addStatus($this->t('The permanent ID has been updated.'));
      }
      else {
        $this->messenger()->addStatus($this->t('The permanent ID did not change.'));
      }
    }
    else {
      $this->messenger()->addStatus($this->t('The permanent ID is not available yet.'));
    }
    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }

    return new RedirectResponse((string) $destination);
  }

}
