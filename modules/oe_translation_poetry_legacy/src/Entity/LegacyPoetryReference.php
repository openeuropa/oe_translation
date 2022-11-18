<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_legacy\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\node\NodeInterface;

/**
 * Defines the Legacy Poetry reference entity.
 *
 * @ContentEntityType(
 *   id = "poetry_legacy_reference",
 *   label = @Translation("Legacy Poetry reference"),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "poetry_legacy_reference",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/legacy-poetry-references/{poetry_legacy_reference}",
 *     "collection" = "/admin/content/legacy-poetry-references",
 *   }
 * )
 */
class LegacyPoetryReference extends ContentEntityBase implements LegacyPoetryReferenceInterface {

  /**
   * {@inheritdoc}
   */
  public function getNode(): ?NodeInterface {
    return $this->get('node')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setNode(NodeInterface $node): LegacyPoetryReference {
    return $this->set('node', ['target_id' => $node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getPoetryId(): ?string {
    return $this->get('poetry_request_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPoetryId(string $poetry_id): LegacyPoetryReference {
    return $this->set('poetry_request_id', $poetry_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['node'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Node')
      ->setDescription(t('The node reference containing the Poetry request.'))
      ->setSetting('target_type', 'node');

    $fields['poetry_request_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Poetry request ID'))
      ->setDescription(t('The Poetry request ID of the node.'));

    return $fields;
  }

}
