<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\oe_translation_poetry\Poetry;
use EC\Poetry\Messages\Components\Identifier;
use EC\Poetry\Messages\MessageInterface;

/**
 * Generates dynamic mock fixtures for the Poetry integrations.
 */
class PoetryMockFixturesGenerator {

  /**
   * A dummy reference that mocks that it already exists in the Poetry system.
   */
  const EXISTING_REFERENCE = 'WEB/2019/9999/0/0/TRA';

  /**
   * The Poetry service.
   *
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * PoetryMockFixturesGenerator constructor.
   *
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   */
  public function __construct(Poetry $poetry) {
    $this->poetry = $poetry;
  }

  /**
   * Returns an XML response from a request XML.
   *
   * @param string $request_xml
   *   The XML request.
   *
   * @return string
   *   The XML response.
   */
  public function responseFromXml(string $request_xml): string {
    $xml = simplexml_load_string($request_xml);
    $request = $xml->request;
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $identifier = $this->poetry->getIdentifier();
    $identifier->fromXml($request->children()->asXML());
    // The client library doesn't parse the sequence.
    $identifier->setSequence((string) $request->demandeId->sequence);
    $variables = $this->prepareIdentifierVariables($identifier);

    $error_template = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/error_template.xml');
    $success_template = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/successful_response_template.xml');

    if ((empty($identifier->getNumber()) && empty($identifier->getSequence())) || $identifier->getNumber() === '0') {
      $variables['@message'] = 'Error in xmlActions:newRequest: Application general error : Element DEMANDEID.NUMERO.XMLTEXT is undefined in REQ_ROOT.,';
      $response = new FormattableMarkup($error_template, $variables);
      return (string) $response;
    }
    if ($identifier->getFormattedIdentifier() === self::EXISTING_REFERENCE) {
      $variables['@message'] = 'Error in xmlActions:newRequest: A request with the same references if already in preparation another product exists for this reference.';
      $response = new FormattableMarkup($error_template, $variables);
      return (string) $response;
    }

    $response = new FormattableMarkup($success_template, $variables);
    return (string) $response;
  }

  /**
   * Returns an XML response from a request message object.
   *
   * @param \EC\Poetry\Messages\MessageInterface $message
   *   The message.
   *
   * @return string
   *   The XML response.
   */
  public function responseFromMessage(MessageInterface $message): string {
    $xml = $this->poetry->get('renderer')->render($message);
    return $this->responseFromXml($xml);
  }

  /**
   * Prepares the identifier variables to replace in the template.
   *
   * @param \EC\Poetry\Messages\Components\Identifier $identifier
   *   The identifier.
   *
   * @return array
   *   The variables.
   */
  protected function prepareIdentifierVariables(Identifier $identifier): array {
    if ($identifier->getSequence()) {
      $new_number = 1000;
      $previous_number = $this->poetry->getGlobalIdentifierNumber();
      if ($previous_number) {
        $new_number = $previous_number + 1;
      }
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

    return $identifier_variables;
  }

}
