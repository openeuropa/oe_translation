<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use EC\Poetry\Poetry as PoetryLibrary;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Poetry client.
 */
class Poetry extends PoetryLibrary {

  /**
   * Poetry constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The logger channel.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, LoggerInterface $logger) {
    $loggerChannel->addLogger($logger);
    $values = [
      'identifier.code' => Settings::get('poetry.identifier.code'),
      'identifier.sequence' => Settings::get('poetry.identifier.sequence'),
      'identifier.year' => date('Y'),
      'service.wsdl' => $configFactory->get('oe_translation_poetry.settings')->get('service_wsdl'),
      'service.username' => Settings::get('poetry.service.username'),
      'service.password' => Settings::get('poetry.service.password'),
      'notification.username' => Settings::get('poetry.notification.username'),
      'notification.password' => Settings::get('poetry.notification.password'),
      'logger' => $loggerChannel,
      'log_level' => LogLevel::INFO,
    ];

    parent::__construct($values);
  }

}
