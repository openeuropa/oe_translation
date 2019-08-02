<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock;

use Drupal\Core\Url;

/**
 * SoapServer class for mocking the Poetry service.
 *
 * @see \Drupal\oe_translation_poetry\PoetryMock
 */
class PoetryMock {

  /**
   * The main entry point.
   *
   * @param string $username
   *   The poetry service username.
   * @param string $password
   *   The poetry service password.
   * @param string $message
   *   The message.
   *
   * @return string
   *   The XML string response.
   */
  public function requestService(string $username, string $password, string $message): string {
    if ($username !== 'admin' || $password !== 'admin') {
      return 'Invalid userName/Logon, access denied.';
    }

    $xml = simplexml_load_string($message);
    $request = $xml->request;
    $details = (array) $request->demande;
    $id = (string) $details['userReference'];
    $response = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/' . $id . '.xml');
    if ($response) {
      return $response;
    }

    return file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/error.xml');
  }

  /**
   * Builds the URL to the local mock Poetry WSDL.
   *
   * @return string
   *   The URL.
   */
  public static  function getWsdlUrl(): string {
    return Url::fromRoute('oe_translation_poetry_mock.wsdl')->setAbsolute()->toString();
  }

}
