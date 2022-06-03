<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\tmgmt\SourceManager;

/**
 * Manages the reading and writing of the translation data from/to entities.
 *
 * Acts as a bridge between us and TMGMT.
 */
class TranslationSourceManager implements TranslationSourceManagerInterface {

  /**
   * The TMGMT source plugin manager.
   *
   * @var \Drupal\tmgmt\SourceManager
   */
  protected $sourceManager;

  /**
   * Constructs a new TranslationSourceManager.
   *
   * @param \Drupal\tmgmt\SourceManager $sourceManager
   *   The TMGMT source plugin manager.
   */
  public function __construct(SourceManager $sourceManager) {
    $this->sourceManager = $sourceManager;
  }

  /**
   * {@inheritdoc}
   */
  public function extractData(ContentEntityInterface $entity): array {
    /** @var \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource $plugin */
    $plugin = $this->sourceManager->createInstance('content');
    return $plugin->extractTranslatableData($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function saveData(array $data, ContentEntityInterface $entity, string $langcode): bool {
    /** @var \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource $plugin */
    $plugin = $this->sourceManager->createInstance('content');
    return $plugin->saveTranslationData($data, $entity, $langcode);
  }

}
