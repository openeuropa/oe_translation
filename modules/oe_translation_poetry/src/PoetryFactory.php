<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\tmgmt\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Factory class for the Poetry service.
 */
class PoetryFactory {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * PoetryFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\State\State $state
   *   The Drupal state.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $loggerFactory, State $state, Connection $database, RequestStack $requestStack) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->loggerFactory = $loggerFactory;
    $this->state = $state;
    $this->database = $database;
    $this->requestStack = $requestStack;
  }

  /**
   * Returns the Poetry instance.
   *
   * @param string $translator
   *   The translator config ID.
   *
   * @return \Drupal\oe_translation_poetry\Poetry
   *   The poetry service.
   */
  public function get(string $translator): Poetry {
    $entity = $this->entityTypeManager->getStorage('tmgmt_translator')->load($translator);
    $settings = [];
    if ($entity instanceof TranslatorInterface) {
      $settings = $entity->get('settings');
    }

    return new Poetry($settings, $this->configFactory, $this->loggerFactory, $this->state, $this->entityTypeManager, $this->database, $this->requestStack);
  }

}
