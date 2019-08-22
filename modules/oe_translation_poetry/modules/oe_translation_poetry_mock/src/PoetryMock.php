<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock;

use Drupal\Core\Url;
use Drupal\oe_translation_poetry\Poetry;

/**
 * SoapServer class for mocking the Poetry service.
 *
 * @see \Drupal\oe_translation_poetry\PoetryMock
 */
class PoetryMock {

  const USERNAME = 'admin';
  const PASSWORD = 'admin';
  const EXISTING_REFERENCE = 'WEB/2019/9999/0/0/TRA';

  /**
   * The mock fixtures generator.
   *
   * @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator
   */
  protected $fixturesGenerator;

  /**
   * The Poetry service.
   *
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * PoetryMock constructor.
   *
   * @param \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator $fixturesGenerator
   *   The mock fixtures generator.
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   */
  public function __construct(PoetryMockFixturesGenerator $fixturesGenerator, Poetry $poetry) {
    $this->fixturesGenerator = $fixturesGenerator;
    $this->poetry = $poetry;
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
    $xml = simplexml_load_string($message);
    $request = $xml->request;
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $identifier = $this->poetry->getIdentifier();
    $identifier->fromXml($request->children()->asXML());
    // The client library doesn't parse the sequence.
    $identifier->setSequence((string) $request->demandeId->sequence);

    if ((empty($identifier->getNumber()) && empty($identifier->getSequence())) || $identifier->getNumber() === '0') {
      return $this->fixturesGenerator->errorFromXml($message, 'Error in xmlActions:newRequest: Application general error : Element DEMANDEID.NUMERO.XMLTEXT is undefined in REQ_ROOT.,');
    }
    if ($identifier->getFormattedIdentifier() === self::EXISTING_REFERENCE) {
      return $this->fixturesGenerator->errorFromXml($message, 'Error in xmlActions:newRequest: A request with the same references if already in preparation another product exists for this reference.');
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
