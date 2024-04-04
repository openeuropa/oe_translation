<?php

namespace Drupal\oe_translation_cdt\Mapper;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for converting Drupal entities to CDT DTOs.
 */
interface DtoMapperInterface {

  /**
   * Converts Drupal entity to the CDT library DTO.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to convert.
   *
   * @return mixed
   *   The DTO object.
   */
  public function convertEntityToDto(ContentEntityInterface $entity): mixed;

}
