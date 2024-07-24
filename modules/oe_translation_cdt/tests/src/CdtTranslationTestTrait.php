<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Helpers for testing CDT.
 */
trait CdtTranslationTestTrait {

  /**
   * Gets the common translation request parameters.
   *
   * @return array
   *   The common parameters of CDT translation request.
   */
  private function getCommonTranslationRequestData() {
    return [
      'bundle' => 'cdt',
      'translator_provider' => 'cdt',
      'correlation_id' => '12345',
      'request_status' => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
      'comments' => 'COMMENT1',
      'confidentiality' => 'test_confidentiality',
      'contact_usernames' => [
        'TEST1',
      ],
      'deliver_to' => [
        'TEST2',
      ],
      'department' => 'DEP1',
      'phone_number' => '999999999',
      'priority' => 'PRIO1',
      'source_language_code' => 'en',
    ];
  }

  /**
   * Creates a translation request.
   *
   * @param array $data
   *   The data to create the translation request.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The content entity to attach to the translation request.
   * @param array $languages
   *   The target languages.
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface
   *   The translation request.
   */
  private function createTranslationRequest(array $data, ?ContentEntityInterface $entity, array $languages): TranslationRequestCdtInterface {
    $request = TranslationRequest::create($data);
    assert($request instanceof TranslationRequestCdtInterface);
    foreach ($languages as $language) {
      $request->updateTargetLanguageStatus($language, TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    }
    if ($entity instanceof ContentEntityInterface) {
      $request->setContentEntity($entity);
      $entity_data = $this->container->get('oe_translation.translation_source_manager')->extractData($entity->getUntranslated());
      $request->setData($entity_data);
    }

    return $request;
  }

}
