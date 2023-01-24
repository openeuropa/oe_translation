<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock;

use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_remote_test\TestRemoteTranslationMockHelper;

/**
 * Helper class to deal with requests until we have notifications set up.
 */
class EpoetryTranslationMockHelper extends TestRemoteTranslationMockHelper {

  /**
   * Notifies the request.
   *
   * Mimics notifications that can come from ePoetry.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetry $request
   *   The request.
   * @param array $notification
   *   Notification data.
   */
  public static function notifyRequest(TranslationRequestEpoetry $request, array $notification): void {
    $type = $notification['type'];

    switch ($type) {
      case 'RequestStatusChange':
        $request->setEpoetryRequestStatus($notification['status']);
        break;

      case 'ProductStatusChange':
        $language = $notification['language'];
        $request->updateTargetLanguageStatus($language, $notification['status']);
        break;
    }
  }

}
