<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_mock;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation_epoetry\EpoetryLanguageMapper;
use Drupal\oe_translation_epoetry\NotificationEndpointResolver;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Helper class to deal with requests until we have notifications set up.
 */
class EpoetryTranslationMockHelper {

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
   * Potential errors to include when sending a translation.
   *
   * @var array
   */
  public static $translationRequestErrors = [];

  /**
   * Adds dummy translation request values for a given language.
   *
   * @param \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $request
   *   The translation request.
   * @param string $langcode
   *   The langcode.
   * @param string|null $suffix
   *   An extra suffix to append to the translation.
   */
  public static function translateRequest(TranslationRequestRemoteInterface $request, string $langcode, ?string $suffix = NULL): void {
    $data = $request->getData();

    $langcode = EpoetryLanguageMapper::getEpoetryLanguageCode($langcode, $request);

    foreach ($data as $field => &$info) {
      if (!is_array($info)) {
        continue;
      }

      static::translateFieldData($info, $langcode, $suffix);
    }

    // Set the translated data onto the request as the original so that we can
    // export it using the content exporter.
    $request->setData($data);

    if (isset(static::$translationRequestErrors['missing translation'])) {
      $request->setData([]);
    }

    $exported = \Drupal::service('oe_translation_epoetry.html_formatter')->export($request);

    $values = [
      '#request_id' => $request->getRequestId(),
      '#language' => $langcode,
      '#status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SENT,
      '#file' => base64_encode((string) $exported),
      '#name' => $request->getContentEntity()->label(),
      '#format' => 'HTML',
    ];

    \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use ($values) {
      $build = [
        '#theme' => 'product_delivery',
      ] + $values;

      $notification = (string) \Drupal::service('renderer')->renderRoot($build);

      static::performNotification($notification);
    });

  }

  /**
   * Recursively sets translated data to field values.
   *
   * @todo refactor to reuse the logic from TestRemoteTranslationMockHelper.
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
   * Notifies the request.
   *
   * Mimics notifications that can come from ePoetry.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetry $request
   *   The request.
   * @param array $notification
   *   Notification data.
   * @param bool $wait
   *   Whether to wait for the response.
   */
  public static function notifyRequest(TranslationRequestEpoetry $request, array $notification, bool $wait = TRUE): void {
    $type = $notification['type'];

    switch ($type) {
      case 'RequestStatusChange':
        $values = [
          '#request_id' => $request->getRequestId(),
          '#planning_agent' => 'test',
          '#planning_sector' => 'DGT',
          '#status' => $notification['status'],
          '#message' => sprintf('The request status has been changed to %s', $notification['status']),
        ];
        if (isset($notification['message']) && $notification['message']) {
          $values['#message'] = $notification['message'];
        }

        \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use ($values, $wait) {
            $build = [
              '#theme' => 'request_status_change',
            ] + $values;

            $notification = (string) \Drupal::service('renderer')->renderRoot($build);
            static::performNotification($notification, $wait);
        });

        break;

      case 'ProductStatusChange':
        $values = [
          '#request_id' => $request->getRequestId(),
          '#language' => EpoetryLanguageMapper::getEpoetryLanguageCode($notification['language'], $request),
          '#status' => $notification['status'],
        ];

        if ($notification['status'] === TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ONGOING) {
          $values['#accepted_deadline'] = TRUE;
        }

        \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use ($values, $wait) {
            $build = [
              '#theme' => 'product_status_change',
            ] + $values;

            $notification = (string) \Drupal::service('renderer')->renderRoot($build);
            static::performNotification($notification, $wait);
        });

        break;

      case 'ProductDelivery':
        static::translateRequest($request, $notification['language']);
        break;
    }
  }

  /**
   * Calls the notification endpoint with a message.
   *
   * This mimics notification requests sent by ePoetry.
   *
   * @param string $notification
   *   The notification message.
   * @param bool $wait
   *   Whether to wait for the response.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected static function performNotification(string $notification, bool $wait = TRUE): void {
    $url = NotificationEndpointResolver::resolve();
    $config = \Drupal::config('oe_translation_epoetry_mock.settings');
    $notifications_endpoint = $config->get('notifications_endpoint');
    if ($notifications_endpoint && $notifications_endpoint !== '') {
      $url = $notifications_endpoint;
    }
    if (Settings::get('epoetry_notifications_endpoint')) {
      $url = Settings::get('epoetry_notifications_endpoint');
    }

    $body = sprintf('<?xml version="1.0" encoding="utf-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eu="http://eu.europa.ec.dgt.epoetry"><soapenv:Header><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">abc</ecas:ProxyTicket></soapenv:Header><S:Body xmlns:S="http://schemas.xmlsoap.org/soap/envelope/"><ns0:receiveNotification xmlns:ns0="http://eu.europa.ec.dgt.epoetry">%s</ns0:receiveNotification></S:Body></soapenv:Envelope>', $notification);

    $options = [
      'cache_wsdl' => WSDL_CACHE_NONE,
      'location' => $url,
      'uri' => 'http://eu.europa.ec.dgt.epoetry',
    ];
    $username = $config->get('notifications_username');
    $password = $config->get('notifications_password');
    if ($username && $password && $username != "" && $password != "") {
      $options['login'] = $username;
      $options['password'] = $password;
    }

    $headers = [
      'Content-type: text/xml;charset="utf-8"',
      'Accept: text/xml',
      'Cache-Control: no-cache',
      'Pragma: no-cache',
      'SOAPAction: receiveNotification',
      'Content-length: ' . strlen($body),
    ];
    if (static::$databasePrefix) {
      $headers[] = 'Cookie: SIMPLETEST_USER_AGENT=' . drupal_generate_test_ua(static::$databasePrefix);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    $timeout = $wait ? 10 : 1;
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_exec($ch);
    curl_close($ch);
  }

}
