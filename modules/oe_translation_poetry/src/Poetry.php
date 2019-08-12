<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\tmgmt\TranslatorManager;
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
   * @param \Drupal\tmgmt\TranslatorManager $translatorManager
   *   The translator plugin manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, LoggerInterface $logger, TranslatorManager $translatorManager) {
    $loggerChannel->addLogger($logger);
    $poetry_translator_definition = $translatorManager->getDefinition('poetry');
    $values = [
      'identifier.code' => $poetry_translator_definition['default_settings']['identifier_code'],
      'identifier.sequence' => Settings::get('poetry.identifier.sequence'),
      'identifier.year' => date('Y'),
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
