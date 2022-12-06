<?php

namespace Drupal\oe_translation_epoetry_mock;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\State\StateInterface;
use OpenEuropa\EPoetry\Request\Type\ContactPersonOut;
use OpenEuropa\EPoetry\Request\Type\Contacts;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequestResponse;
use OpenEuropa\EPoetry\Request\Type\DossierReference;
use OpenEuropa\EPoetry\Request\Type\LinguisticRequestOut;
use OpenEuropa\EPoetry\Request\Type\ProductRequestOut;
use OpenEuropa\EPoetry\Request\Type\Products;
use OpenEuropa\EPoetry\Request\Type\RequestDetailsOut;
use OpenEuropa\EPoetry\Request\Type\RequestReferenceOut;
use OpenEuropa\EPoetry\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mock server object for the ePoetry mock soap server.
 */
class MockServer implements ContainerInjectionInterface {

  /**
   * The path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $pathResolver;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new MockServer.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $pathResolver
   *   The path resolver.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ExtensionPathResolver $pathResolver, StateInterface $state) {
    $this->pathResolver = $pathResolver;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('state')
    );
  }

  /**
   * Returns a mock response for the createLinguisticRequest.
   *
   * @param \SimpleXMLElement $linguistic_request
   *   The linguistic request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequestResponse
   *   The linguistic request response object.
   */
  public function createLinguisticRequest(\SimpleXMLElement $linguistic_request): CreateLinguisticRequestResponse {
    $serializer = new Serializer();
    $linguistic_request = $serializer->deserialize($linguistic_request->Body->createLinguisticRequest->asXml(), CreateLinguisticRequest::class, 'xml');

    $last_request_reference = $this->state->get('oe_translation_epoetry_mock.last_request_reference', 1000);
    $new_request_reference = $last_request_reference + 1;
    $dossier = (new DossierReference())
      ->setNumber($new_request_reference)
      ->setRequesterCode('DIGIT')
      ->setYear((int) date('Y'));

    $request_reference = (new RequestReferenceOut())
      ->setDossier($dossier)
      ->setPart(00)
      ->setVersion(00)
      ->setProductType('TRA');

    $request_details_in = $linguistic_request->getRequestDetails();
    $response = new CreateLinguisticRequestResponse();
    $request_details_out = (new RequestDetailsOut())
      ->setTitle($request_details_in->getTitle())
      ->setRequestedDeadline($request_details_in->getRequestedDeadline())
      ->setSlaAnnex($request_details_in->getSlaAnnex())
      ->setProcedure($request_details_in->getProcedure())
      ->setApplicationName($linguistic_request->getApplicationName())
      ->setWorkflowCode('WEB')
      ->setSensitive(FALSE)
      ->setSentViaRue(FALSE)
      ->setAccessibleTo($request_details_in->getAccessibleTo())
      ->setStatus('SenttoDGT');

    if ($request_details_in->getComment()) {
      $request_details_out->setComment($request_details_in->getComment());
    }

    $contacts_in = $request_details_in->getContacts();
    $contacts = new Contacts();
    foreach ($contacts_in->getContact() as $contact) {
      $contacts->addContact(new ContactPersonOut('First name', 'Last name', 'text@example.com', $contact->getUserId(), $contact->getContactRole()));
    }
    $request_details_out->setContacts($contacts);

    $products_in = $request_details_in->getProducts();
    $products = new Products();
    foreach ($products_in->getProduct() as $product) {
      $products->addProduct((new ProductRequestOut())
        ->setStatus('SenttoDGT')
        ->setRequestedDeadline($product->getRequestedDeadline())
        ->setTrackChanges($product->isTrackChanges())
        ->setLanguage($product->getLanguage()));
    }
    $request_details_out->setProducts($products);

    $linguistic_request = new LinguisticRequestOut();
    $linguistic_request->setRequestDetails($request_details_out);
    $linguistic_request->setRequestReference($request_reference);

    $response->setReturn($linguistic_request);
    $this->state->set('oe_translation_epoetry_mock.last_request_reference', $new_request_reference);
    return $response;
  }

}
