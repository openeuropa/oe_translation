<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Saves the etrans onto the translation request.
 *
 * We handle this via a queue because DGT can send many etranslations in quick
 * sequence or at the same time which means we may have issue saving them all
 * on the translation request at the same time. So we queue that action.
 *
 * @QueueWorker(
 *   id = "oe_translation_etrans_delivery",
 *   title = @Translation("Etrans delivery processor"),
 *   cron = {"time" = 30}
 * )
 */
class EtransDeliveryProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EtransDeliveryProcessor instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $translation_request = $this->entityTypeManager->getStorage('oe_translation_request')->load($data['translation_request_id']);
    if (!$translation_request instanceof TranslationRequestEtransInterface) {
      // We don't do anything in this case.
      return;
    }
    $translation_request->setTranslatedData($data['language'], $data['data']);
    $translation_request->updateTargetLanguageStatus($data['language'], TranslationRequestEtransInterface::STATUS_LANGUAGE_REVIEW);
    $translation_request->log('The <strong>@language</strong> translation has been delivered.', ['@language' => ConfigurableLanguage::load($data['language'])->getName()]);
    $translation_request->save();
  }

}
