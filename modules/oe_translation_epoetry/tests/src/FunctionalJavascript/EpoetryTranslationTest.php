<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_epoetry\FunctionalJavascript;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\node\Entity\Node;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_epoetry\RequestFactory;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_epoetry_mock\EpoetryTranslationMockHelper;
use Drupal\oe_translation_epoetry_mock\Logger\MockLogger;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_epoetry\EpoetryTranslationTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;

/**
 * Tests the remote translations via ePoetry.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class EpoetryTranslationTest extends TranslationTestBase {

  use TranslationsTestTrait;
  use RemoteTranslationsTestTrait;
  use EpoetryTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'paragraphs',
    'entity_reference_revisions',
    'menu_link_content',
    'oe_translation',
    'oe_translation_test',
    'oe_translation_remote',
    'oe_translation_epoetry',
    'oe_translation_epoetry_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $provider = RemoteTranslatorProvider::load('epoetry');
    $configuration = $provider->getProviderConfiguration();
    $configuration['title_prefix'] = 'A title prefix';
    $configuration['site_id'] = 'A site ID';
    $configuration['auto_accept'] = FALSE;
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
  }

  /**
   * Tests the ePoetry translation provider configuration form.
   */
  public function testEpoetryProviderConfiguration(): void {
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/remote-translation-provider');
    $this->assertSession()->pageTextContains('Remote Translator Provider entities');
    $this->clickLink('Add Remote Translator Provider');
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'ePoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for ePoetry');

    // Assert we have the contact form elements.
    $contact_fields = [
      'Author',
      'Requester',
      'Recipient',
      'Editor',
      'Webmaster',
    ];
    foreach ($contact_fields as $field) {
      $this->assertSession()->fieldExists($field);
    }

    // Assert we have the auto-accept checkbox.
    $this->assertSession()->fieldExists('Auto-accept translations');

    // Fill in the first 3 contact types.
    $this->getSession()->getPage()->fillField('Recipient', 'test_recipient');
    $this->getSession()->getPage()->fillField('Webmaster', 'test_webmaster');
    $this->getSession()->getPage()->fillField('Editor', 'test_editor');

    // Check the box for auto-accepting.
    $this->getSession()->getPage()->checkField('Auto-accept translations');

    // Set the title prefix and site ID.
    $this->getSession()->getPage()->fillField('Request title prefix', 'The title prefix');
    $this->getSession()->getPage()->fillField('Site ID', 'The site ID');

    // Create a new ePoetry provider.
    $this->getSession()->getPage()->fillField('Name', 'ePoetry provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'epoetry_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the ePoetry provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('epoetry_provider');
    $this->assertEquals('ePoetry provider', $translator->label());
    $this->assertEquals('epoetry', $translator->getProviderPlugin());
    $this->assertEquals([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => TRUE,
      'title_prefix' => 'The title prefix',
      'site_id' => 'The site ID',
    ], $translator->getProviderConfiguration());

    // Edit the provider.
    $this->drupalGet($translator->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('Name', 'ePoetry provider');
    $this->assertSession()->fieldValueEquals('Recipient', 'test_recipient');
    $this->assertSession()->fieldValueEquals('Webmaster', 'test_webmaster');
    $this->assertSession()->fieldValueEquals('Editor', 'test_editor');
    $this->assertSession()->fieldValueEquals('Request title prefix', 'The title prefix');
    $this->assertSession()->fieldValueEquals('Site ID', 'The site ID');
    $this->getSession()->getPage()->fillField('Name', 'ePoetry provider edited');
    $this->getSession()->getPage()->uncheckField('Auto-accept translations');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the ePoetry provider edited Remote Translator Provider.');
    $translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('epoetry_provider');
    $this->assertEquals([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => FALSE,
      'title_prefix' => 'The title prefix',
      'site_id' => 'The site ID',
    ], $translator->getProviderConfiguration());
  }

  /**
   * Tests that some configuration is pre-set from the provider configuration.
   */
  public function testEpoetryPresetTranslationForm(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->selectFieldOption('Translator', 'ePoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    // The contacts fields are empty and the auto-accept checkbox is enabled
    // because the provider is not yet configured.
    $this->assertSession()->fieldEnabled('Auto accept translations');
    $this->assertSession()->elementTextEquals('css', '.form-item-translator-configuration-epoetry-auto-accept-value .description', 'Choose if incoming translations should be auto-accepted');
    $this->assertSession()->fieldExists('Automatic synchronisation');
    // The deadline field has a "Date" hidden label.
    $this->assertSession()->fieldExists('Date');
    $this->assertSession()->fieldExists('Message');
    $contact_fields = [
      'Author' => FALSE,
      'Requester' => FALSE,
      'Recipient' => TRUE,
      'Editor' => TRUE,
      'Webmaster' => TRUE,
    ];
    foreach ($contact_fields as $field => $exists) {
      if ($exists) {
        $this->assertSession()->fieldValueEquals($field, '');
      }
      else {
        $this->assertSession()->fieldNotExists($field);
      }
    }

    // Update the provider configuration to provide default contacts and
    // pre-configure the auto-accept.
    $translator = RemoteTranslatorProvider::load('epoetry');
    $translator->setProviderConfiguration([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => TRUE,
    ]);
    $translator->save();

    // Now the contact fields are pre-filled and the auto-accept checkbox
    // is disabled.
    $this->getSession()->reload();
    $this->getSession()->getPage()->selectFieldOption('Translator', 'ePoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->assertSession()->fieldValueEquals($field, $value);
    }
    $this->assertSession()->fieldDisabled('Auto accept translations');
    $this->assertSession()->elementTextEquals('css', '.form-item-translator-configuration-epoetry-auto-accept-value .description', 'Choose if incoming translations should be auto-accepted. The auto-accept feature is enabled at site level. All requests will be auto-accepted.');
  }

  /**
   * Tests the main remote translation flow using ePoetry.
   *
   * This is similar to RemoteTranslationTest::testSingleTranslationFlow but
   * simplified and focusing more on the ePoetry specific aspects.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testEpoetrySingleTranslationFlow(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Select the ePoetry translator.
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    // Pick a deadline, contacts and a message.
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    // Assert the languages' validation.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one language.');

    // Select 2 languages.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('French');

    // Assert the message length is under 1700 characters.
    $this->getSession()->getPage()->fillField('Message', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cup');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please keep the message under 1700 characters.');

    // Put a smaller message and send.
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 1</internalReference><requestedDeadline>2032-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><comment>http://web:8080/build/en/basic-translation-node</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMSIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDE8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0xIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzFdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTVYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+00:00" trackChanges="false"><language>bg</language></product><product requestedDeadline="2032-10-10T23:59:00+00:00" trackChanges="false"><language>fr</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Active'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'Active'), $target_languages['fr']);
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getAcceptedDeadline());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2032-10-10', $request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Message to the provider', $request->getMessage());
    $this->assertEquals('DIGIT/' . date('Y') . '/1001/0/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/1001/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
      // The mock link tu accept the request.
      'Accept',
    ]);

    // Assert the message.
    $this->assertSession()->pageTextContains('Message to provider');
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the createLinguisticRequest request type. The dossier number started is 1001.',
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your createLinguisticRequest. We will process it soon.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert that we are storing the generated dossier in the state.
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Accept the request and the FR language.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ]);
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'fr',
    ]);

    $this->getSession()->reload();
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/1001/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
      // The mock link tu cancel the request since it's been Accepted.
      'Cancel',
    ]);
    $expected_languages['fr']['status'] = 'Accepted';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the extra logs.
    $expected_logs[3] = [
      'Info',
      'The request has been Accepted by ePoetry. Planning agent: test. Planning sector: DGT. Message: The request status has been changed to Accepted.',
    ];
    $expected_logs[4] = [
      'Info',
      'The French product status has been updated to Accepted.',
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Send the translation.
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $this->getSession()->reload();
    $expected_languages['fr']['status'] = 'Review';
    $expected_languages['fr']['review'] = TRUE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the extra logs.
    $expected_logs[5] = [
      'Info',
      'The French translation has been delivered.',
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Sync the translation (first accept it).
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->drupalGet($request->toUrl());
    $expected_logs[6] = [
      'Info',
      'The French translation has been accepted.',
    ];
    $this->assertLogMessagesTable($expected_logs);
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronize');
    $this->assertSession()->pageTextContains('The translation in French has been synchronized.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    $expected_languages['fr']['status'] = 'Synchronized';
    $expected_languages['fr']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $expected_logs[7] = [
      'Info',
      'The French translation has been synchronised with the content.',
    ];
    $this->assertLogMessagesTable($expected_logs);
    $node = Node::load($node->id());
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertEquals('Basic translation node - fr', $node->getTranslation('fr')->label());
  }

  /**
   * Tests the that we are creating new parts when we translate new nodes.
   *
   * This tests that when we translate nodes, we add parts to the same dossier
   * instead of always making a new linguistic requests. And that the parts
   * only go up to 30.
   */
  public function testsAddPartsTranslationFlow(): void {
    // Create a node and mimic it having been translated via ePoetry.
    $first_node = $this->createBasicTestNode();
    $first_node->addTranslation('fr', ['title' => 'Basic translation node fr'] + $first_node->toArray());
    $first_node->setNewRevision(FALSE);
    $first_node->save();
    $request = $this->createNodeTranslationRequest($first_node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      ],
    ]);
    $request->save();
    // Mimic the setting in state of the newly created dossier.
    $dossiers[2000] = [
      'part' => 0,
      'code' => 'DIGIT',
      'year' => date('Y'),
    ];
    // Mimic that the above request has been made in the UI and store the
    // resulting dossier and mock last requested reference number.
    RequestFactory::setEpoetryDossiers($dossiers);
    \Drupal::state()->set('oe_translation_epoetry_mock.last_request_reference', 2000);

    // Now create another node and start translating it.
    $second_node = $this->createBasicTestNode();
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct and it's using the
    // addNewPart.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 2</internalReference><requestedDeadline>2032-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><comment>http://web:8080/build/en/basic-translation-node-0</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+00:00" trackChanges="false"><language>fr</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that the state dossier got updated correctly.
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      2000 => [
        'part' => 1,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Assert the log messages.
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the addNewPartToDossierRequest request type.',
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your addNewPartToDossier. We will process it soon.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Now create yet another node and translate it. The parts should increment.
    $third_node = $this->createBasicTestNode();
    $this->drupalGet($third_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(2, $requests);
    $xml = end($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 3</internalReference><requestedDeadline>2032-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><comment>http://web:8080/build/en/basic-translation-node-1</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMyIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDM8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0zIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzNdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTTExYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+00:00" trackChanges="false"><language>de</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      2000 => [
        'part' => 2,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Mimic that we have reached to part 30 and the dossier should be reset.
    $dossiers[2000]['part'] = 30;
    RequestFactory::setEpoetryDossiers($dossiers);

    // Create a new node and send a translation request.
    $fourth_node = $this->createBasicTestNode();
    $this->drupalGet($fourth_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('Italian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct and it's using the
    // createNewLinguisticRequest.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(3, $requests);
    $xml = end($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 4</internalReference><requestedDeadline>2032-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><comment>http://web:8080/build/en/basic-translation-node-2</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iNCIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDQ8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS00Ij4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzRdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTkYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+00:00" trackChanges="false"><language>it</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      2000 => [
        'part' => 30,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
      2001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);
  }

  /**
   * Tests that after a translation is made, we make a new version request.
   */
  public function testEpoetryNewVersionRequest(): void {
    // Create a node and mimic it having been translated via ePoetry.
    $node = $this->createBasicTestNode();
    $node->addTranslation('fr', ['title' => 'Basic translation node fr'] + $node->toArray());
    $node->setNewRevision(FALSE);
    $node->save();
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      ],
    ]);
    $request->save();

    // Make a new draft of the node and start a new translation.
    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Basic translation node'],
      'fr' => ['title' => 'Basic translation node fr'],
    ]);

    $this->clickLink('Remote translations');
    $this->assertSession()->fieldEnabled('Translator')->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));

    // Fill in request details and submit.
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->checkField('Italian');

    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2034');
    $this->getSession()->getPage()->fillField('Message', 'Some message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $year = date('Y');
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createNewVersion><linguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . $year . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node - update</title><internalReference>Translation request 2</internalReference><requestedDeadline>2034-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Some message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient2" contactRole="RECIPIENT"/><contact userId="test_webmaster2" contactRole="WEBMASTER"/><contact userId="test_editor2" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node---update.html</fileName><comment>http://web:8080/build/en/basic-translation-node-update</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlIC0gdXBkYXRlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2034-10-10T23:59:00+00:00" trackChanges="false"><language>de</language></product><product requestedDeadline="2034-10-10T23:59:00+00:00" trackChanges="false"><language>it</language></product></products></requestDetails></linguisticRequest><applicationName>digit</applicationName></ns1:createNewVersion></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);
    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $update_request = reset($requests);
    $this->assertNotEquals($request->id(), $update_request->id());
    // The old request didn't get its status changed by this new update.
    $this->assertEquals('Executed', $request->getEpoetryRequestStatus());
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $update_request);
    $this->assertEquals('en', $update_request->getSourceLanguageCode());
    $target_languages = $update_request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('de', 'Active'), $target_languages['de']);
    $this->assertEquals(new LanguageWithStatus('it', 'Active'), $target_languages['it']);
    $this->assertEquals('Active', $update_request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $update_request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $update_request->getTranslatorProvider()->id());
    $this->assertNull($update_request->getAcceptedDeadline());
    $this->assertFalse($update_request->isAutoAccept());
    $this->assertFalse($update_request->isAutoSync());
    $this->assertEquals('2034-10-10', $update_request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Some message to the provider', $update_request->getMessage());
    // The version got updated, not the number.
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/1/0/TRA', $update_request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ], $update_request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      // The version got updated, not the number.
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      'N/A',
      // The mock link to Accept.
      'Accept',
    ]);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['de', 'it'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the log messages.
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the createNewVersionRequest request type.',
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your createNewVersionRequest. We will process it soon.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the access to create a new version for ongoing requests.
   *
   * In essence, tests that all criteria are met before a user can make a
   * createNewVersion request for an ongoing translation request. It doesn't
   * cover the cases in which a given request has already been translated by
   * ePoetry.
   */
  public function testAccessOngoingCreateVersion(): void {
    $cases = [
      'no new draft, sent to DGT request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'no new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
      'new draft, suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode();

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
      if ($case_info['finished']) {
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED);
      }
      $request->save();

      // Make a new draft if needed.
      if ($case_info['create draft']) {
        $node->set('title', 'Basic translation node - update - ' . $case);
        $node->setNewRevision(TRUE);
        $node->save();
      }

      $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
      $this->clickLink('Remote translations');

      if ($case_info['ongoing']) {
        $this->assertSession()->pageTextContains('Ongoing remote translation request via ePoetry');
      }
      else {
        $this->assertSession()->pageTextNotContains('Ongoing remote translation request via ePoetry');
      }

      if ($case_info['visible']) {
        $this->assertSession()->pageTextContains('Request an update');
        $this->assertSession()->linkExistsExact('Update');

        // If we are on the case where we should see the Update button, assert
        // also that if the request is not active anymore, we don't see it.
        foreach (['Translated', 'Finished', 'Failed'] as $status) {
          $request->setRequestStatus($status);
          $request->save();
        }

        $this->getSession()->reload();
        $this->assertSession()->pageTextNotContains('Request an update');
        $this->assertSession()->linkNotExistsExact('Update');

        // Set back the active status for the next iteration.
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE);
      }
      else {
        $this->assertSession()->pageTextNotContains('Request an update');
        $this->assertSession()->linkNotExistsExact('Update');
      }

      $node->delete();
    }
  }

  /**
   * Tests the creation of a new version for ongoing requests.
   */
  public function testOngoingCreateVersion(): void {
    // Create a node and its active, ongoing, translation.
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    $this->assertSession()->pageTextContains('Request an update');
    $this->assertSession()->linkExistsExact('Update');
    $this->clickLink('Update');
    $this->assertSession()->addressEquals('/en/translation-request/epoetry/new-version-request/' . $request->id());
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));

    // Make some changes to the request: new language, request message,
    // different date.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->checkField('Italian');

    $this->getSession()->getPage()->fillField('deadline[0][value][date]', '10/10/2034');
    $this->getSession()->getPage()->fillField('Message', 'Some message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $this->assertSession()->pageTextContains(sprintf('The old request with the id %s has been marked as Finished.', $request->getRequestId(TRUE)));

    // We are back where we came from.
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // We see still only one request, the new one, as the previous one was
    // marked as finished.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      // The version got updated, not the number.
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      'N/A',
      // The mock link to Accept.
      'Accept',
    ]);

    // Assert the message.
    $this->assertSession()->pageTextContains('Some message to the provider');

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'de', 'it'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert that the original request has been marked as finished.
    $request = TranslationRequestEpoetry::load($request->id());
    $this->assertEquals('Finished', $request->getRequestStatus());
    // The ePoetry status didn't get changed.
    $this->assertEquals('Accepted', $request->getEpoetryRequestStatus());

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $year = date('Y');
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createNewVersion><linguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . $year . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node - update</title><internalReference>Translation request 2</internalReference><requestedDeadline>2034-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Some message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient2" contactRole="RECIPIENT"/><contact userId="test_webmaster2" contactRole="WEBMASTER"/><contact userId="test_editor2" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node---update.html</fileName><comment>http://web:8080/build/en/basic-translation-node-update</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlIC0gdXBkYXRlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2034-10-10T23:59:00+00:00" trackChanges="false"><language>bg</language></product><product requestedDeadline="2034-10-10T23:59:00+00:00" trackChanges="false"><language>de</language></product><product requestedDeadline="2034-10-10T23:59:00+00:00" trackChanges="false"><language>it</language></product></products></requestDetails></linguisticRequest><applicationName>digit</applicationName></ns1:createNewVersion></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $update_request = reset($requests);
    $this->assertNotEquals($request->id(), $update_request->id());
    // The old request got its status changed by this new update.
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $update_request);
    $this->assertEquals('en', $update_request->getSourceLanguageCode());
    $target_languages = $update_request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Active'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('de', 'Active'), $target_languages['de']);
    $this->assertEquals(new LanguageWithStatus('it', 'Active'), $target_languages['it']);
    $this->assertEquals('Active', $update_request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $update_request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $update_request->getTranslatorProvider()->id());
    $this->assertNull($update_request->getAcceptedDeadline());
    $this->assertFalse($update_request->isAutoAccept());
    $this->assertFalse($update_request->isAutoSync());
    $this->assertEquals('2034-10-10', $update_request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Some message to the provider', $update_request->getMessage());
    // The version got updated, not the number.
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/1/0/TRA', $update_request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ], $update_request->getContacts());

    // Assert we see information about the request we replaced.
    $old_request_id = 'DIGIT/' . date('Y') . '/2000/0/0/TRA';
    $this->assertSession()->pageTextContains('Updated request');
    $this->assertSession()->pageTextContains('The current request was created as an update to a previous one (' . $old_request_id . ') which was still ongoing in ePoetry with at least 1 language. That request has been marked as Finished and was replaced with the current one.');
    $this->clickLink($old_request_id);
    // Assert that on the old request, we see the log message pointing to the
    // new one.
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('This request has been marked as Finished and has been replaced by an updated one: New request');
    // Go back to where we were.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Assert the logs.
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the createNewVersionRequest request type.',
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your createNewVersionRequest. We will process it soon.',
      ],
      3 => [
        'Info',
        'The request has replaced an ongoing translation request which has now been marked as Finished: ' . $old_request_id . '.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Now test that we can make update requests to ongoing requests even if
    // we have already a translated request (not yet synced).
    // Start by translating directly this new request so we have a translated
    // request.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($update_request, 'bg');
    EpoetryTranslationMockHelper::translateRequest($update_request, 'de');
    EpoetryTranslationMockHelper::translateRequest($update_request, 'it');

    $this->getSession()->reload();
    $this->assertRequestStatusTable([
      'Translated',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      'N/A',
    ]);

    // Next, create a new request, accept it from DGT and then make an update
    // to this request.
    $this->assertSession()->selectExists('Translator')->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $expected_ongoing_one = [
      'translator' => 'ePoetry',
      'status' => 'Translated',
      'title' => 'Basic translation node - update',
      'title_url' => $node->toUrl('revision')->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $expected_ongoing_two = $expected_ongoing_one;
    // They are both for the same revision, except the second one is Active.
    $expected_ongoing_two['status'] = 'Active';
    $this->assertOngoingTranslations([
      $expected_ongoing_one,
      $expected_ongoing_two,
    ]);

    // Accept this request, make a new draft and make an update request.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE, [TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED]);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->getSession()->getPage()->find('xpath', "//tr[td//text()[contains(., 'Active')]]")->clickLink('View');
    $this->assertSession()->linkNotExistsExact('Update');
    // Accept the request.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ]);
    // Keep track of the old revision ID.
    $revision_id = $node->getRevisionId();
    $node->set('title', 'Basic translation node - second update');
    $node->setNewRevision(TRUE);
    $node->save();
    $this->getSession()->reload();
    $this->assertSession()->linkExistsExact('Update');
    $this->clickLink('Update');
    $this->assertSession()->addressEquals('/en/translation-request/epoetry/new-version-request/' . $request->id());
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('deadline[0][value][date]', '10/10/2033');
    $this->getSession()->getPage()->fillField('Message', 'Another message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient3',
      'Webmaster' => 'test_webmaster3',
      'Editor' => 'test_editor3',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $this->assertSession()->pageTextContains(sprintf('The old request with the id %s has been marked as Finished.', $request->getRequestId(TRUE)));
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // We still have the translated request and now a new, updated request for
    // the new content version.
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($revision_id);
    $expected_ongoing_one['is_default'] = 'No';
    $expected_ongoing_one['title_url'] = $revision->toUrl('revision')->toString();

    $expected_ongoing_two['title'] = 'Basic translation node - second update';
    $expected_ongoing_two['title_url'] = $node->toUrl()->toString();
    $expected_ongoing_two['revision'] = $node->getRevisionId();

    $this->assertOngoingTranslations([
      $expected_ongoing_one,
      $expected_ongoing_two,
    ]);
  }

  /**
   * Tests the access to modify an ongoing request, i.e. add languages.
   */
  public function testAccessOngoingModifyRequest(): void {
    $cases = [
      'sent to DGT request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
      'suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode();

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
      if ($case_info['finished']) {
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED);
      }
      $request->save();

      $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
      $this->clickLink('Remote translations');

      if ($case_info['ongoing']) {
        $this->assertSession()->pageTextContains('Ongoing remote translation request via ePoetry');
      }
      else {
        $this->assertSession()->pageTextNotContains('Ongoing remote translation request via ePoetry');
      }

      if ($case_info['visible']) {
        $this->assertSession()->pageTextContains('Add new languages');
        $this->assertSession()->linkExistsExact('Add new languages');

        // If we are on the case where we should see the add languages button,
        // assert also that if the request is not active anymore, we don't
        // see it.
        foreach (['Translated', 'Finished', 'Failed'] as $status) {
          $request->setRequestStatus($status);
          $request->save();
        }

        $this->getSession()->reload();
        $this->assertSession()->pageTextNotContains('Add new languages');
        $this->assertSession()->linkNotExistsExact('Add new languages');

        // Set back the active status for the next iteration.
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE);
      }
      else {
        $this->assertSession()->pageTextNotContains('Add new languages');
        $this->assertSession()->linkNotExistsExact('Add new languages');
      }

      $node->delete();
    }
  }

  /**
   * Tests that we can add new languages to an Accepted request.
   */
  public function testModifyLinguisticRequest(): void {
    // Create a node and its active, ongoing, translation.
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Assert our Accepted request status.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'N/A',
      'Cancel',
    ]);

    // We have a single language.
    $expected_languages = [];
    foreach (['fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert, though, that we can add new languages.
    $this->assertSession()->pageTextContains('Add new languages');
    $this->assertSession()->linkExistsExact('Add new languages');
    $this->clickLink('Add new languages');
    $this->assertSession()->addressEquals('/en/translation-request/epoetry/modify-linguistic-request/' . $request->id());
    $this->assertSession()->pageTextContains(sprintf('You are making a request to add extra languages to an existing, ongoing request with the ID %s.', $request->getRequestId(TRUE)));

    // Assert that FR is disabled and checked because the ongoing request
    // is for FR.
    $this->assertSession()->fieldDisabled('French');
    $this->assertSession()->checkboxChecked('French');
    // Add another language and submit the request.
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // We are back where we came from.
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // Our request should stay unchanged, apart from the new language addition.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'N/A',
      'Cancel',
    ]);

    // We now have 2 translations.
    $expected_languages = [];
    foreach (['fr', 'de'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert we only have 1 request and we have no change on it except the
    // language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, FALSE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('fr', 'Active'), $target_languages['fr']);
    $this->assertEquals(new LanguageWithStatus('de', 'Active'), $target_languages['de']);
    $this->assertCount(2, $target_languages);
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('Accepted', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getAcceptedDeadline());
    $this->assertNull($request->getMessage());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2035-10-10', $request->getDeadline()->format('Y-m-d'));
    // The version got updated, not the number.
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:modifyLinguisticRequest><modifyLinguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><contacts xsi:type="ns1:contacts"><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><products xsi:type="ns1:products"><product requestedDeadline="2035-10-10T23:59:00+00:00" trackChanges="false"><language>de</language></product></products></requestDetails></modifyLinguisticRequest><applicationName>digit</applicationName></ns1:modifyLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert the logs. This set of logs is missing the initial ones because
    // we created the request programatically.
    $expected_logs = [
      1 => [
        'Info',
        'The modifyLinguisticRequest has been sent to ePoetry for adding the following extra languages: German.',
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your modifyLinguisticRequest. We will process it soon.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the request notifications subscriber.
   */
  public function testRequestNotifications(): void {
    // Create a node and its active translation request.
    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());

    $statuses = [
      'Accepted' => 'Active',
      'Rejected' => 'Finished',
      'Cancelled' => 'Finished',
      'Executed' => 'Finished',
      'Suspended' => 'Active',
    ];

    // Keep track of all the log messages because we are sending notifications
    // to the same request entity.
    $expected_logs = [];
    $i = 1;
    foreach ($statuses as $epoetry_status => $request_status) {
      EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
      $notification = [
        'type' => 'RequestStatusChange',
        'status' => $epoetry_status,
      ];
      if ($epoetry_status == 'Rejected') {
        $notification['message'] = 'We are sorry';
      }
      EpoetryTranslationMockHelper::notifyRequest($request, $notification);

      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      $this->assertEquals($request_status, $request->getRequestStatus(), sprintf('The request status for %s is correct', $epoetry_status));
      $this->assertEquals($epoetry_status, $request->getEpoetryRequestStatus());

      $log_type = in_array($epoetry_status, ['Accepted', 'Executed']) ? 'Info' : 'Warning';
      $log_message = sprintf('The request has been %s by ePoetry. Planning agent: test. Planning sector: DGT. Message: The request status has been changed to %s.', $epoetry_status, $epoetry_status);
      if ($epoetry_status == 'Rejected') {
        $log_message = sprintf('The request has been %s by ePoetry. Planning agent: test. Planning sector: DGT. Message: We are sorry.', $epoetry_status);
      }

      $expected_logs[$i] = [
        $log_type,
        $log_message,
      ];

      $this->assertLogMessagesValues($request, $expected_logs);

      // Reset the statuses for the next iteration.
      $request->setRequestStatus('Active');
      $request->setEpoetryRequestStatus('SenttoDGT');
      $request->save();
      $i++;
    }

    // Delete the request and send some notifications to assert we are logging
    // the fact that we cannot find the request.
    $request->delete();
    $notification = [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ];
    EpoetryTranslationMockHelper::notifyRequest($request, $notification);
    $log = MockLogger::getLogs()[0];
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $log['context']['@reference']);
  }

  /**
   * Tests the product notifications subscriber.
   */
  public function testProductNotifications(): void {
    // Create a node and its active translation request.
    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Active', $request->getTargetLanguage('fr')->getStatus());

    $statuses = [
      'Requested',
      'Cancelled',
      'Accepted',
      'Ongoing',
      'ReadyToBeSent',
      'Sent',
      'Closed',
      'Suspended',
    ];

    // Keep track of all the log messages because we are sending notifications
    // to the same request entity.
    $expected_logs = [];
    $i = 1;
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    foreach ($statuses as $status) {
      $notification = [
        'type' => 'ProductStatusChange',
        'status' => $status,
        'language' => 'fr',
      ];

      EpoetryTranslationMockHelper::notifyRequest($request, $notification);

      $storage->resetCache();
      $request = $storage->load($request->id());
      if ($status === 'Requested') {
        // For this status, we don't change our own as it doesn't make a
        // difference.
        $status = 'Active';
      }
      $this->assertEquals($status, $request->getTargetLanguage('fr')->getStatus());

      if ($status !== 'Requested') {
        // We don't do anything for this status so we don't log anything.
        continue;
      }

      $log_type = in_array($status, ['Accepted', 'Executed']) ? 'Warning' : 'Info';
      $log_message = sprintf('The French product status has been updated to %s.', $status);

      $expected_logs[$i] = [
        $log_type,
        $log_message,
      ];

      $this->assertLogMessagesValues($request, $expected_logs);
      $i++;
    }

    // Delete the request and send some notifications to assert we are logging
    // the fact that we cannot find the request.
    $request->delete();
    $notification = [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'fr',
    ];
    EpoetryTranslationMockHelper::notifyRequest($request, $notification);
    $log = MockLogger::getLogs()[0];
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $log['context']['@reference']);
    MockLogger::clearLogs();
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $log = MockLogger::getLogs()[0];
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $log['context']['@reference']);
  }

  /**
   * Tests resubmitting rejected requests.
   */
  public function testResubmitRejectedRequests(): void {
    $request_storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    // Create a rejected request, just so we have one in the system for the
    // node.
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ACTIVE,
      ],
    ]);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED);
    $request->save();

    // Now create a new request for this node that is active. Note that because
    // the previous request was rejected, the request ID can stay identical
    // as this would be technically a resubmit request.
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_ACTIVE, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ACTIVE,
      ],
    ]);
    $request->save();

    // Go to the remote translation dashboard and assert that while we have an
    // active request, we cannot make a new one, as expected under the normal
    // flow.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'N/A',
      // The mock link to accept the request.
      'Accept',
    ]);
    $this->assertSession()->fieldDisabled('Translator');
    // We also don't see any message of a rejected request.
    $this->assertSession()->pageTextNotContains('had been rejected');

    // Reject the request.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Rejected',
      'message' => 'Please fix your content',
    ]);
    $request_storage->resetCache();
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $request_storage->load($request->id());
    $this->assertEquals(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED, $request->getEpoetryRequestStatus());
    $this->assertEquals(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED, $request->getRequestStatus());

    // Assert the rejected request has logged the information.
    $this->assertLogMessagesValues($request, [
      // It doesn't contain the initial logs as we created the request manually.
      1 => [
        'Warning',
        'The request has been Rejected by ePoetry. Planning agent: test. Planning sector: DGT. Message: Please fix your content.',
      ],
    ]);

    // Go to the remote translation dashboard and assert there are no requests
    // visible, we can make a new translation, but we also have a message
    // stating that we had a rejected request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertNull($this->getSession()->getPage()->find('css', 'table.request-status-meta-table'));
    $this->assertSession()->pageTextContains('The last ePoetry translation request with the ID DIGIT/' . date('Y') . '/2000/0/0/TRA had been rejected. You can resubmit to correct it.');
    $this->assertSession()->fieldEnabled('Translator');

    // Make a new request to "correct" the reason why it was rejected. This can
    // be done even without making a change to the content.
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');
    $this->assertSession()->pageTextContains('You are making a request for a new version. The previous version was translated with the DIGIT/' . date('Y') . '/2000/0/0/TRA request ID. The previous request had been rejected. You are now resubmitting the request, please ensure it is now valid.');
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:resubmitRequest><resubmitRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 3</internalReference><requestedDeadline>2032-10-10T23:59:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><comment>http://web:8080/build/en/basic-translation-node</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMyIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDM8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0zIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzNdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTTExYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+00:00" trackChanges="false"><language>fr</language></product></products></requestDetails></resubmitRequest><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:resubmitRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = TranslationRequest::loadMultiple();
    // We have 3 requests in total: the first rejected, the second active which
    // was rejected and the third which is active now.
    $this->assertCount(3, $requests);
    $request = end($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('fr', 'Active'), $target_languages['fr']);
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getAcceptedDeadline());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2032-10-10', $request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      // The ID is the same as the previous was rejected.
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
      // The mock link to accept the request.
      'Accept',
    ]);

    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the resubmitRequest request type.',
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your resubmitRequest. We will process it soon.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the case when a request crashes or fails.
   */
  public function testFailedRequest(): void {
    // Create a node and mimic it having been translated via ePoetry. This is
    // so that we have a request already in the system that went through and we
    // can run assertions about messaging and stuff about past requests.
    $node = $this->createBasicTestNode();
    $node->addTranslation('fr', ['title' => 'Basic translation node fr'] + $node->toArray());
    $node->setNewRevision(FALSE);
    $node->save();
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      ],
    ]);
    $request->save();

    // Make a new draft of the node and start a new translation.
    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    // Create a node and make an ePoetry request that fails.
    \Drupal::state()->set('oe_translation_epoetry_mock_response_error', [
      'code' => 'ns0:Server',
      'string' => 'There was an error in your request.',
    ]);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('There was a problem sending the request to ePoetry.');

    // We have a failed request so no new translations can be made until the
    // user dispenses with this request by marking it as finished.
    $this->assertSession()->fieldDisabled('Translator');

    $this->assertRequestStatusTable([
      'Failed',
      'ePoetry',
      // No epoetry status.
      '',
      // No ID.
      '',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
    ]);

    $expected_logs = [
      1 => [
        'Error',
        'Phpro\SoapClient\Exception\SoapException: There was an error in your request.',
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Go to the dashboard and mark the request as Failed & Finished.
    $this->clickLink('Dashboard');
    $this->assertSession()->pageTextNotContains('There are no ongoing remote translation requests');
    $expected_ongoing = [
      'translator' => 'ePoetry',
      'status' => 'Failed',
      'title' => 'Basic translation node',
      'title_url' => $node->toUrl()->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $this->assertOngoingTranslations([$expected_ongoing]);
    $this->getSession()->getPage()->pressButton('List additional actions');
    $this->clickLink('Mark as finished');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');
    $requests = TranslationRequest::loadMultiple();
    $this->assertCount(2, $requests);
    $request = end($requests);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED, $request->getRequestStatus());

    // Go back to and create a request, this time going through.
    \Drupal::state()->delete('oe_translation_epoetry_mock_response_error');
    $this->clickLink('Remote translations');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $request = reset($requests);
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    // We can only find one ongoing request.
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Active'), $target_languages['bg']);
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getAcceptedDeadline());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2032-10-10', $request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Message to the provider', $request->getMessage());
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/1/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
     // The mock link tu accept the request.
      'Accept',
    ]);
  }

  /**
   * Tests the resetting of the current dossier to force a new dossier creation.
   */
  public function testResetDossier(): void {
    // Create a node and make a new translation request for it. This will
    // start a dossier.
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Accept and translate.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ]);
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'bg',
    ]);
    EpoetryTranslationMockHelper::translateRequest($request, 'bg');

    // Assert the created dossier.
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Log in as an admin and go reset the dossier.
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/structure/remote-translation-provider/epoetry/edit');
    $this->assertSession()->pageTextContains('[CURRENT] Number 1001 / Code DIGIT / Year ' . date('Y') . ' / Part 0');
    $this->assertSession()->pageTextNotContains('SET TO RESET');
    $this->getSession()->getPage()->checkField('Reset current dossier');
    $this->getSession()->getPage()->pressButton('Save');
    $this->drupalGet('/admin/structure/remote-translation-provider/epoetry/edit');
    $this->assertSession()->pageTextContains('[CURRENT] Number 1001 / Code DIGIT / Year ' . date('Y') . ' / Part 0 [SET TO RESET]');

    // Now create a new request and assert the dossier has been reset and a
    // createLinguisticRequest request is made.
    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
        'reset' => TRUE,
      ],
      1002 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);
  }

  /**
   * Tests that by default, we use the proper authentication service.
   */
  public function testAuthentication(): void {
    \Drupal::state()->set('oe_translation_epoetry_mock.bypass_mock_authentication', TRUE);

    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Select the ePoetry translator.
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('There was a problem sending the request to ePoetry.');
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('Phpro\SoapClient\Exception\SoapException: Client certificate authentication failed due to the following error');
  }

  /**
   * Asserts the log messages table output.
   *
   * @param array $logs
   *   The log information array keyed by the number with type and message.
   */
  protected function assertLogMessagesTable(array $logs): void {
    $table = $this->getSession()->getPage()->find('css', 'table.translation-request-log-messages');
    $rows = $table->findAll('css', 'tbody tr');
    $this->assertCount(count($logs), $rows);
    $actual = [];
    foreach ($rows as $row) {
      $cols = $row->findAll('css', 'td');
      $actual[(int) $cols[0]->getHtml()] = [
        $cols[1]->getHtml(),
        strip_tags($cols[2]->getHtml()),
      ];
    }

    $this->assertEquals($logs, $actual);
  }

  /**
   * Asserts the logs messages in the entity.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   * @param array $logs
   *   The expected logs.
   */
  protected function assertLogMessagesValues(TranslationRequestEpoetryInterface $request, array $logs): void {
    $actual = [];
    $i = 1;
    foreach ($request->getLogMessages() as $log) {
      $actual[$i] = [
        ucfirst($log->getType()),
        strip_tags((string) $log->getMessage()),
      ];

      $i++;
    }

    $this->assertEquals($logs, $actual);
  }

}