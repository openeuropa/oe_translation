<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\views\EntityViewsData;

/**
 * Views data handler for the translation request entities.
 */
class TranslationRequestViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // For each content entity type, create a relationship to our content entity
    // field so that we can add a relationship to the referenced entity in
    // Views. Unfortunately, we cannot make this dynamically so a separate
    // relationship is needed for each targeted entity types. Meaning, we
    // cannot have for example, in Views, a single filter by the title of both
    // a node and block because the joins are against different tables.
    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition) {
      if (!$definition instanceof ContentEntityTypeInterface) {
        continue;
      }

      $target_base_table = $definition->getDataTable() ?: $definition->getBaseTable();
      if ($definition->isRevisionable()) {
        $target_base_table = $definition->getRevisionDataTable() ?: $definition->getRevisionTable();
      }
      $data['oe_translation_request']['content_entity__' . $definition->id()]['relationship'] = [
        'title' => t('@entity_type referenced from Entity Revision with Type', ['@entity_type' => $definition->getLabel()]),
        'label' => t('Entity Revision with Type: @entity_type', ['@entity_type' => $definition->getLabel()]),
        'group' => 'Translation Request',
        'id' => 'standard',
        'base' => $target_base_table,
        'entity type' => $definition->id(),
        'base field' => $definition->getKey('revision'),
        'relationship field' => 'content_entity__entity_revision_id',
      ];
    }

    return $data;
  }

}
