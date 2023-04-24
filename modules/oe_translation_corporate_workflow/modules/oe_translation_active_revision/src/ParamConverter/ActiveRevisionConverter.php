<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\ParamConverter;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Symfony\Component\Routing\Route;

/**
 * Param converter for the active revision on the node canonical page.
 *
 * We need this because the entity view controller, after building the entity
 * render array and all the view and build hooks have passed, it just
 * replaces back the rendered entity with the initial one. Because normally,
 * for other view modes, we use
 * oe_translation_active_revision_entity_build_defaults_alter() to switch out
 * the entity revision.
 *
 * @see EntityViewController::view()
 */
class ActiveRevisionConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($definition['type']) && strpos($definition['type'], 'entity:') === 0) {
      $entity_type_id = substr($definition['type'], strlen('entity:'));
      return $entity_type_id === 'node';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $route_name = $defaults['_route'] ?? NULL;
    if ($route_name !== 'entity.node.canonical') {
      return parent::convert($value, $definition, $name, $defaults);
    }

    $contexts_repository = \Drupal::service('context.repository');
    $contexts = $contexts_repository->getAvailableContexts();
    $current_langcode = $this->getContentLanguageFromContexts($contexts);

    $node_id = $defaults['node'];
    $ids = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getQuery()
      ->condition('field_language_revision.entity_id', $node_id)
      ->condition('field_language_revision.entity_type', 'node')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return parent::convert($value, $definition, $name, $defaults);
    }

    $active_revisions = $this->entityTypeManager->getStorage('oe_translation_active_revision')->loadMultiple($ids);
    $active_revision = reset($active_revisions);

    $language_revisions = $active_revision->get('field_language_revision')->getValue();
    $has_language_active_revision = FALSE;
    $language_active_revision = NULL;
    foreach ($language_revisions as $delta => $values) {
      if ($values['langcode'] === $current_langcode) {
        $has_language_active_revision = TRUE;
        $language_active_revision = $this->entityTypeManager->getStorage('node')->loadRevision($values['entity_revision_id']);
        break;
      }
    }

    if (!$has_language_active_revision) {
      return parent::convert($value, $definition, $name, $defaults);
    }

    if ($language_active_revision->getUntranslated()->language()->getId() === $current_langcode) {
      // If we are on the untranslated version, we defer back to the parent.
      return parent::convert($value, $definition, $name, $defaults);
    }

    return $this->entityRepository->getTranslationFromContext($language_active_revision);
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
     foreach ([LanguageInterface::TYPE_CONTENT, LanguageInterface::TYPE_INTERFACE] as $language_type) {
       $context_id = '@language.current_language_context:' . $language_type;
       if (isset($contexts[$context_id])) {
         return $contexts[$context_id]->getContextValue()->getId();
       }
     }
     return \Drupal::languageManager()->getDefaultLanguage()->getId();
   }
}
