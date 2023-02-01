<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface;
use GuzzleHttp\ClientInterface;
use Http\Adapter\Guzzle6\Client;
use OpenEuropa\EPoetry\Request\Type\ContactPersonIn;
use OpenEuropa\EPoetry\Request\Type\Contacts;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\LinguisticSectionOut;
use OpenEuropa\EPoetry\Request\Type\LinguisticSections;
use OpenEuropa\EPoetry\Request\Type\OriginalDocumentIn;
use OpenEuropa\EPoetry\Request\Type\ProductRequestIn;
use OpenEuropa\EPoetry\Request\Type\Products;
use OpenEuropa\EPoetry\Request\Type\RequestDetailsIn;
use OpenEuropa\EPoetry\RequestClientFactory;
use OpenEuropa\EPoetry\Tests\Authentication\MockAuthentication;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The ePoetry request factory.
 *
 * It provides the entry-way to creating requests for ePoetry.
 */
class RequestFactory extends RequestClientFactory {

  /**
   * The content formatter.
   *
   * @var \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface
   */
  protected $formatter;

  /**
   * Constructs a new instance of RequestFactory.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger factory.
   * @param \GuzzleHttp\ClientInterface $guzzle
   *   The Guzzle client.
   * @param \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface $formatter
   *   The content formatter.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, LoggerChannelFactoryInterface $loggerChannelFactory, ClientInterface $guzzle, ContentFormatterInterface $formatter) {
    $logger = $loggerChannelFactory->get('epoetry');
    $endpoint = Settings::get('epoetry.service_url');
    $this->formatter = $formatter;
    if (!$endpoint) {
      $logger->error('The ePoetry endpoint is not configured');
      throw new \Exception('The ePoetry endpoint is not configured');
      // @todo handle failure.
    }

    // @todo provide authentication mechanism.
    $authentication = new MockAuthentication('ticket');
    $http_client = new Client($guzzle);
    parent::__construct($endpoint, $authentication, $eventDispatcher, $logger, $http_client);
  }

  /**
   * Creates a client request object from our ePoetry translation request.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest
   *   A linguistic request object.
   */
  public function createTranslationRequest(TranslationRequestEpoetryInterface $request): CreateLinguisticRequest {
    $entity = $request->getContentEntity();
    $plugin_configuration = $request->getTranslatorProvider()->getProviderConfiguration();
    $request_title = (new FormattableMarkup('@prefix: @site_id - @title', [
      '@prefix' => $plugin_configuration['title_prefix'],
      '@site_id' => $plugin_configuration['site_id'],
      '@title' => $entity->label(),
    ]))->__toString();

    $request_details = new RequestDetailsIn();
    $request_details
      ->setTitle($request_title)
      ->setRequestedDeadline($request->getDeadline()->getPhpDateTime())
      ->setInternalReference('Translation request ' . $request->id());

    $contacts = new Contacts();
    foreach ($request->getContacts() as $contact_type => $contact) {
      $contacts->addContact(new ContactPersonIn($contact, strtoupper($contact_type)));
    }
    $request_details->setContacts($contacts);

    $linguistic_sections = (new LinguisticSections())
      ->addLinguisticSection(new LinguisticSectionOut('EN'));

    $content = $this->formatter->export($request);
    $original_document = (new OriginalDocumentIn())
      ->setTrackChanges(FALSE)
      ->setFileName($entity->label())
      ->setContent(base64_encode((string) $content))
      ->setComment($entity->toUrl()->setAbsolute()->toString())
      ->setLinguisticSections($linguistic_sections);
    $request_details->setOriginalDocument($original_document);

    if ($request->getMessage()) {
      $request_details->setComment($request->getMessage());
    }

    $products = new Products();
    foreach ($request->getTargetLanguages() as $language_with_status) {
      $productRequestIn = (new ProductRequestIn())
        // @todo fix the language mapping.
        ->setLanguage($language_with_status->getLangcode())
        ->setRequestedDeadline($request->getDeadline()->getPhpDateTime())
        ->setTrackChanges(FALSE);
      $products->addProduct($productRequestIn);
    }

    $request_details->setProducts($products);
    $request_details->setDestination('PUBLIC');
    $request_details->setProcedure('NEANT');
    $request_details->setSlaAnnex('NO');
    $request_details->setAccessibleTo('CONTACTS');

    $linguistic_request = new CreateLinguisticRequest();
    $linguistic_request
      ->setRequestDetails($request_details)
      ->setApplicationName(Settings::get('epoetry.application_name'))
      ->setTemplateName('WEBTRA');

    return $linguistic_request;
  }

}
