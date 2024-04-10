<?php

namespace Drupal\oe_translation_cdt\Mapper;

use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use OpenEuropa\CdtClient\Model\Request\Translation;

/**
 * Defines the interface for converting TranslationRequest entity to CDT DTO.
 */
interface TranslationRequestMapperInterface {

  /**
   * Converts Drupal TranslationRequest entity to the CDT library DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $entity
   *   The TranslationRequest entity to convert.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\Translation
   *   The DTO object.
   */
  public function convertEntityToDto(TranslationRequestCdtInterface $entity): Translation;

}
