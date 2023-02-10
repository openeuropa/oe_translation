<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use OpenEuropa\EPoetry\Request\Type\AddNewPartToDossier;
use OpenEuropa\EPoetry\Request\Type\AddNewPartToDossierResponse;
use OpenEuropa\EPoetry\Request\Type\ContactPersonOut;
use OpenEuropa\EPoetry\Request\Type\Contacts;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequestResponse;
use OpenEuropa\EPoetry\Request\Type\CreateNewVersion;
use OpenEuropa\EPoetry\Request\Type\CreateNewVersionResponse;
use OpenEuropa\EPoetry\Request\Type\DossierReference;
use OpenEuropa\EPoetry\Request\Type\LinguisticRequestOut;
use OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequest;
use OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequestResponse;
use OpenEuropa\EPoetry\Request\Type\ProductRequestOut;
use OpenEuropa\EPoetry\Request\Type\Products;
use OpenEuropa\EPoetry\Request\Type\RequestDetailsIn;
use OpenEuropa\EPoetry\Request\Type\RequestDetailsOut;
use OpenEuropa\EPoetry\Request\Type\RequestReferenceOut;
use OpenEuropa\EPoetry\Request\Type\ResubmitRequest;
use OpenEuropa\EPoetry\Request\Type\ResubmitRequestResponse;
use OpenEuropa\EPoetry\Serializer\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MockServer.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $pathResolver
   *   The path resolver.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ExtensionPathResolver $pathResolver, StateInterface $state, EntityTypeManagerInterface $entityTypeManager) {
    $this->pathResolver = $pathResolver;
    $this->state = $state;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('state'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns a mock response for the createLinguisticRequest.
   *
   * @param \SimpleXMLElement $xml_request
   *   The linguistic request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequestResponse
   *   The linguistic request response object.
   */
  public function createLinguisticRequest(\SimpleXMLElement $xml_request): CreateLinguisticRequestResponse {
    $serializer = new Serializer();
    /** @var \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest $linguistic_request */
    $linguistic_request = $serializer->deserialize($xml_request->Body->createLinguisticRequest->asXml(), CreateLinguisticRequest::class, 'xml');

    $last_request_reference = $this->state->get('oe_translation_epoetry_mock.last_request_reference', 1000);
    $new_request_reference = $last_request_reference + 1;
    $dossier = (new DossierReference())
      ->setNumber($new_request_reference)
      ->setRequesterCode('DIGIT')
      ->setYear((int) date('Y'));

    $request_reference = (new RequestReferenceOut())
      ->setDossier($dossier)
      ->setPart(0)
      ->setVersion(0)
      ->setProductType('TRA');

    $request_details_in = $linguistic_request->getRequestDetails();
    $request_details_out = $this->toRequestDetailsOut($request_details_in, $linguistic_request->getApplicationName());

    $linguistic_request = new LinguisticRequestOut();
    $linguistic_request->setRequestDetails($request_details_out);
    $linguistic_request->setRequestReference($request_reference);

    $response = new CreateLinguisticRequestResponse();
    $response->setReturn($linguistic_request);
    $this->state->set('oe_translation_epoetry_mock.last_request_reference', $new_request_reference);
    return $response;
  }

  /**
   * Returns a mock response for the resubmitRequest.
   *
   * @param \SimpleXMLElement $xml_request
   *   The linguistic request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\ResubmitRequestResponse
   *   The linguistic request response object.
   */
  public function resubmitRequest(\SimpleXMLElement $xml_request): ResubmitRequestResponse {
    $serializer = new Serializer();
    /** @var \OpenEuropa\EPoetry\Request\Type\ResubmitRequest $resubmit_request */
    $resubmit_request = $serializer->deserialize($xml_request->Body->resubmitRequest->asXml(), ResubmitRequest::class, 'xml');

    $request_reference_in = $resubmit_request->getResubmitRequest()->getRequestReference();
    $dossier_in = $request_reference_in->getDossier();

    $request_details_in = $resubmit_request->getResubmitRequest()->getRequestDetails();
    $request_details_out = $this->toRequestDetailsOut($request_details_in, $resubmit_request->getApplicationName());

    // Find the rejected translation request so we can determine the version.
    $ids = $this->entityTypeManager->getStorage('oe_translation_request')->getQuery()
      ->condition('bundle', 'epoetry')
      ->condition('request_id.code', $dossier_in->getRequesterCode())
      ->condition('request_id.year', $dossier_in->getYear())
      ->condition('request_id.number', $dossier_in->getNumber())
      ->condition('request_id.part', $request_reference_in->getPart())
      ->condition('epoetry_status', TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED)
      ->sort('id', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      throw new NotFoundHttpException();
    }

    $id = reset($ids);
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
    $request_id = $request->getRequestId();

    $dossier = (new DossierReference())
      ->setNumber($dossier_in->getNumber())
      ->setRequesterCode($dossier_in->getRequesterCode())
      ->setYear($dossier_in->getYear());

    $request_reference = (new RequestReferenceOut())
      ->setDossier($dossier)
      ->setPart($request_reference_in->getPart())
      ->setProductType('TRA')
      ->setVersion((int) $request_id['version']);

    $linguistic_request = new LinguisticRequestOut();
    $linguistic_request->setRequestDetails($request_details_out);
    $linguistic_request->setRequestReference($request_reference);

    $response = new ResubmitRequestResponse();
    $response->setReturn($linguistic_request);

    return $response;
  }

  /**
   * Returns a mock response for the createNewVersion request.
   *
   * @param \SimpleXMLElement $xml_request
   *   The linguistic request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\CreateNewVersionResponse
   *   The linguistic request response object.
   */
  public function createNewVersion(\SimpleXMLElement $xml_request): CreateNewVersionResponse {
    $serializer = new Serializer();

    /** @var \OpenEuropa\EPoetry\Request\Type\CreateNewVersion $version_request */
    $version_request = $serializer->deserialize($xml_request->Body->createNewVersion->asXml(), CreateNewVersion::class, 'xml');
    $linguistic_request_in = $version_request->getLinguisticRequest();
    $reference_in = $linguistic_request_in->getRequestReference();
    $request_details_in = $linguistic_request_in->getRequestDetails();
    $dossier_in = $reference_in->getDossier();

    // Get from state the previous request versions so that we mimic how ePoetry
    // keeps track and increments the versions.
    $system_request_versions = $this->state->get('oe_translation_epoetry_mock.request_versions', []);
    $id = implode('/', [
      $dossier_in->getRequesterCode(),
      $dossier_in->getYear(),
      $dossier_in->getNumber(),
      $reference_in->getPart(),
      $reference_in->getProductType(),
    ]);
    $last_entity_version = $system_request_versions[$id] ?? 0;
    $new_entity_version = $last_entity_version + 1;
    $system_request_versions[$id] = $new_entity_version;
    $this->state->set('oe_translation_epoetry_mock.request_versions', $system_request_versions);

    $reference_out = (new RequestReferenceOut())
      ->setDossier($reference_in->getDossier())
      ->setPart($reference_in->getPart())
      ->setVersion($new_entity_version)
      ->setProductType('TRA');

    $request_details_out = $this->toRequestDetailsOut($request_details_in, $version_request->getApplicationName());

    $linguistic_request = new LinguisticRequestOut();
    $linguistic_request->setRequestDetails($request_details_out);
    $linguistic_request->setRequestReference($reference_out);

    $response = new CreateNewVersionResponse();
    $response->setReturn($linguistic_request);

    return $response;
  }

  /**
   * Returns a mock response for the modifyLinguisticRequest request.
   *
   * @param \SimpleXMLElement $xml_request
   *   The linguistic request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequestResponse
   *   The response.
   */
  public function modifyLinguisticRequest(\SimpleXMLElement $xml_request): ModifyLinguisticRequestResponse {
    $serializer = new Serializer();

    /** @var \OpenEuropa\EPoetry\Request\Type\ModifyLinguisticRequest $request_in */
    $request_in = $serializer->deserialize($xml_request->Body->modifyLinguisticRequest->asXml(), ModifyLinguisticRequest::class, 'xml');
    $modify_linguistic_request_in = $request_in->getModifyLinguisticRequest();
    $reference_in = $modify_linguistic_request_in->getRequestReference();
    $request_details_in = $modify_linguistic_request_in->getRequestDetails();
    $dossier_in = $reference_in->getDossier();

    $reference_out = (new RequestReferenceOut())
      ->setDossier($reference_in->getDossier())
      ->setPart($reference_in->getPart())
      // Normally, ePoetry should return the same version as we have so we
      // just set 0 here as we won't update the version when the request
      // comes back to drupal.
      ->setVersion(0)
      ->setProductType('TRA');

    $ids = $this->entityTypeManager->getStorage('oe_translation_request')->getQuery()
      ->condition('bundle', 'epoetry')
      ->condition('request_id.code', $dossier_in->getRequesterCode())
      ->condition('request_id.year', $dossier_in->getYear())
      ->condition('request_id.number', $dossier_in->getNumber())
      ->condition('request_id.part', $reference_in->getPart())
      ->condition('epoetry_status', TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED)
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      throw new NotFoundHttpException();
    }

    // We expect this to be here.
    $id = reset($ids);
    $request_entity = $this->entityTypeManager->getStorage('oe_translation_request')->load($id);

    // Create an original linguistic request from the found entity so we can
    // extract outbound details from it.
    /** @var \OpenEuropa\EPoetry\Request\Type\CreateLinguisticRequest $create_linguistic_request_in */
    $create_linguistic_request_in = \Drupal::service('oe_translation_epoetry.request_factory')->createLinguisticRequest($request_entity);
    $request_details_out = $this->toRequestDetailsOut($create_linguistic_request_in->getRequestDetails(), $create_linguistic_request_in->getApplicationName());

    // Add the extra language.
    foreach ($request_details_in->getProducts()->getProduct() as $product) {
      $request_details_out->getProducts()->addProduct((new ProductRequestOut())
        ->setStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT)
        ->setRequestedDeadline($product->getRequestedDeadline())
        ->setTrackChanges($product->isTrackChanges())
        ->setLanguage($product->getLanguage()));
    }

    $linguistic_request = new LinguisticRequestOut();
    $linguistic_request->setRequestDetails($request_details_out);
    $linguistic_request->setRequestReference($reference_out);

    $response = new ModifyLinguisticRequestResponse();
    $response->setReturn($linguistic_request);

    return $response;
  }

  /**
   * Returns a mock response for the AddNewPartToDossier request.
   *
   * @param \SimpleXMLElement $xml_request
   *   The linguistic request.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\AddNewPartToDossierResponse
   *   The response.
   */
  public function addNewPartToDossier(\SimpleXMLElement $xml_request): AddNewPartToDossierResponse {
    $serializer = new Serializer();

    /** @var \OpenEuropa\EPoetry\Request\Type\AddNewPartToDossier $request_in */
    $request_in = $serializer->deserialize($xml_request->Body->addNewPartToDossier->asXml(), AddNewPartToDossier::class, 'xml');

    $number = $request_in->getDossier()->getNumber();
    $last_part_for_number = $this->state->get('oe_translation_epoetry_mock.last_part_for_number', []);
    $last_part = $last_part_for_number[$number] ?? 0;
    $new_part = $last_part + 1;

    $dossier = (new DossierReference())
      ->setNumber($request_in->getDossier()->getNumber())
      ->setRequesterCode($request_in->getDossier()->getRequesterCode())
      ->setYear($request_in->getDossier()->getYear());

    $request_reference = (new RequestReferenceOut())
      ->setDossier($dossier)
      ->setPart($new_part)
      ->setVersion(0)
      ->setProductType('TRA');

    $request_details_in = $request_in->getRequestDetails();
    $request_details_out = $this->toRequestDetailsOut($request_details_in, $request_in->getApplicationName());

    $linguistic_request = new LinguisticRequestOut();
    $linguistic_request->setRequestDetails($request_details_out);
    $linguistic_request->setRequestReference($request_reference);

    $response = new AddNewPartToDossierResponse();
    $response->setReturn($linguistic_request);

    $last_part_for_number[$number] = $new_part;
    $this->state->set('oe_translation_epoetry_mock.last_part_for_number', $last_part_for_number);

    return $response;
  }

  /**
   * Creates a RequestDetailsOut object from a RequestDetailsIn.
   *
   * @param \OpenEuropa\EPoetry\Request\Type\RequestDetailsIn $request_details_in
   *   The inbound request details object.
   * @param string $application_name
   *   The application name.
   *
   * @return \OpenEuropa\EPoetry\Request\Type\RequestDetailsOut
   *   The outbound request details.
   */
  protected function toRequestDetailsOut(RequestDetailsIn $request_details_in, string $application_name): RequestDetailsOut {
    $request_details_out = (new RequestDetailsOut())
      ->setTitle($request_details_in->getTitle())
      ->setRequestedDeadline($request_details_in->getRequestedDeadline())
      ->setSlaAnnex($request_details_in->getSlaAnnex())
      ->setProcedure($request_details_in->getProcedure())
      ->setApplicationName($application_name)
      ->setWorkflowCode('WEB')
      ->setSensitive(FALSE)
      ->setSentViaRue(FALSE)
      ->setAccessibleTo($request_details_in->getAccessibleTo())
      ->setStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT);

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
        ->setStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT)
        ->setRequestedDeadline($product->getRequestedDeadline())
        ->setTrackChanges($product->isTrackChanges())
        ->setLanguage($product->getLanguage()));
    }
    $request_details_out->setProducts($products);

    return $request_details_out;
  }

}
