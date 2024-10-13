<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_poetry_legacy\FunctionalJavascript;

use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\node\Entity\Node;
use Drupal\oe_translation_epoetry_mock\EpoetryTranslationMockHelper;
use Drupal\oe_translation_poetry_legacy\Entity\LegacyPoetryReference;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;

/**
 * Tests the Legacy Poetry reference entity.
 *
 * @group batch1
 */
class LegacyPoetryReferenceTest extends TranslationTestBase {

  use TranslationsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry_legacy',
    'views',
    'oe_translation',
    'oe_translation_remote',
    'oe_translation_epoetry',
    'oe_translation_epoetry_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Tests the view of the entity type.
   */
  public function testLegacyPoetryReferenceView(): void {
    // Create two nodes to be referenced.
    $node1 = Node::create([
      'type' => 'page',
      'title' => 'Test node 1',
    ]);
    $node1->save();
    $node2 = Node::create([
      'type' => 'page',
      'title' => 'Test node 2',
    ]);
    $node2->save();
    // Create 2 Legacy Poetry reference entities.
    $poetry_legacy_reference_storage = $this->container->get('entity_type.manager')->getStorage('poetry_legacy_reference');
    /** @var \Drupal\oe_translation_poetry_legacy\Entity\LegacyPoetryReference $legacy_poetry_reference */
    $poetry_legacy_reference_storage->create([
      'node' => ['target_id' => $node1->id()],
      'poetry_request_id' => 'WEB/2022/1000/0/0/TRA',
    ])->save();
    $poetry_legacy_reference_storage->create([
      'node' => ['target_id' => $node2->id()],
      'poetry_request_id' => 'WEB/2022/2000/0/0/TRA',
    ])->save();

    $this->drupalGet('admin/content/legacy-poetry-references');
    // Anonymous users do not have access to the view.
    $this->assertSession()->pageTextContains('Access denied');
    $user = $this->createUser();
    $this->drupalLogin($user);
    // Authenticated users without the required permission do not have access
    // to the view either.
    $this->drupalGet('admin/content/legacy-poetry-references');
    $this->assertSession()->pageTextContains('Access denied');
    // Create a user with access to translate any entity.
    $user = $this->createUser(['translate any entity']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/content/legacy-poetry-references');
    // Assert the filter fields.
    $this->assertSession()->fieldExists('Node');
    $this->assertSession()->fieldExists('Poetry request ID');
    $this->assertSession()->buttonExists('Filter');
    $this->assertSession()->buttonNotExists('Reset');
    // Assert the values of the created entities.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Test the filters.
    $this->getSession()->getPage()->fillField('Node', 'Test');
    $this->getSession()->getPage()->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Both entities should still be displayed.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Filter using the full title of the node.
    $this->getSession()->getPage()->fillField('Node', 'Test node 1');
    $this->getSession()->getPage()->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Only the first entity should be displayed.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextNotContains('Test node 2');
    $this->assertSession()->pageTextNotContains('WEB/2022/2000/0/0/TRA');
    // Reset the filters.
    $this->getSession()->getPage()->pressButton('Reset');
    // Both entities should be displayed again.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Filter by Poetry ID.
    $this->getSession()->getPage()->fillField('Poetry request ID', 'WEB/2022/2000/0/0/TRA');
    $this->getSession()->getPage()->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Only the second entity should be displayed.
    $this->assertSession()->pageTextNotContains('Test node 1');
    $this->assertSession()->pageTextNotContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
    // Filter by Poetry ID using a common string between the two values.
    $this->getSession()->getPage()->fillField('Poetry request ID', 'web');
    $this->getSession()->getPage()->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Both entities should be displayed again.
    $this->assertSession()->pageTextContains('Test node 1');
    $this->assertSession()->pageTextContains('WEB/2022/1000/0/0/TRA');
    $this->assertSession()->pageTextContains('Test node 2');
    $this->assertSession()->pageTextContains('WEB/2022/2000/0/0/TRA');
  }

  /**
   * Tests that the ePoetry requests get altered to include the legacy ID.
   */
  public function testEpoetryRequestAlter(): void {
    // We don't need assertions that nodes for which there are no legacy IDs
    // are not altered because those are captured in the ePoetry translation
    // tests.
    $provider = RemoteTranslatorProvider::load('epoetry');
    $configuration = $provider->getProviderConfiguration();
    $configuration['title_prefix'] = 'A title prefix';
    $configuration['site_id'] = 'A site ID';
    $configuration['auto_accept'] = FALSE;
    $configuration['language_mapping'] = [];
    $provider->setProviderConfiguration($configuration);
    $provider->set('enabled', TRUE);
    $provider->save();

    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);

    // Create a node and a legacy ID for it.
    $node = $this->createBasicTestNode();
    LegacyPoetryReference::create([
      'node' => $node->id(),
      'poetry_request_id' => 'DIGIT/2020/500/0/0/TRA',
    ])->save();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Select the ePoetry translator.
    // The ePoetry translator is selected by default.
    $select = $this->assertSession()->selectExists('Translator');
    $this->assertEquals('epoetry', $select->getValue());

    // Select 2 languages.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('Portuguese');

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

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the first (createLinguisticRequest) was build correctly and
    // it includes in the comment the legacy ID.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 1</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Message to the provider. Page URL: http://web:8080/build/en/basic-translation-node. Poetry legacy request ID: DIGIT/2020/500/0/0/TRA</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMSIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDE8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0xIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzFdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTVYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>BG</language></product><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>PT-PT</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Make another node.
    $second_node = $this->createBasicTestNode();
    LegacyPoetryReference::create([
      'node' => $second_node->id(),
      'poetry_request_id' => 'DIGIT/2020/1000/0/0/TRA',
    ])->save();

    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
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
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(2, $requests);
    array_shift($requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>1001</number><year>' . date('Y') . '</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 2</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Page URL: http://web:8080/build/en/basic-translation-node-0. Poetry legacy request ID: DIGIT/2020/1000/0/0/TRA</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>BG</language></product></products></requestDetails><applicationName>digit</applicationName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Reject the request.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($second_node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Rejected',
    ]);

    // Make another request.
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
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
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(3, $requests);
    array_shift($requests);
    array_shift($requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:resubmitRequest><resubmitRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>1001</number><year>' . date('Y') . '</year></dossier><productType>TRA</productType><part>1</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 3</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Page URL: http://web:8080/build/en/basic-translation-node-0. Poetry legacy request ID: DIGIT/2020/1000/0/0/TRA</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMyIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDM8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0zIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzNdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTTExYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>BG</language></product></products></requestDetails></resubmitRequest><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:resubmitRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

  }

}
