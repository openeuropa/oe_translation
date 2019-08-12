<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\State;
use Drupal\tmgmt\TranslatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory class for the Poetry service.
 */
class PoetryFactory {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * PoetryFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The logger channel.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\State\State $state
   *   The Drupal state.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, LoggerInterface $logger, State $state) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->loggerChannel = $loggerChannel;
    $this->logger = $logger;
    $this->state = $state;
  }

  /**
   * Returns the Poetry instance.
   *
   * @param string $translator
   *   The translator config ID.
   *
   * @return \Drupal\oe_translation_poetry\Poetry
   */
  public function get(string $translator): Poetry {
    $entity = $this->entityTypeManager->getStorage('tmgmt_translator')->load($translator);
    $settings = [];
    if ($entity instanceof TranslatorInterface) {
      $settings = $entity->get('settings');
    }

    return new Poetry($settings, $this->configFactory, $this->loggerChannel, $this->logger, $this->state, $this->entityTypeManager);
  }

}
