<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Renderer;
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
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * PoetryMockFixturesGenerator constructor.
   *
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(Poetry $poetry, Renderer $renderer) {
    $this->poetry = $poetry;
    $this->renderer = $renderer;
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
   * Creates a status notification fixture.
   *
   * @param array $request_identifier
   *   The request identifier data.
   * @param string $status
   *   The full request (demand) status.
   * @param array $accepted_languages
   *   The data for the accepted languages.
   * @param array $refused_languages
   *   The data for the refused languages.
   * @param array $cancelled_languages
   *   The data for the cancelled languages.
   *
   * @return string
   *   The XML response.
   */
  public function statusNotification(array $request_identifier, string $status, array $accepted_languages, array $refused_languages = [], array $cancelled_languages = []): string {
    $vars = [
      '#theme' => 'status_notification',
      '#request_identifier' => $request_identifier,
      '#request_status' => 'ONG',
      '#accepted_languages' => $accepted_languages,
    ];

    return (string) $this->renderer->renderRoot($vars);
  }

  /**
   * Generates a translation notification fixture.
   *
   * @param string $language
   *   The target language.
   * @param array $data
   *   The translated data values.
   * @param int $item_id
   *   The job item ID.
   * @param int $job_id
   *   The job ID.
   *
   * @return string
   *   The XML response.
   */
  public function translationNotification(string $language, array $data, int $item_id = 1, int $job_id = 1): string {
    $template = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_mock') . '/fixtures/translation_notification_template.xml');
    $variables = [
      '@job_id' => $job_id,
      '@item_id' => $item_id,
      '@language' => $language,
    ];

    foreach ($data as $key => $value) {
      $variables['@' . $key] = $value['#text'];
    }

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
