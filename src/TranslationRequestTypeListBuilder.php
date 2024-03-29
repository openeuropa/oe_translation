<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of translation request type entities.
 *
 * @see \Drupal\oe_translation\Entity\TranslationRequestType
 */
class TranslationRequestTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Label');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No translation request types available. <a href=":link">Add translation request type</a>.',
      [':link' => Url::fromRoute('entity.oe_translation_request_type.add_form')->toString()]
    );

    return $build;
  }

}
