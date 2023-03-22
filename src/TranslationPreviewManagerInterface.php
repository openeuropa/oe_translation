<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Interface for translation preview managers.
 */
interface TranslationPreviewManagerInterface {

  /**
   * Returns a translation of the underlying request used for preview.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request.
   * @param string $language
   *   The language of the translation.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The translation.
   */
  public function getTranslation(TranslationRequestInterface $request, string $language): ContentEntityInterface;

}
