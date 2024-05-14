<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt;

use Drupal\oe_translation_cdt\Api\CdtApiWrapperInterface;
use Drupal\oe_translation_cdt\Mapper\LanguageCodeMapper;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\CdtClient\Model\Callback\JobStatus;
use OpenEuropa\CdtClient\Model\Callback\RequestStatus;
use OpenEuropa\CdtClient\Model\Response\ReferenceData;
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
      'request_status' => $this->convertRequestStatusFromCdt($request_status->getStatus()),
    ], "Received CDT callback, updating the request...");
  }

  /**
   * {@inheritdoc}
   */
  public function updateFromJobStatus(TranslationRequestCdtInterface $translation_request, JobStatus $job_status): bool {
    $drupal_langcode = LanguageCodeMapper::getDrupalLanguageCode($job_status->getTargetLanguageCode(), $translation_request);
    return $this->updateFieldset($translation_request, [
      'languages' => [$drupal_langcode => $job_status->getStatus()],
    ], "Received CDT callback, updating the job...");
  }

  /**
   * {@inheritdoc}
   */
  public function updateFromTranslationResponse(TranslationRequestCdtInterface $translation_request, Translation $translation_response, ReferenceData $reference_data): bool {
    $languages = [];
    foreach ($translation_response->getJobSummary() as $job) {
      $drupal_langcode = LanguageCodeMapper::getDrupalLanguageCode($job->getTargetLanguage(), $translation_request);
      $languages[$drupal_langcode] = $job->getStatus();
    }

    $comments = trim(array_reduce($translation_response->getComments(), function ($carry, $item) {
      return $carry . $item->getComment() . "\n";
    }, ''), "\n");
    $changes = [
      'request_status' => $this->convertRequestStatusFromCdt($translation_response->getStatus()),
      'languages' => $languages,
      'priority' => $translation_response->getJobSummary()[0]->getPriorityCode(),
      'comments' => $comments,
      'phone_number' => $translation_response->getPhoneNumber(),
    ];

    // Get the department code.
    $departments = $reference_data->getDepartments();
    $department_label = $translation_response->getDepartment();
    foreach ($departments as $department) {
      if ($department->getDescription() === $department_label) {
        $changes['department'] = $department->getCode();
        break;
      }
    }

    // Build an array with contact mappings.
    $contacts = $reference_data->getContacts();
    $contacts_mapping = [];
    foreach ($contacts as $contact) {
      $contacts_mapping[$contact->getFirstName() . ' ' . $contact->getLastName()] = $contact->getUserName();
    }

    // Get the contact labels.
    foreach ($translation_response->getContacts() as $contact) {
      if (isset($contacts_mapping[$contact])) {
        $changes['contact_usernames'][] = $contacts_mapping[$contact];
      }
    }

    // Get the "deliver to" labels.
    foreach ($translation_response->getDeliverToContacts() as $contact) {
      if (isset($contacts_mapping[$contact])) {
        $changes['deliver_to'][] = $contacts_mapping[$contact];
      }
    }

    return $this->updateFieldset($translation_request, $changes, "Manually updated the status.");
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
   * Updates a set of fields on the translation request.
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
        'languages' => $this->updateLanguagesStatus($translation_request, $value),
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
   * Updates a single field on the translation request.
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

    $old_value = $translation_request->$getter() ?? '';
    if ($old_value !== $value) {
      $translation_request->$setter($value);
      $from_text = $old_value ? "from <code>@{$field}_old_value</code>" : '';
      return [
        'message' => "Updated <strong >@{$field}_name</strong> field $from_text to <code>@{$field}_new_value</code>.",
        'variables' => [
          "@{$field}_name" => $field,
          "@{$field}_old_value" => is_array($old_value) ? implode(', ', $old_value) : $old_value,
          "@{$field}_new_value" => is_array($value) ? implode(', ', $value) : $value,
        ],
      ];
    }

    return NULL;
  }

  /**
   * Updates the status of the languages.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request to update.
   * @param array $languages
   *   Ann array of languages and their statuses.
   *
   * @return array|null
   *   The log message if the languages were updated, NULL otherwise.
   */
  protected function updateLanguagesStatus(TranslationRequestCdtInterface $translation_request, array $languages): ?array {
    $updated_languages = [];
    foreach ($languages as $langcode => $cdt_status) {
      $old_status = $translation_request->getTargetLanguage($langcode)?->getStatus();
      $new_status = $this->convertLanguageStatusFromCdt($cdt_status);
      if ($old_status != $new_status) {
        if (in_array($new_status, [
          TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED,
          TranslationRequestCdtInterface::STATUS_LANGUAGE_FAILED,
          TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED,
        ],)) {
          $translation_request->removeTranslatedData($langcode);
        }
        $translation_request->updateTargetLanguageStatus($langcode, $new_status);
        $updated_languages[] = sprintf('%s (%s => %s)', $langcode, $old_status, $new_status);
      }
    }

    if ($updated_languages) {
      return [
        'message' => "The following languages are updated: <strong>@languages</strong>.",
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
  protected function convertRequestStatusFromCdt(string $cdt_status): string {
    return match($cdt_status) {
      CdtApiWrapperInterface::STATUS_REQUEST_COMPLETED => TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
      CdtApiWrapperInterface::STATUS_REQUEST_CANCELLED => TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
      default => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    };
  }

  /**
   * Converts the language status from CDT to the internal status.
   *
   * @param string|null $cdt_language_status
   *   The CDT language status.
   *
   * @return string
   *   The internal status.
   */
  protected function convertLanguageStatusFromCdt(?string $cdt_language_status): string {
    return match($cdt_language_status) {
      CdtApiWrapperInterface::STATUS_JOB_FAILED => TranslationRequestCdtInterface::STATUS_LANGUAGE_FAILED,
      CdtApiWrapperInterface::STATUS_JOB_CANCELLED, CdtApiWrapperInterface::STATUS_JOB_TO_BE_CANCELLED => TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED,
      CdtApiWrapperInterface::STATUS_JOB_COMPLETED => TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW,
      default => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    };
  }

}
