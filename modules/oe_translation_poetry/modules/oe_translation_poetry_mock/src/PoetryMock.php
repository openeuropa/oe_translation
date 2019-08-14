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
   * The initial number.
   */
  const START_NUMBER = 1000;

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
    /** @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator $generator */
    $generator = \Drupal::service('oe_translation_poetry_mock.fixture_generator');
    return $generator->responseFromXml($message);
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
