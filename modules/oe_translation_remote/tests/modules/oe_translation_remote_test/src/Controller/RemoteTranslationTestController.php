<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\oe_translation_remote_test\TestRemoteTranslationMockHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Remote translations test controller.
 */
class RemoteTranslationTestController extends ControllerBase {

  /**
   * Translates a given node into a given language using dummy values.
   *
   * @param \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $oe_translation_request
   *   The translation request.
   * @param string $langcode
   *   The langcode.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function translateRequest(TranslationRequestRemoteInterface $oe_translation_request, string $langcode, Request $request) {
    TestRemoteTranslationMockHelper::translateRequest($oe_translation_request, $langcode);
    $oe_translation_request->save();
    $this->messenger()
      ->addStatus($this->t('The translation request for @label in @langcode has been translated',
        [
          '@label' => $oe_translation_request->getContentEntity()->label(),
          '@langcode' => $langcode,
        ]));
    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }

    return new RedirectResponse($destination);
  }

}
