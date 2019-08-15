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

    // @todo allow also for error responses.
    // - no counter (sequence) registered with poetry
    $template = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/successful_response_template.xml');
    $response = new FormattableMarkup($template, $variables);
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
    $identifier = $message->getIdentifier();
    $variables = $this->prepareIdentifierVariables($identifier);

    // @todo allow also for error responses.
    // - no counter (sequence) registered with poetry
    $template = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/successful_response_template.xml');
    $response = new FormattableMarkup($template, $variables);
    return (string) $response;
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
