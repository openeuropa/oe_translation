<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface;
use Http\Adapter\Guzzle7\Client;
use OpenEuropa\EPoetry\Authentication\AuthenticationInterface;
use OpenEuropa\EPoetry\Request\Type\AddNewPartToDossier;
use OpenEuropa\EPoetry\Request\Type\ContactPersonIn;
use OpenEuropa\EPoetry\Request\Type\Contacts;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\CreateNewVersion;
use OpenEuropa\EPoetry\Request\Type\DossierReference;
use OpenEuropa\EPoetry\Request\Type\LinguisticRequestIn;
use OpenEuropa\EPoetry\Request\Type\LinguisticSectionOut;
use OpenEuropa\EPoetry\Request\Type\LinguisticSections;
use OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequestIn;
use OpenEuropa\EPoetry\Request\Type\ModifyRequestDetailsIn;
use OpenEuropa\EPoetry\Request\Type\ModifyRequestReferenceIn;
use OpenEuropa\EPoetry\Request\Type\OriginalDocumentIn;
use OpenEuropa\EPoetry\Request\Type\ProductRequestIn;
use OpenEuropa\EPoetry\Request\Type\Products;
use OpenEuropa\EPoetry\Request\Type\RequestDetailsIn;
use OpenEuropa\EPoetry\Request\Type\RequestReferenceIn;
use OpenEuropa\EPoetry\Request\Type\ResubmitRequest;
use OpenEuropa\EPoetry\RequestClientFactory;
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
   * The client certificate authentication.
   *
   * @var \Drupal\oe_translation_epoetry\ClientCertificateAuthentication
   */
  protected $certificateAuthentication;

  /**
   * Constructs a new instance of RequestFactory.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger factory.
   * @param \Drupal\Core\Http\ClientFactory $guzzle_factory
   *   The Guzzle client.
   * @param \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface $formatter
   *   The content formatter.
   * @param \OpenEuropa\EPoetry\Authentication\AuthenticationInterface $authentication
   *   The authentication object.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, LoggerChannelFactoryInterface $loggerChannelFactory, ClientFactory $guzzle_factory, ContentFormatterInterface $formatter, AuthenticationInterface $authentication) {
    $logger = $loggerChannelFactory->get('oe_translation_epoetry');
    $endpoint = static::getEpoetryServiceUrl();
    $this->formatter = $formatter;
    $this->certificateAuthentication = $authentication;
    if (!$endpoint) {
      $logger->error('The ePoetry endpoint is not configured');
      throw new \Exception('The ePoetry endpoint is not configured');
      // @todo handle failure.
    }

    // Increase the timeout to 60 seconds to accommodate for slower ePoetry
    // responses.
    $guzzle = $guzzle_factory->fromOptions([
      'timeout' => 60,
    ]);
    $authentication = $this->getAuthentication();
    $http_client = new Client($guzzle);
    parent::__construct($endpoint, $authentication, $eventDispatcher, $logger, $http_client);
  }

  /**
   * Returns the ePoetry service URL.
   *
   * @return string|null
   *   The ePoetry service URL.
   */
  public static function getEpoetryServiceUrl(): ?string {
    return Settings::get('epoetry.service_url');
  }

  /**
   * Returns the ePoetry application name.
   *
   * @return string|null
   *   The ePoetry application name.
   */
  public static function getEpoetryApplicationName(): ?string {
    return Settings::get('epoetry.application_name');
  }

  /**
   * Returns the ePoetry dossiers.
   *
   * These are the State-stored dossier values for all the ePoetry requests
   * that have been made on the current site.
   *
   * @return array
   *   The ePoetry dossiers.
   */
  public static function getEpoetryDossiers(): array {
    \Drupal::state()->resetCache();
    return \Drupal::state()->get('oe_translation_epoetry.dossiers', []);
  }

  /**
   * Sets the ePoetry dossiers.
   *
   * These are the State-stored dossier values for all the ePoetry requests
   * that have been made on the current site.
   *
   * @param array $dossiers
   *   The dossiers.
   */
  public static function setEpoetryDossiers(array $dossiers): void {
    \Drupal::state()->set('oe_translation_epoetry.dossiers', $dossiers);
  }

  /**
   * Creates a new request object from our ePoetry translation request.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest
   *   A linguistic request object.
   */
  public function createLinguisticRequest(TranslationRequestEpoetryInterface $request): CreateLinguisticRequest {
    $request_details = $this->toRequestDetails($request);

    $linguistic_request = new CreateLinguisticRequest();
    $linguistic_request
      ->setRequestDetails($request_details)
      ->setApplicationName(static::getEpoetryApplicationName())
      ->setTemplateName('WEBTRA');

    return $linguistic_request;
  }

  /**
   * Creates a resubmitRequest from a rejected ePoetry translation request.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $rejected_request
   *   The rejected request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\ResubmitRequest
   *   A resubmit request object.
   */
  public function resubmitRequest(TranslationRequestEpoetryInterface $request, TranslationRequestEpoetryInterface $rejected_request): ResubmitRequest {
    $request_details = $this->toRequestDetails($request);
    $reference = $this->toRequestReference($rejected_request, RequestReferenceIn::class);

    $linguistic_request_in = new LinguisticRequestIn();
    $linguistic_request_in->setRequestDetails($request_details);
    $linguistic_request_in->setRequestReference($reference);

    $resubmit_request = new ResubmitRequest();
    $resubmit_request
      ->setResubmitRequest($linguistic_request_in)
      ->setApplicationName(static::getEpoetryApplicationName())
      ->setTemplateName('WEBTRA');

    return $resubmit_request;
  }

  /**
   * Creates a new version request object from our ePoetry translation request.
   *
   * In doing so, we use the last request entity that was sent so that we can
   * send the request reference for updating the version.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $last_request
   *   The last translation request entity.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\CreateNewVersion
   *   A new version request object.
   */
  public function createNewVersionRequest(TranslationRequestEpoetryInterface $request, TranslationRequestEpoetryInterface $last_request): CreateNewVersion {
    $version_request = new CreateNewVersion();
    $request_details = $this->toRequestDetails($request);
    $reference = $this->toRequestReference($last_request, RequestReferenceIn::class);
    $linguistic_request = (new LinguisticRequestIn())
      ->setRequestDetails($request_details)
      ->setRequestReference($reference);
    $version_request->setLinguisticRequest($linguistic_request);
    $version_request->setApplicationName(static::getEpoetryApplicationName());

    return $version_request;
  }

  /**
   * Creates a modifyLinguisticRequest object from our translation request.
   *
   * The purpose is to simply add the extra languages.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequest
   *   A modify linguistic request object.
   */
  public function modifyLinguisticRequestRequest(TranslationRequestEpoetryInterface $request): ModifyLinguisticRequest {
    $modify_request = new ModifyLinguisticRequest();
    // Process the request details from the given request.
    $request_details = $this->toRequestDetails($request);
    $request_details_in = new ModifyRequestDetailsIn();
    // Extract only the request details values that we need, i.e. the products
    // and contacts.
    $request_details_in->setProducts($request_details->getProducts());
    $request_details_in->setContacts($request_details->getContacts());
    $reference = $this->toRequestReference($request, ModifyRequestReferenceIn::class);
    $linguistic_request = (new ModifyLinguisticRequestIn())
      ->setRequestDetails($request_details_in)
      ->setRequestReference($reference);
    $modify_request->setModifyLinguisticRequest($linguistic_request);
    $modify_request->setApplicationName(static::getEpoetryApplicationName());

    return $modify_request;
  }

  /**
   * Creates a addNewPartToDossierRequest object from our translation request.
   *
   * The purpose is to add a new part to an existing dossier.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\AddNewPartToDossier
   *   A request object to add a new part.
   */
  public function addNewPartToDossierRequest(TranslationRequestEpoetryInterface $request): AddNewPartToDossier {
    $add_part_request = new AddNewPartToDossier();
    $request_details_in = $this->toRequestDetails($request);

    $reference = $this->toRequestReference($request, RequestReferenceIn::class);
    $dossier = $reference->getDossier();
    $add_part_request->setDossier($dossier);
    $add_part_request->setRequestDetails($request_details_in);
    $add_part_request->setApplicationName(static::getEpoetryApplicationName());

    return $add_part_request;
  }

  /**
   * Creates a RequestDetailsIn object from a request entity.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\RequestDetailsIn
   *   The request details object.
   */
  protected function toRequestDetails(TranslationRequestEpoetryInterface $request): RequestDetailsIn {
    $entity = $request->getContentEntity();
    $plugin_configuration = $request->getTranslatorProvider()->getProviderConfiguration();
    $request_title = sprintf('%s: %s - %s', $plugin_configuration['title_prefix'], $plugin_configuration['site_id'], $entity->label());

    $request_details = new RequestDetailsIn();
    $deadline = $request->getDeadline()->getPhpDateTime();
    $deadline->setTimezone(new \DateTimeZone('Europe/Brussels'));
    // Set the end of the day in case the user picks today.
    $deadline->setTime(23, 59, 00);
    $request_details
      ->setTitle($request_title)
      ->setRequestedDeadline($deadline)
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
      ->setFileName(str_replace([' ', "'"], ['-', ''], $entity->label()) . '.html')
      ->setContent((string) $content)
      ->setLinguisticSections($linguistic_sections);
    $request_details->setOriginalDocument($original_document);

    $comment = '';
    if ($request->getMessage()) {
      $comment .= trim($request->getMessage(), '.');
    }
    if ($comment !== "") {
      $comment .= '. ';
    }
    $comment .= 'Page URL: ' . $entity->toUrl('revision')->setAbsolute()->toString();
    $request_details->setComment($comment);

    $products = new Products();
    foreach ($request->getTargetLanguages() as $language_with_status) {
      $productRequestIn = (new ProductRequestIn())
        ->setLanguage(EpoetryLanguageMapper::getEpoetryLanguageCode($language_with_status->getLangcode(), $request),)
        ->setRequestedDeadline($deadline)
        ->setTrackChanges(FALSE);
      $products->addProduct($productRequestIn);
    }

    $request_details->setProducts($products);
    $request_details->setDestination('PUBLIC');
    $request_details->setProcedure('NEANT');
    $request_details->setSlaAnnex('NO');
    $request_details->setAccessibleTo('CONTACTS');

    return $request_details;
  }

  /**
   * Creates a RequestReferenceIn object from a request entity.
   *
   * This contains the request ID without the version and is used for new
   * version requests.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The ePoetry translation request entity.
   * @param string $class
   *   The request reference class to use.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\RequestReferenceIn|ModifyRequestReferenceIn
   *   The request reference object.
   */
  protected function toRequestReference(TranslationRequestEpoetryInterface $request, string $class): RequestReferenceIn|ModifyRequestReferenceIn {
    $reference = new $class();
    $request_id = $request->getRequestId();

    $dossier = (new DossierReference())
      ->setNumber((int) $request_id['number'])
      ->setRequesterCode($request_id['code'])
      ->setYear((int) $request_id['year']);

    $reference->setProductType($request_id['service']);
    $reference->setPart((int) $request_id['part']);
    $reference->setDossier($dossier);

    return $reference;
  }

  /**
   * Returns the authentication object.
   *
   * @return \OpenEuropa\EPoetry\Authentication\AuthenticationInterface
   *   The authentication object.
   */
  protected function getAuthentication(): AuthenticationInterface {
    return $this->certificateAuthentication;
  }

}
