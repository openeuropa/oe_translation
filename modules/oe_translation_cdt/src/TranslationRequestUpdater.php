<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt;

use Drupal\oe_translation_cdt\Mapper\LanguageCodeMapper;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\CdtClient\Model\Callback\JobStatus;
use OpenEuropa\CdtClient\Model\Callback\RequestStatus;
use OpenEuropa\CdtClient\Model\Response\Translation;

/**
 * The service that updates the translation request data.
 */
final class TranslationRequestUpdater implements TranslationRequestUpdaterInterface {

  /**
   * {@inheritdoc}
   */
  public function updateFromRequestStatus(TranslationRequestCdtInterface $translation_request, RequestStatus $request_status): bool {
    return $this->updateFieldset($translation_request, [
      'cdt_id' => $request_status->getRequestIdentifier(),
      'request_status' => $this->convertStatusFromCdt($request_status->getStatus()),
    ], "Received CDT callback, updating the request...");
  }

  /**
   * {@inheritdoc}
   */
  public function updateFromJobStatus(TranslationRequestCdtInterface $translation_request, JobStatus $job_status): bool {
    $drupal_langcode = LanguageCodeMapper::getDrupalLanguageCode($job_status->getTargetLanguageCode(), $translation_request);
    if ($job_status->getStatus() === 'CMP') {
      return $this->updateFieldset($translation_request, [
        'translated_languages' => [$drupal_langcode],
      ], "Received CDT callback, updating the job...");
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFromTranslationResponse(TranslationRequestCdtInterface $translation_request, Translation $translation_response): bool {
    $translated_languages = [];
    foreach ($translation_response->getTargetFiles() as $target_file) {
      $drupal_langcode = LanguageCodeMapper::getDrupalLanguageCode((string) $target_file->getTargetLanguage(), $translation_request);
      $translated_languages[] = $drupal_langcode;
    }

    $comments = array_reduce($translation_response->getComments(), function ($carry, $item) {
      return $carry . $item->getComment() . "\n";
    }, '');

    // @todo use departent label
    // @todo change contact
    // @todo update mock service
    return $this->updateFieldset($translation_request, [
      'request_status' => $this->convertStatusFromCdt($translation_response->getStatus()),
      'translated_languages' => $translated_languages,
      'department' => $translation_response->getDepartment(),
      'priority' => $translation_response->getJobSummary()[0]->getPriorityCode(),
      'comments' => $comments,
      'phone_number' => $translation_response->getPhoneNumber(),
    ], "Manually updated the status.");
  }

  /**
   * {@inheritdoc}
   */
  public function updatePermanentId(TranslationRequestCdtInterface $translation_request, string $permanent_id): bool {
    return $this->updateFieldset($translation_request, [
      'cdt_id' => $permanent_id,
    ], "Manually updated the permanent ID.");
  }

  /**
   * Update a set of fields on the translation request.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param array $fields
   *   An associative array of fields to update.
   * @param string|null $global_message
   *   A global message to log.
   *
   * @return bool
   *   TRUE if any changes were made, FALSE otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateFieldset(TranslationRequestCdtInterface $translation_request, array $fields, string $global_message = NULL): bool {
    $cumulated_changes = [];
    $cumulated_variables = [];
    foreach ($fields as $field => $value) {
      $message = match($field) {
        'translated_languages' => $this->updateLanguagesStatus($translation_request, $value),
        default => $this->updateField($translation_request, $field, $value),
      };
      if ($message) {
        $cumulated_changes[] = $message['message'];
        $cumulated_variables = array_merge($cumulated_variables, $message['variables']);
      }
    }
    if (!empty($cumulated_changes)) {
      $log_message = implode("<br>", $cumulated_changes);
      if ($global_message) {
        $log_message = $global_message . "<br>" . $log_message;
      }
      $translation_request->log($log_message, $cumulated_variables);
      $translation_request->save();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Update a single field on the translation request.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param string $field
   *   The field to update.
   * @param mixed $value
   *   The new value.
   *
   * @return array|null
   *   The log message if the field was updated, NULL otherwise.
   *
   * @throws \InvalidArgumentException
   *   If the field does not exist or does not support getters/setters.
   */
  protected function updateField(TranslationRequestCdtInterface $translation_request, string $field, $value): ?array {
    $reflection = new \ReflectionClass(TranslationRequestCdtInterface::class);
    $setter = 'set' . str_replace('_', '', ucwords($field, '_'));
    $getter = 'get' . str_replace('_', '', ucwords($field, '_'));
    if (!$reflection->hasMethod($setter) || !$reflection->hasMethod($getter)) {
      throw new \InvalidArgumentException(sprintf('Field %s does not exist or does not support getters/setters.', $field));
    }

    $old_value = $translation_request->$getter();
    if ($old_value !== $value) {
      $translation_request->$setter($value);
      $from_text = $old_value ? "from <code>@{$field}_old_value</code>" : '';
      return [
        'message' => "Updated <strong >@{$field}_name</strong> field $from_text to <code>@{$field}_new_value</code>.",
        'variables' => [
          "@{$field}_name" => $field,
          "@{$field}_old_value" => $old_value,
          "@{$field}_new_value" => $value,
        ],
      ];
    }

    return NULL;
  }

  /**
   * Update the status of the languages.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param array $languages
   *   Ann array of translated languages.
   *
   * @return array|null
   *   The log message if the languages were updated, NULL otherwise.
   */
  protected function updateLanguagesStatus(TranslationRequestCdtInterface $translation_request, array $languages): ?array {
    $updated_languages = [];
    foreach ($languages as $langcode) {
      $old_status = $translation_request->getTargetLanguage($langcode)?->getStatus();
      if ($old_status == TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED) {
        $translation_request->updateTargetLanguageStatus($langcode, TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW);
        $updated_languages[] = $langcode;
      }
    }

    if ($updated_languages) {
      return [
        'message' => "The following languages are updated and ready for review: <strong>@languages</strong>.",
        'variables' => [
          '@languages' => implode(', ', $updated_languages),
        ],
      ];
    }

    return NULL;
  }

  /**
   * Converts the status from CDT to the internal status.
   *
   * @param string $cdt_status
   *   The CDT status.
   *
   * @return string
   *   The internal status.
   */
  protected function convertStatusFromCdt(string $cdt_status): string {
    return match($cdt_status) {
      'COMP' => TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
      'CANC' => TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
      default => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    };
  }

}
