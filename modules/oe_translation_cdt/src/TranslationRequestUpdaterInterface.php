<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt;

use OpenEuropa\CdtClient\Model\Callback\JobStatus;
use OpenEuropa\CdtClient\Model\Callback\RequestStatus;
use OpenEuropa\CdtClient\Model\Response\ReferenceData;
use OpenEuropa\CdtClient\Model\Response\Translation;

/**
 * The interface for the TranslationRequest entity updater.
 */
interface TranslationRequestUpdaterInterface {

  /**
   * Updates the request from the RequestStatus callback DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param \OpenEuropa\CdtClient\Model\Callback\RequestStatus $request_status
   *   The callback DTO.
   *
   * @return bool
   *   TRUE if any changes were made, FALSE otherwise.
   */
  public function updateFromRequestStatus(TranslationRequestCdtInterface $translation_request, RequestStatus $request_status): bool;

  /**
   * Updates the request from the JobStatus callback DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param \OpenEuropa\CdtClient\Model\Callback\JobStatus $job_status
   *   The callback DTO.
   *
   * @return bool
   *   TRUE if any changes were made, FALSE otherwise.
   */
  public function updateFromJobStatus(TranslationRequestCdtInterface $translation_request, JobStatus $job_status): bool;

  /**
   * Updates the request from the Translation response DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param \OpenEuropa\CdtClient\Model\Response\Translation $translation_response
   *   The translation DTO.
   * @param \OpenEuropa\CdtClient\Model\Response\ReferenceData $reference_data
   *   The reference data DTO.
   *
   * @return bool
   *   TRUE if any changes were made, FALSE otherwise.
   */
  public function updateFromTranslationResponse(TranslationRequestCdtInterface $translation_request, Translation $translation_response, ReferenceData $reference_data): bool;

  /**
   * Updates the permanent ID of the request.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param string $permanent_id
   *   The permanent ID.
   *
   * @return bool
   *   TRUE if any changes were made, FALSE otherwise.
   */
  public function updatePermanentId(TranslationRequestCdtInterface $translation_request, string $permanent_id): bool;

}
