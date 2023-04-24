<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\ParamConverter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;

/**
 * Helper for performing the param conversion based on the active revision.
 */
trait ActiveRevisionConverterTrait {

  /**
   * Performs the conversion.
   */
  public function doConvert($value, $definition, $name, array $defaults) {
    $contexts_repository = \Drupal::service('context.repository');
    $contexts = $contexts_repository->getAvailableContexts();
    $current_langcode = $this->getContentLanguageFromContexts($contexts);

    $node = $this->getNodeFromDefaults($defaults, $definition);

    $language_active_revision = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getLangcodeMappedRevision($current_langcode, $node, new CacheableMetadata());
    if (!$language_active_revision) {
      return parent::convert($value, $definition, $name, $defaults);
    }

    if ($language_active_revision->getUntranslated()->language()->getId() === $current_langcode) {
      // If we are on the untranslated version, we defer back to the parent.
      return parent::convert($value, $definition, $name, $defaults);
    }

    return $this->entityRepository->getTranslationFromContext($language_active_revision);
  }

  /**
   * Loads the node from the storage based on the route defaults.
   *
   * @param array $defaults
   *   The route defaults.
   * @param array $definition
   *   The param converter definition.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function getNodeFromDefaults(array $defaults, array $definition): NodeInterface {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    if ($definition['type'] === 'entity_revision:node') {
      return $node_storage->loadRevision($defaults['node_revision']);
    }
    if (!empty($definition['load_latest_revision'])) {
      // In case we are instructed to load the latest revision, such as if we
      // are on the /latest page of a given node, we need to load the latest
      // revision.
      return $node_storage->loadRevision($node_storage->getLatestRevisionId($defaults['node']));
    }

    // Otherwise, just load the default node.
    return $node_storage->load($defaults['node']);
  }

  /**
   * Retrieves the current content language from the specified contexts.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   An array of context items.
   *
   * @return string|null
   *   A language code or NULL if no language context was provided.
   */
  protected function getContentLanguageFromContexts(array $contexts) {
    // Content language might not be configurable, in which case we need to fall
    // back to a configurable language type.
    $language_types = [
      LanguageInterface::TYPE_CONTENT,
      LanguageInterface::TYPE_INTERFACE,
    ];
    foreach ($language_types as $language_type) {
      $context_id = '@language.current_language_context:' . $language_type;
      if (isset($contexts[$context_id])) {
        return $contexts[$context_id]->getContextValue()->getId();
      }
    }
    return $this->languageManager->getDefaultLanguage()->getId();
  }

}
