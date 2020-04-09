<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource as OriginalContentEntitySource;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An override of the default ContentEntitySource plugin class.
 */
class ContentEntitySource extends OriginalContentEntitySource implements ContainerFactoryPluginInterface {

  /**
   * The content entity source translation info service.
   *
   * @var \Drupal\oe_translation\ContentEntitySourceTranslationInfo
   */
  protected $contentEntitySourceTranslationInfo;

  /**
   * ContentEntitySource constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\oe_translation\ContentEntitySourceTranslationInfo $contentEntitySourceTranslationInfo
   *   The content entity source translation info service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, ContentEntitySourceTranslationInfo $contentEntitySourceTranslationInfo) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->contentEntitySourceTranslationInfo = $contentEntitySourceTranslationInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_translation.content_entity_source_translation_info')
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
   * {@inheritdoc}
   *
   * Overriding the way the the entity is retrieved to allow others to determine
   * which revision of the entity to work with for displaying data and saving.
   */
  protected function getEntity(JobItemInterface $job_item) {
    return $this->contentEntitySourceTranslationInfo->getEntityFromJobItem($job_item);
  }

}
