<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Http\Adapter\Guzzle7\Client;
use Http\Discovery\Psr17Factory;
use OpenEuropa\EPoetry\TicketValidation\EuLogin\EuLoginTicketValidation;

/**
 * Validation service for the notifications requests that come from ePoetry.
 */
class NotificationTicketValidation extends EuLoginTicketValidation {

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $guzzle, LoggerChannelFactoryInterface $logger_channel_factory) {
    $callback_url = NotificationEndpointResolver::resolve();
    $http_client = new Client($guzzle);
    parent::__construct($callback_url, static::getEuLoginBasePath(), static::getEuLoginJobAccount(), new Psr17Factory(), $http_client, $logger_channel_factory->get('oe_translation_epoetry'));
  }

  /**
   * Returns the EULogin base path used for the ticket validation.
   *
   * @return string|null
   *   The base path.
   */
  public static function getEuLoginBasePath(): ?string {
    return Settings::get('epoetry.ticket_validation.eulogin_base_path') ? Settings::get('epoetry.ticket_validation.eulogin_base_path') : '';
  }

  /**
   * Returns the EULogin job account used for the ticket validation.
   *
   * @return string|null
   *   The base path.
   */
  public static function getEuLoginJobAccount(): ?string {
    return Settings::get('epoetry.ticket_validation.eulogin_job_account') ? Settings::get('epoetry.ticket_validation.eulogin_job_account') : '';
  }

}
