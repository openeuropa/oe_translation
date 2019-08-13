<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;

/**
 * SoapServer class for mocking the Poetry service.
 *
 * @see \Drupal\oe_translation_poetry\PoetryMock
 */
class PoetryMock {

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
    $xml = simplexml_load_string($message);
    $request = $xml->request;
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = \Drupal::service('oe_translation_poetry.client.default');
    $identifier = $poetry->getIdentifier();
    $identifier->fromXml($request->children()->asXML());
    // The client library doesn't parse the sequence.
    $identifier->setSequence((string) $request->demandeId->sequence);
    if ($identifier->getSequence()) {
      $previous_number = (int) $poetry->getGlobalIdentifierNumber();
      $new_number = $previous_number ? (int) $previous_number++ : static::START_NUMBER;
      $identifier->setNumber($new_number);
    }

    $identifier_variables = [
      '@code' => $identifier->getCode(),
      '@year' => $identifier->getYear(),
      '@number' => $identifier->getNumber(),
      '@version' => $identifier->getVersion(),
      '@part' => $identifier->getPart(),
      '@product' => $identifier->getProduct(),
    ];

    // @todo allow also for error responses.
    // - no counter (sequence) registered with poetry
    $template = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/successful_response_template.xml');
    $response = new FormattableMarkup($template, $identifier_variables);
    return (string) $response;
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
