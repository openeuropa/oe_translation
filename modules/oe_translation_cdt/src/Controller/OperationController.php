<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\oe_translation_cdt\Api\CdtApiWrapperInterface;
use Drupal\oe_translation_cdt\ContentFormatter\ContentFormatterInterface;
use Drupal\oe_translation_cdt\Mapper\LanguageCodeMapper;
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
   * @param \Drupal\oe_translation_cdt\ContentFormatter\ContentFormatterInterface $xmlFormatter
   *   The XML formatter.
   */
  public function __construct(
    private readonly CdtApiWrapperInterface $apiWrapper,
    private readonly TranslationRequestUpdaterInterface $updater,
    private readonly ContentFormatterInterface $xmlFormatter
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('oe_translation_cdt.api_wrapper'),
      $container->get('oe_translation_cdt.translation_request_updater'),
      $container->get('oe_translation_cdt.xml_formatter')
    );
  }

  /**
   * Refreshes the request status.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @throws \Drupal\oe_translation_cdt\Exception\CdtConnectionException
   */
  public function refreshStatus(TranslationRequestCdtInterface $translation_request, Request $request): RedirectResponse {
    $translation_response = $this->apiWrapper->getClient()->getRequestStatus((string) $translation_request->getCdtId());
    $reference_data = $this->apiWrapper->getClient()->getReferenceData();
    if ($this->updater->updateFromTranslationResponse($translation_request, $translation_response, $reference_data)) {
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
   * Fetches the translation.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   * @param string $langcode
   *   The language code.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\oe_translation_cdt\Exception\CdtConnectionException
   */
  public function fetchTranslation(TranslationRequestCdtInterface $translation_request, string $langcode, Request $request): RedirectResponse {
    $translation_response = $this->apiWrapper->getClient()->getRequestStatus((string) $translation_request->getCdtId());
    foreach ($translation_response->getTargetFiles() as $file) {
      if (LanguageCodeMapper::getDrupalLanguageCode((string) $file->getTargetLanguage(), $translation_request) == $langcode) {
        $xml = $this->apiWrapper->getClient()->downloadFile($file->getLinks()['files']->getHref());
        $data = $this->xmlFormatter->import($xml, $translation_request);
        $translation_request->setTranslatedData($langcode, $data);
        $translation_request->save();
        break;
      }
    }

    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }
    return new RedirectResponse((string) $destination);
  }

  /**
   * Gets the permanent ID.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @throws \Drupal\oe_translation_cdt\Exception\CdtConnectionException
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
