<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry_mock\PoetryMock;

/**
 * Tests the configuration of Poetry translators works as intended.
 */
class PoetryConfigurationTest extends PoetryTranslationTestBase {

  /**
   * Tests the configuration of the Poetry translator plugin.
   */
  public function testTranslatorConfiguration() : void {
    // Log in with a TMGMT administrator user to edit the Poetry translator.
    /** @var \Drupal\user\RoleInterface $role */
    $user = $this->drupalCreateUser(['administer tmgmt']);
    $this->drupalLogin($user);

    $contact_values = [
      'auteur' => 'contactAuthor',
      'secretaire' => 'contactSecretary',
      'contact' => 'contactContact',
      'responsable' => 'contactResponsible',
    ];

    $organisation_values = [
      'responsible' => 'orgResponsible',
      'author' => 'orgAuthor',
      'requester' => 'orgRequester',
    ];

    // Save custom configuration values on the translator.
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->container->get('entity_type.manager')->getStorage('tmgmt_translator')->load('poetry');
    $form_values = [
      'settings[service_wsdl]' => PoetryMock::getWsdlUrl(),
      'settings[identifier_code]' => 'testCode',
      'settings[title_prefix]' => 'testPrefix',
      'settings[site_id]' => 'testId',
      'settings[application_reference]' => 'testRef',
    ];
    foreach ($contact_values as $field => $value) {
      $form_values['settings[contact][' . $field . ']'] = $value;
    }
    foreach ($organisation_values as $field => $value) {
      $form_values['settings[organisation][' . $field . ']'] = $value;
    }

    $this->drupalPostForm(Url::fromRoute('entity.tmgmt_translator.edit_form', ['tmgmt_translator' => $translator->id()]),
      $form_values,
      'Save');
    $this->assertSession()->pageTextContains($translator->label() . " configuration has been updated.");

    // Log in with a translator user to finalize the translation process.
    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load('translator');
    $user = $this->drupalCreateUser($role->getPermissions());
    $this->drupalLogin($user);

    // Create a node to translate.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My first node',
    ]);
    $node->save();

    // Select some languages to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian']);
    $job = $this->jobStorage->load(1);

    // Check that the fields contain the default values.
    foreach ($contact_values as $field => $value) {
      $this->assertsession()->fieldValueEquals('details[contact][' . $field . ']', $value);
    }
    foreach ($organisation_values as $field => $value) {
      $this->assertsession()->fieldValueEquals('details[organisation][' . $field . ']', $value);
    }

    // Submit the request form.
    $date = new \DateTime();
    $date->modify('+ 7 days');
    $values = [
      'details[date]' => $date->format('Y-m-d'),
    ];
    $this->drupalPostForm(NULL, $values, 'Send request');
    $this->assertSession()->pageTextContains('The request has been sent to DGT.');

    // Check the request sent and assert the saved values where used.
    $result = $this->container->get('database')
      ->select('watchdog', 'w')
      ->range(0, 1)
      ->fields('w', ['variables'])
      ->condition('message', 'Poetry event <strong>@name</strong>: <br /><br />Username: <strong>@username</strong> <br /><br />Password: <strong>@password</strong> \n\n<pre>@message</pre>')
      ->execute()
      ->fetchCol(0);
    $this->assertCount(1, $result);
    $logged_message = trim(unserialize(reset($result))['@message'], "'");
    $message = simplexml_load_string($logged_message);
    $request = $message->request;

    // Check identifier with the custom code.
    $this->assertEqual((string) $request->attributes()['id'], $form_values['settings[identifier_code]'] . '/2019/EWCMS_SEQUENCE/0/0/TRA');

    // Check the title.
    $title = (string) new FormattableMarkup('@prefix: @site_id - @title', [
      '@prefix' => $form_values['settings[title_prefix]'],
      '@site_id' => $form_values['settings[site_id]'],
      '@title' => $job->label(),
    ]);
    $this->assertEquals($title, (string) $request->demande->titre);

    // Check the application reference.
    $this->assertEquals($form_values['settings[application_reference]'], (string) $request->demande->applicationReference);

    // Check organisation details.
    $this->assertEquals($organisation_values['author'], (string) $request->demande->organisationAuteur);
    $this->assertEquals($organisation_values['responsible'], (string) $request->demande->organisationResponsable);
    $this->assertEquals($organisation_values['requester'], (string) $request->demande->serviceDemandeur);

    // Check the contact information.
    $contacts = $request->contacts;
    foreach ($contacts as $contact) {
      $this->assertEquals($contact_values[(string) $contact->attributes()['type']], (string) $contact->contactNickname);
    }
  }

  /**
   * Tests that we cannot make Poetry translations requests without config.
   */
  public function testRequiredConfiguration(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My test node',
    ]);
    $node->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->buttonExists('Request DGT translation for the selected languages');

    // Unset the service WSDL as an example of required configuration.
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->container->get('entity_type.manager')->getStorage('tmgmt_translator')->load('poetry');
    $translator->setSetting('service_wsdl', NULL);
    $translator->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->buttonNotExists('Request DGT translation for the selected languages');
  }

}
