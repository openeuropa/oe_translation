<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Handler for managing new version requests for ongoing requests.
 *
 * This is responsible for determining if, for a given request, we can make
 * a new request to create a new version, while the first is ongoing.
 */
interface EpoetryOngoingNewVersionRequestHandlerInterface {

  /**
   * Determines if we are allowed to request a new version.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The existing request.
   *
   * @return bool
   *   Whether we are allowed.
   */
  public function canCreateRequest(TranslationRequestEpoetryInterface $request): bool;

  /**
   * Shows an information message to the user about the new version request.
   *
   * This is used to inform about what they are doing and what are the
   * consequences of making this request.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The existing request.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The message.
   */
  public function getInfoMessage(TranslationRequestEpoetryInterface $request): MarkupInterface;

  /**
   * Returns the entity revision for which the update should be requested.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The existing request.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity revision.
   */
  public function getUpdateEntity(TranslationRequestEpoetryInterface $request): ContentEntityInterface;

}
