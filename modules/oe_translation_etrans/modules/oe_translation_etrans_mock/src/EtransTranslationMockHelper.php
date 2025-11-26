<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans_mock;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\LanguageMapper;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;

/**
 * Helper class for dealing with requests to etrans and incoming translations.
 */
class EtransTranslationMockHelper {

  /**
   * The PHPUnit test database prefix.
   *
   * We set this from the outside in case we are using this helper from within
   * a test.
   *
   * @var string
   */
  public static $databasePrefix;

  /**
   * The HTTP code of the last request.
   *
   * @var string
   */
  public static $httpCode;

  /**
   * Adds dummy translation request values for a given language.
   *
   * @param \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $request
   *   The translation request.
   * @param string $langcode
   *   The langcode.
   * @param string|null $suffix
   *   An extra suffix to append to the translation.
   */
  public static function translateRequest(TranslationRequestEtransInterface $request, string $langcode, ?string $suffix = NULL): void {
    $data = $request->getData();
    $original_data = $data;

    $langcode = LanguageMapper::getMappedLanguageCode($langcode, $request);

    foreach ($data as $field => &$info) {
      if (!is_array($info)) {
        continue;
      }

      static::translateFieldData($info, $langcode, $suffix);
    }

    // Set the translated data onto the request as the original so that we can
    // export it using the content exporter.
    $request->setData($data);

    $exported = (string) \Drupal::service('oe_translation_content_formatter.html_formatter')->export($request);

    // Set back the untranslated data back.
    $request->setData($original_data);

    $result = base64_encode($exported);

    $data = [
      'requestId' => $request->get('remote_id')->value ?? '1',
      'externalReference' => $request->getSavedAccessToken(),
      'targetLanguage' => $langcode,
      'result' => $result,
    ];

    static::performNotification($data, 'delivery');
  }

  /**
   * Sends an error callback.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request.
   * @param string $langcode
   *   The langcode.
   * @param string $error_code
   *   The error code.
   * @param string $error_message
   *   The error message.
   */
  public static function sendErrorCallback(TranslationRequestInterface $request, string $langcode, string $error_code, string $error_message): void {
    $langcode = LanguageMapper::getMappedLanguageCode($langcode, $request);
    $data = [
      'requestId' => $request->get('remote_id')->value ?? '1',
      'externalReference' => $request->getSavedAccessToken(),
      'targetLanguages' => [$langcode],
      'errorCode' => $error_code,
      'errorMessage' => $error_message,
    ];

    static::performNotification($data, 'error');
  }

  /**
   * Recursively sets translated data to field values.
   *
   * @param array $data
   *   The data.
   * @param string $langcode
   *   The langcode.
   * @param string|null $suffix
   *   An extra suffix to append to the translation.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected static function translateFieldData(array &$data, string $langcode, ?string $suffix = NULL): void {
    if (!isset($data['#text'])) {
      foreach ($data as $field => &$info) {
        if (!is_array($info)) {
          continue;
        }
        static::translateFieldData($info, $langcode, $suffix);
      }

      return;
    }

    if (isset($data['#translate']) && $data['#translate'] === FALSE) {
      return;
    }

    // Check whether this is a new translation or not by checking for a
    // stored translation for the field.
    if (isset($data['#translation'])) {
      $data['#translation']['#text'] = $data['#translation']['#text'] . ' OVERRIDDEN';
      return;
    }

    $append = $suffix ? $langcode . ' - ' . $suffix : $langcode;
    $data['#translation']['#text'] = $data['#text'] . ' - ' . $append;

    // Set the translation value onto the original.
    if (isset($data['#translation']['#text']) && $data['#translation']['#text'] != "") {
      $data['#text'] = $data['#translation']['#text'];
    }
  }

  /**
   * Calls the notification endpoint with a message.
   */
  public static function performNotification(array $data, string $endpoint = 'delivery'): void {
    $url = NULL;
    if ($endpoint === 'delivery') {
      $url = Settings::get('etrans.delivery_endpoint');
      if (!$url) {
        $url = Url::fromRoute('oe_translation_etrans.delivery_callback')
          ->setAbsolute()
          ->toString();
      }
    }
    if ($endpoint === 'error') {
      $url = Url::fromRoute('oe_translation_etrans.error_callback')
        ->setAbsolute()
        ->toString();
    }

    $headers = [
      'Content-Type' => 'application/json',
    ];
    if (static::$databasePrefix) {
      $headers[] = 'Cookie: SIMPLETEST_USER_AGENT=' . drupal_generate_test_ua(static::$databasePrefix);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_exec($ch);
    static::$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
  }

}
