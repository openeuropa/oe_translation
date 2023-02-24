<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource as OriginalContentEntitySource;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An override of the default ContentEntitySource plugin class.
 */
class ContentEntitySource extends OriginalContentEntitySource implements ContainerFactoryPluginInterface {

  /**
   * The entity revision info service.
   *
   * @var \Drupal\oe_translation\EntityRevisionInfoInterface
   */
  protected $entityRevisionInfo;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * ContentEntitySource constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\oe_translation\EntityRevisionInfoInterface $entityRevisionInfo
   *   The entity revision info service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityRevisionInfoInterface $entityRevisionInfo, LoggerChannelFactoryInterface $loggerChannelFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityRevisionInfo = $entityRevisionInfo;
    $this->logger = $loggerChannelFactory->get('oe_translation');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_translation.entity_revision_info'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(ContentEntityInterface $entity) {
    $data = parent::extractTranslatableData($entity);

    // Set some information about the entity so others can know what this bit
    // is dealing with in case it's an embedded entity.
    $data['#entity_type'] = $entity->getEntityTypeId();
    $data['#entity_bundle'] = $entity->bundle();

    return $data;
  }

  /**
   * Method used to delegate the saving of the data to the correct logic.
   *
   * We want to avoid using job items as much as possible and pass directly the
   * entity to save the data on.
   *
   * @param array $data
   *   The data to save.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to save on.
   * @param string $target_langcode
   *   The target language.
   *
   * @return bool
   *   Whether it was successful.
   */
  public function saveTranslationData(array $data, ContentEntityInterface $entity, string $target_langcode): bool {
    // Fake a job item cause apparently TMGMT needs it for no reason.
    $job_item = JobItem::create([]);

    // Use the entity revision info service to resolve the correct revision
    // onto which to save the translation.
    $entity = $this->entityRevisionInfo->getEntityRevision($entity, $target_langcode);

    try {
      $this->doSaveTranslations($entity, $data, $target_langcode, $job_item);
      return TRUE;
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return FALSE;
    }
  }

}
