<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Mapper;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\oe_translation\TranslationSourceHelper;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use OpenEuropa\CdtClient\Model\Request\Callback;
use OpenEuropa\CdtClient\Model\Request\File;
use OpenEuropa\CdtClient\Model\Request\SourceDocument;
use OpenEuropa\CdtClient\Model\Request\Translation;
use OpenEuropa\CdtClient\Model\Request\TranslationJob;

/**
 * Maps the data between the entity and DTO.
 *
 * Converting the TranslationRequestCDT entity to the
 * Translation Request DTO defined by the cdt-client library.
 */
class TranslationRequestMapper {

  protected const CHARACTERS_PER_PAGE = 750;

  protected const VOLUME_MULTIPLIER = 0.5;

  /**
   * Converts Drupal TranslationRequest entity to CDT library DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request entity.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\Translation
   *   The translation request DTO.
   */
  public static function entityToDto(TranslationRequestCdtInterface $translation_request): Translation {
    $translation = new Translation();
    $translation->setComments($translation_request->getComments());
    $translation->setTitle(sprintf(
      'Translation request #%s',
      $translation_request->id()
    ));
    $translation->setClientReference((string) $translation_request->id());
    $translation->setService('Translation');
    $translation->setPhoneNumber($translation_request->getPhoneNumber());
    $translation->setIsQuotationOnly(FALSE);
    $translation->setSendOptions('Send');
    $translation->setPurposeCode('WS');
    $translation->setDepartmentCode($translation_request->getDepartment());
    $translation->setContactUserNames($translation_request->getContactUsernames());
    $translation->setDeliveryContactUsernames($translation_request->getDeliverTo());
    $translation->setPriorityCode($translation_request->getPriority());
    $translation->setCallbacks(self::createCallbacks());
    $translation->setSourceDocuments([self::createSourceDocument($translation_request)]);
    return $translation;
  }

  /**
   * Creates the SourceDocument DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request entity.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\SourceDocument
   *   The source document DTO.
   */
  protected static function createSourceDocument(TranslationRequestCdtInterface $translation_request): SourceDocument {
    $source_document = new SourceDocument();
    $translation_jobs = self::createTranslationJobs($translation_request);
    $source_document->setTranslationJobs($translation_jobs);
    $source_languages = [
      LanguageCodeMapper::getCdtLanguageCode($translation_request->getSourceLanguageCode(), $translation_request),
    ];
    $source_document->setSourceLanguages($source_languages);
    $source_document->setIsPrivate(FALSE);
    $source_document->setOutputDocumentFormatCode('XM');
    $source_document->setConfidentialityCode($translation_request->getConfidentiality());
    $source_document->setFile(self::createFile($translation_request));
    return $source_document;
  }

  /**
   * Creates the File DTO.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request entity.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\File
   *   The file DTO.
   */
  protected static function createFile(TranslationRequestCdtInterface $translation_request): File {
    $file = new File();
    $file->setFileName(sprintf(
        'translation_job_%s_request.xml',
        $translation_request->id())
    );
    $file->setContent('<xml>');
    // @todo Content should be real.
    return $file;
  }

  /**
   * Creates the TranslationJob DTO list.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request entity.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\TranslationJob[]
   *   The translation job DTOs.
   */
  protected static function createTranslationJobs(TranslationRequestCdtInterface $translation_request): array {
    $translation_jobs = [];
    $character_count = self::countCharactersWithoutSpaces($translation_request);
    $volume = self::countVolume($character_count);
    foreach ($translation_request->getTargetLanguages() as $target_language) {
      $translation_job = new TranslationJob();
      $source_langcode = LanguageCodeMapper::getCdtLanguageCode($translation_request->getSourceLanguageCode(), $translation_request);
      $translation_job->setSourceLanguage($source_langcode);
      $target_langcode = LanguageCodeMapper::getCdtLanguageCode($target_language->getLangcode(), $translation_request);
      $translation_job->setTargetLanguage($target_langcode);
      $translation_job->setVolume($volume);
      $translation_jobs[] = $translation_job;
    }
    return $translation_jobs;
  }

  /**
   * Counts the characters in the translation request.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request entity.
   *
   * @return int
   *   The character count.
   */
  protected static function countCharactersWithoutSpaces(TranslationRequestCdtInterface $translation_request): int {
    $data = TranslationSourceHelper::filterTranslatable($translation_request->getData());
    $character_count = 0;
    foreach ($data as $field) {
      $text_without_spaces = preg_replace('/\s+/', '', $field['#text']);
      $character_count += mb_strlen(strip_tags($text_without_spaces));
    }
    return $character_count;
  }

  /**
   * Counts the volume of the content translation.
   *
   * @param int $character_count
   *   The content's character length.
   *
   * @return float
   *   The volume.
   */
  protected static function countVolume(int $character_count): float {
    return ceil($character_count / self::CHARACTERS_PER_PAGE) * self::VOLUME_MULTIPLIER;
  }

  /**
   * Creates the Callback DTOs.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\Callback[]
   *   The callback DTOs.
   */
  protected static function createCallbacks(): array {
    $job_status_url = Url::fromRoute(
      route_name: 'oe_translation_cdt.request_status_callback',
      options: ['absolute' => TRUE]
    );
    $request_status_url = Url::fromRoute(
      route_name: 'oe_translation_cdt.job_status_callback',
      options: ['absolute' => TRUE]
    );

    return [
      (new Callback())
        ->setCallbackType('JOB_STATUS')
        ->setCallbackBaseUrl($job_status_url->toString())
        ->setClientApiKey(Settings::get('cdt.api_key')),
      (new Callback())
        ->setCallbackType('REQUEST_STATUS')
        ->setCallbackBaseUrl($request_status_url->toString())
        ->setClientApiKey(Settings::get('cdt.api_key')),
    ];
  }

}
