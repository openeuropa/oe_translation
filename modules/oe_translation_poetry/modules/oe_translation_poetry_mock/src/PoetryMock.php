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

  const USERNAME = 'admin';
  const PASSWORD = 'admin';

  /**
   * The mock fixtures generator.
   *
   * @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator
   */
  protected $fixturesGenerator;

  /**
   * PoetryMock constructor.
   *
   * @param \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator $fixturesGenerator
   *   The mock fixtures generator.
   */
  public function __construct(PoetryMockFixturesGenerator $fixturesGenerator) {
    $this->fixturesGenerator = $fixturesGenerator;
  }

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
    if ($username !== self::USERNAME || $password !== self::PASSWORD) {
      return 'ERROR: Authentication failed.';
    }
    return $this->fixturesGenerator->responseFromXml($message);
  }

  /**
   * Builds the URL to the local mock Poetry WSDL.
   *
   * @return string
   *   The URL.
   */
  public static function getWsdlUrl(): string {
    return Url::fromRoute('oe_translation_poetry_mock.wsdl')->setAbsolute()->toString();
  }

}
