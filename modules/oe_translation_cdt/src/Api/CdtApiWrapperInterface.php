<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Api;

use OpenEuropa\CdtClient\Contract\ApiClientInterface;

/**
 * The interface for CDT API client wrapper.
 */
interface CdtApiWrapperInterface {

  const STATUS_JOB_COMPLETED = 'CMP';
  const STATUS_JOB_FAILED = 'FLR';
  const STATUS_JOB_IN_PROGRESS = 'INP';
  const STATUS_JOB_CANCELLED = 'CNC';
  const STATUS_JOB_TO_BE_CANCELLED = 'TCN';

  const STATUS_REQUEST_COMPLETED = 'COMP';
  const STATUS_REQUEST_IN_PROGRESS = 'INPR';
  const STATUS_REQUEST_CANCELLED = 'CANC';
  const STATUS_REQUEST_PENDING_APPROVAL = 'PEND';
  const STATUS_REQUEST_UNDER_QUOTATION = 'UNDE';

  /**
   * Gets and authenticates the client.
   *
   * @return \OpenEuropa\CdtClient\Contract\ApiClientInterface
   *   The CDT API client.
   *
   * @throws \Drupal\oe_translation_cdt\Exception\CdtConnectionException
   * @throws \OpenEuropa\CdtClient\Exception\InvalidStatusCodeException
   */
  public function getClient(): ApiClientInterface;

  /**
   * Resets the authentication and forces a new token retrieval.
   */
  public function resetAuthentication(): void;

}
