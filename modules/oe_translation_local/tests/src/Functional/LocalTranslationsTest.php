<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_local\Functional;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Url;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the local translation system.
 *
 * @group batch1
 */
class LocalTranslationsTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'paragraphs',
    'entity_reference_revisions',
    'menu_link_content',
    'views',
    // Add this module to increase coverage of features working correctly even
    // with this enabled.
    'oe_translation_multivalue',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Array of fields.
   *
   * Keyed by field name containing the xpath, value and whether it's
   * translatable.
   *
   * @var array
   */
  protected $fields = [
    'title' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Title']",
      'value' => 'Full translation node',
      'translate' => TRUE,
    ],
    'address_country_code' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / The two-letter country code']",
      'value' => 'BE',
    ],
    'address_locality' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / City']",
      'value' => 'Brussels',
    ],
    'address_postal_code' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / Postal code']",
      'value' => 1000,
    ],
    'address_address_line1' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / Street address']",
      'value' => 'The street name',
    ],
    'address_given_name' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / First name']",
      'value' => 'The first name',
    ],
    'address_family_name' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / Last name']",
      'value' => 'The last name',
    ],
    'ott_content_reference' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Content reference / Title']",
      'value' => 'Referenced node',
      'translate' => TRUE,
    ],
    'ott_inner_paragraphs__0__ott_inner_paragraph_ott__0' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'child field value 1',
      'translate' => TRUE,
    ],
    'ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__0' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'grandchild field value 1',
      'translate' => TRUE,
    ],
    'ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__1' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner Paragraphs / (1) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'grandchild field value 2',
      'translate' => TRUE,
    ],
    'ott_inner_paragraphs__0__ott_inner_paragraph_ott__1' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (1) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'child field value 2',
      'translate' => TRUE,
    ],
    'ott_top_level_paragraphs__0__ott_top_level_paragraph_ott__0' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Top level paragraph field']",
      'value' => 'top field value 1',
      'translate' => TRUE,
    ],
    'ott_top_level_paragraphs__1__ott_top_level_paragraph_ott__0' => [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (1) / Demo paragraph type / Top level paragraph field']",
      'value' => 'top field value 2',
      'translate' => TRUE,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mark the paragraph bundles translatable.
    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'demo_paragraph_type', TRUE);
    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'demo_inner_paragraph_type', TRUE);

    // Import the configs from the tasks folder.
    $storage = new FileStorage(\Drupal::service('extension.list.module')->getPath('oe_translation_test') . '/config/tasks');
    foreach ($storage->listAll() as $name) {
      $this->importConfigFromFile($name, $storage);
    }

    // Mark the test entity reference field as embeddable to behave
    // as composite entities.
    $this->config('oe_translation.settings')
      ->set('translation_source_embedded_fields', [
        'node' => [
          'ott_content_reference' => TRUE,
        ],
      ])
      ->save();

    // Mark multivalue fields as using translation_multivalue.
    $storage = FieldStorageConfig::load('node.ott_demo_link_field');
    $storage->setSetting('translation_multivalue', TRUE);
    $storage->save();
  }

  /**
   * Tests the main local translation flow.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testSingleTranslationFlow(): void {
    // Assert that the local translation creation URL is not accessible for
    // entity types that are not using our translation system.
    $menu_link_content = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => 'http://example.com'],
      'title' => 'Link test',
    ]);
    $menu_link_content->save();
    $url = Url::fromRoute('oe_translation_local.create_local_translation_request', [
      'entity_type' => 'menu_link_content',
      'entity' => $menu_link_content->getRevisionId(),
      'source' => 'en',
      'target' => 'fr',
    ]);
    $access = $url->access(NULL, TRUE);
    $this->assertTrue($access->isForbidden());

    // Create a node and perform the translation flow.
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $this->createFullTestNode();
    $this->drupalGet($node->toUrl());

    $this->clickLink('Translate');
    // Assert we don't have yet any translations.
    $this->assertSession()->pageTextContains('There are no open local translation requests');
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Full translation node'],
    ]);

    $this->clickLink('Local translations');
    // Assert we have a row for each language with a link to start a
    // translation.
    $this->assertLocalLanguagesTable();

    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();

    // Assert the title.
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate Full translation node in French');

    // Assert that we have the 3 buttons because the oe_translator has all the
    // permissions.
    $this->assertSession()->buttonExists('Save as draft');
    $this->assertSession()->buttonExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronise');

    // Remove the "accept" permission and assert the button is missing.
    $role = Role::load('oe_translator');
    $role->revokePermission('accept translation request');
    $role->save();
    $this->getSession()->reload();

    $this->assertSession()->buttonExists('Save as draft');
    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronise');
    // Do the same for the sync permission. Since access policies are cached,
    // we need to make sure the related tags are invalidated immediately.
    \Drupal::service('cache_tags.invalidator')->resetChecksums();
    $role->revokePermission('sync translation request');
    $role->save();
    $this->getSession()->reload();

    $this->assertSession()->buttonExists('Save as draft');
    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonNotExists('Save and synchronise');

    // Add back the permissions.
    \Drupal::service('cache_tags.invalidator')->resetChecksums();
    $role->grantPermission('accept translation request');
    $role->grantPermission('sync translation request');
    $role->save();
    $this->getSession()->reload();

    // Translate each of the fields.
    foreach ($this->fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      if (!$table_header) {
        $this->fail(sprintf('The form label for the "%s" field was not found on the page.', $key));
      }

      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      if (!$element) {
        $this->fail(sprintf('The translation element for the "%s" field was not found on the page.', $key));
      }
      // Assert we have all the fields there with the correct value.
      $this->assertEquals($data['value'], $element->getText());
      // Set a translation value.
      if (isset($data['translate'])) {
        $element->setValue($data['value'] . ' FR');
      }
    }

    // Assert that we have the 4 buttons.
    $this->assertSession()->buttonExists('Save as draft');
    $this->assertSession()->buttonExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronise');
    $this->assertSession()->buttonExists('Preview');

    // Assert that we have max_length validation.
    $this->getSession()->getPage()->fillField('title|0|value[translation]', 'ЄС єдиний у своїй солідарності з Україною та продовжуватиме підтримувати Україну та її народ разом зі своїми міжнародними партнерами, зокрема шляхом додаткової політичної, фінансової та гуманітарної підтримки. ЄС єдиний у своїй солідарності з Україною та продовжуватиме підтримувати Україну та її народ разом зі своїми міжнародними партнерами, зокрема шляхом додаткової політичної, фінансової та гуманітарної підтримки.');
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The field has 419 characters while the limit is 255.');
    $this->getSession()->getPage()->fillField('title|0|value[translation]', 'ЄС єдиний у своїй солідарності з Україною та продовжуватиме підтримувати Україну та її народ разом зі своїми міжнародними партнерами, зокрема шляхом додаткової політичної, фінансової та гуманітарної підтримки.');
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The translation has been saved.');

    // Change back the title.
    $this->clickLink('Edit draft translation');
    $this->getSession()->getPage()->fillField('title|0|value[translation]', 'Full translation node FR');

    // Save the translation as draft.
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/local');

    // Assert that FR now has a started translation request.
    $this->assertLocalLanguagesTable(['fr']);

    // Assert no translation was created on the node.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(1, $node->getTranslationLanguages());

    // Go the dashboard and assert we see the ongoing request.
    $this->clickLink('Dashboard');
    $expected_ongoing = [
      'langcode' => 'fr',
      'status' => 'Draft',
      'title' => 'Full translation node',
      'title_url' => $node->toUrl()->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $this->assertDashboardOngoingTranslations([$expected_ongoing]);

    // Edit the translation request.
    $this->clickLink('Local translations');
    $this->clickLink('Edit draft translation');

    // Assert the translation values have been saved and the form is
    // populated with the translated values.
    foreach ($this->fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");

      $expected_value = isset($data['translate']) ? $data['value'] . ' FR' : $data['value'];
      $this->assertEquals($expected_value, $element->getText());
    }

    // Save and accept the translation.
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/local');

    // Assert no translation was created on the node.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(1, $node->getTranslationLanguages());

    // Assert the status change of the request on the dashboard.
    $this->clickLink('Dashboard');
    $expected_ongoing['status'] = 'Accepted';
    $this->assertDashboardOngoingTranslations([$expected_ongoing]);

    // Edit and sync the translation. This time from the dashboard.
    $this->clickLink('Edit accepted translation');
    // The "accept" button is no longer visible since we accepted it.
    $this->assertSession()->buttonExists('Save as draft');
    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronise');

    // Each form element is disabled because while accepted, we should not
    // edit.
    foreach ($this->fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      $this->assertEquals('disabled', $element->getAttribute('disabled'));
    }

    // Before syncing, save it again as draft to assert that the form elements
    // become editable again.
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');
    $this->clickLink('Edit draft translation');
    foreach ($this->fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      $this->assertNull($element->getAttribute('disabled'));
    }

    // Sync directly from draft state.
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->pageTextContains('The translation has been synchronised.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');

    // Go to the dashboard and assert there is no ongoing request, but we now
    // have a translation.
    $this->clickLink('Dashboard');
    $this->assertSession()->elementNotExists('css', 'table.ongoing-local-translation-requests-table');
    $this->assertSession()->pageTextContains('There are no open local translation requests');
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Full translation node'],
      'fr' => ['title' => 'Full translation node FR'],
    ]);

    // Assert the node now has the FR translation.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));
    $this->drupalGet('/fr/node/' . $node->id(), ['external' => FALSE]);

    // Assert that we can see all the translated values.
    foreach ($this->fields as $key => $data) {
      if (isset($data['translate'])) {
        $this->assertSession()->pageTextContains($data['value'] . ' FR');
      }
    }

    // Assert that we can preview a translation request.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');

    // Add a spanish translation to be previewed.
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="es"] a')->click();
    foreach ($this->fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      $this->assertEquals($data['value'], $element->getText());
      // Set a translation value.
      if (isset($data['translate'])) {
        $element->setValue($data['value'] . ' ES');
      }
    }
    // Preview translation.
    $this->getSession()->getPage()->pressButton('Preview');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->addressEquals('/es/translation-request/2/preview/es');
    $this->assertSession()->pageTextContains('Full translation node ES');
    $this->assertSession()->pageTextContains('Referenced node ES');
    $this->assertSession()->pageTextContains('grandchild field value 1 ES');
    $this->assertSession()->pageTextContains('top field value 1 ES');

    // Test that we can delete the translation request.
    $this->getSession()->back();
    $this->getSession()->getPage()->clickLink('Delete');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('The translation request 2 has been deleted.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/local');
  }

  /**
   * Tests the translation flow with parallel request over many revisions.
   */
  public function testMultipleTranslationFlow(): void {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $this->createBasicTestNode();
    $first_revision = $node->getRevisionId();
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');

    // Start a translation in FR and save it as draft with translated value.
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $this->assertEquals('Basic translation node', $element->getText());
    $element->setValue('Basic translation node FR');
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The translation has been saved.');

    // Edit the node and start a new revision.
    $node->set('title', 'Updated basic translation node');
    $node->setNewRevision(TRUE);
    $node->save();

    // Visit the dashboard to assert we see the correct info.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');

    // Assert the ongoing requests.
    $revision = $node_storage->loadRevision($first_revision);
    $expected_ongoing = [
      'langcode' => 'fr',
      'status' => 'Draft',
      'title' => 'Basic translation node',
      'title_url' => $revision->toUrl('revision')->toString(),
      // It is the initial revision ID.
      'revision' => $first_revision,
      // It is no longer the default revision.
      'is_default' => 'No',
    ];
    $this->assertDashboardOngoingTranslations([$expected_ongoing]);

    // Assert the existing translations. These point to the default revision.
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Updated basic translation node'],
    ]);

    // Start a new translation for the new revision.
    $this->clickLink('Local translations');
    // None of the languages have an edit button since no translation request
    // exists for this revision.
    $this->assertLocalLanguagesTable();
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();

    // We don't yet have any translation on the node so the default value is
    // that of the source.
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $this->assertEquals('Updated basic translation node', $element->getText());
    $element->setValue('Updated basic translation node FR');

    // Save again as draft and go back to the dashboard.
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->clickLink('Dashboard');

    // Assert we see both ongoing translation requests.
    $first_ongoing = $expected_ongoing;
    $second_ongoing = $expected_ongoing;
    $second_ongoing['title'] = 'Updated basic translation node';
    $second_ongoing['title_url'] = $node->toUrl()->toString();
    $second_ongoing['revision'] = $node->getRevisionId();
    $second_ongoing['is_default'] = 'Yes';
    $this->assertDashboardOngoingTranslations([$first_ongoing, $second_ongoing]);

    // Edit the most recent translation request and sync it.
    $this->clickLink('Edit draft translation', 1);
    // Ensure we clicked the right one.
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $this->assertEquals('Updated basic translation node FR', $element->getText());
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->clickLink('Dashboard');

    // Assert we only have again one ongoing request on the dashboard.
    $this->assertDashboardOngoingTranslations([$first_ongoing]);
    // But now we have a translation as well in the existing table.
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Updated basic translation node'],
      'fr' => ['title' => 'Updated basic translation node FR'],
    ]);

    // Assert the node has translation only on the most recent revision.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $node_storage->loadRevision($first_revision);
    $this->assertCount(1, $revision->getTranslationLanguages());
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertEquals('Updated basic translation node FR', $node->getTranslation('fr')->label());

    // Sync also the older ongoing translation.
    $this->clickLink('Edit draft translation');
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $this->assertEquals('Basic translation node FR', $element->getText());
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->clickLink('Dashboard');
    $this->assertSession()->pageTextContains('There are no open local translation requests');

    // Assert each revision has the correct translation.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $node_storage->loadRevision($first_revision);
    $this->assertCount(2, $revision->getTranslationLanguages());
    $this->assertTrue($revision->hasTranslation('fr'));
    $this->assertEquals('Basic translation node FR', $revision->getTranslation('fr')->label());
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertEquals('Updated basic translation node FR', $node->getTranslation('fr')->label());

    // Assert that in this process, no new node revisions were created.
    $this->assertCount(2, $node_storage->revisionIds($node));
  }

  /**
   * Tests the local translation dashboard.
   */
  public function testLocalTranslationDashboard(): void {
    $first_node = $this->createBasicTestNode();
    $first_node->set('title', 'First node');
    $first_node->save();
    $this->drupalGet($first_node->toUrl());
    $this->clickLink('Translate');
    $this->assertAddLocalTranslationOperation($first_node, ['en']);
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $element->setValue('First node FR');
    $this->getSession()->getPage()->pressButton('Save as draft');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->drupalGet($first_node->toUrl());
    $this->clickLink('Translate');
    $this->assertAddLocalTranslationOperation($first_node, ['fr', 'en']);

    $second_node = $this->createBasicTestNode();
    $second_node->set('title', 'Second node');
    $second_node->save();
    $this->drupalGet($second_node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="it"] a')->click();
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $element->setValue('Second node IT');
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->assertSession()->pageTextContains('The translation has been saved.');

    $third_node = $this->createBasicTestNode();
    $third_node->set('title', 'Third node');
    $third_node->save();
    $this->drupalGet($third_node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="ro"] a')->click();
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $element->setValue('Second node RO');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');

    // Assert the dashboard only accessible to users with the correct
    // permission.
    $this->drupalLogout();
    $this->drupalGet('admin/content/local-translation-requests');
    $this->assertSession()->pageTextContains('Access denied');

    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet('admin/content/local-translation-requests');
    $this->assertSession()->pageTextContains('Access denied');

    $user->addRole('oe_translator');
    $user->save();
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('Local translation requests');

    // Assert we can see all the nodes.
    $this->assertSession()->linkExistsExact('Third node');
    $this->assertSession()->linkExistsExact('Second node');
    $this->assertSession()->linkExistsExact('First node');

    // Assert that we can see the target language and target status fields.
    $this->assertEquals('French', $this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'First node')]]/td[5]")->getText());
    $this->assertEquals('Draft', $this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'First node')]]/td[6]")->getText());

    // Assert the filters.
    $this->getSession()->getPage()->selectFieldOption('Status', 'Synchronised');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkExistsExact('Third node');
    $this->assertSession()->linkNotExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('First node');
    $this->getSession()->getPage()->pressButton('Reset');

    $this->getSession()->getPage()->fillField('Content', 'Second');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkNotExistsExact('Third node');
    $this->assertSession()->linkExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('First node');

    $this->assertSession()->linkExistsExact('Edit accepted translation');
  }

  /**
   * Tests translation values are removed upon sync if source ones are removed.
   */
  public function testSourceValuesRemoval(): void {
    // Grant permission to edit nodes for the Translator role.
    $role = Role::load('oe_translator');
    $role->grantPermission('edit any oe_demo_translatable_page content');
    $role->save();
    // Create a node and translate it to French.
    $node = $this->createFullTestNode();
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    // Translate each of the fields.
    foreach ($this->fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      if (!$table_header) {
        $this->fail(sprintf('The form label for the "%s" field was not found on the page.', $key));
      }
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      if (!$element) {
        $this->fail(sprintf('The translation element for the "%s" field was not found on the page.', $key));
      }
      // Set a translation value.
      if (isset($data['translate'])) {
        $element->setValue($data['value'] . ' FR');
      }
    }
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    // Assert the node now has the FR translation.
    $this->drupalGet('/fr/node/' . $node->id(), ['external' => FALSE]);

    // Assert that we can see all the translated values.
    foreach ($this->fields as $key => $data) {
      if (isset($data['translate'])) {
        $this->assertSession()->pageTextContains($data['value'] . ' FR');
      }
    }

    $this->drupalGet($node->toUrl('edit-form'));
    // Remove on of the Top level paragraph fields values.
    $this->getSession()->getPage()->fillField('ott_top_level_paragraphs[0][subform][ott_top_level_paragraph_field][0][value]', '');
    $this->getSession()->getPage()->pressButton('Save (this translation)');
    // Navigate to the French local translation.
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    // Assert that we see a message informing the user that the field value
    // was removed from the source, and upon syncing, it will be removed from
    // the translation as well.
    $this->assertSession()->pageTextContains('Source value for the field Top level paragraphs / Delta #0 / Top level paragraph field was removed. Synchronising this translation will remove the value from the translation as well.');
    // Sync the translation.
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    // Assert that the French translations contains the same values except for
    // the ones removed from the original node.
    $this->drupalGet('/fr/node/' . $node->id(), ['external' => FALSE]);
    $this->assertSession()->pageTextContains('Full translation node FR');
    $this->assertSession()->pageTextContains('Referenced node FR');
    $this->assertSession()->pageTextNotContains('top field value 1 FR');
    $this->assertSession()->pageTextContains('top field value 2 FR');
    $this->assertSession()->pageTextContains('grandchild field value 1 FR');
    $this->assertSession()->pageTextContains('grandchild field value 2 FR');
    $this->assertSession()->pageTextContains('child field value 1 FR');
    $this->assertSession()->pageTextContains('child field value 2 FR');
  }

  /**
   * Tests that the available languages for ePoetry can be altered.
   */
  public function testLanguagesAlter(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');

    $this->assertSession()->pageTextContains('Bulgarian');
    $this->assertSession()->pageTextContains('French');

    \Drupal::state()->set('oe_translation_test.remove_languages', ['bg']);

    $this->getSession()->reload();

    $this->assertSession()->pageTextNotContains('Bulgarian');
    $this->assertSession()->pageTextContains('French');
  }

  /**
   * Asserts the ongoing translations table.
   *
   * @param array $languages
   *   The expected languages.
   */
  protected function assertDashboardOngoingTranslations(array $languages): void {
    $table = $this->getSession()->getPage()->find('css', 'table.ongoing-local-translation-requests-table');
    $this->assertCount(count($languages), $table->findAll('css', 'tbody tr'));
    $rows = $table->findAll('css', 'tbody tr');
    foreach ($rows as $key => $row) {
      $cols = $row->findAll('css', 'td');
      $hreflang = $row->getAttribute('hreflang');
      $expected_info = $languages[$key];
      $this->assertEquals($expected_info['langcode'], $hreflang);
      $language = ConfigurableLanguage::load($hreflang);
      $this->assertEquals($language->getName(), $cols[0]->getText());
      $this->assertEquals($expected_info['status'], $cols[1]->getText());
      $this->assertTrue($cols[2]->hasLink('Edit'));
    }
  }

  /**
   * Asserts the languages table on the local translations tab.
   *
   * @param array $with_edit
   *   The languages for which we have an edit.
   */
  protected function assertLocalLanguagesTable(array $with_edit = []): void {
    $languages = \Drupal::languageManager()->getLanguages();
    $page = $this->getSession()->getPage();
    // English should not be listed here because it's the default site language.
    unset($languages['en']);
    $this->assertNull($page->find('css', 'tbody tr[hreflang="en"]'));
    $page = $this->getSession()->getPage();
    $this->assertCount(count($languages), $page->findAll('css', 'tbody tr'));
    foreach ($languages as $language) {
      $langcode = $language->getId();
      $row = $page->find('css', 'tbody tr[hreflang="' . $langcode . '"]');
      $this->assertNotNull($row);
      $operations = $row->findAll('css', 'td .dropbutton li a');

      // If we expect to have an edit button, assert the extra operation.
      if (in_array($langcode, $with_edit)) {
        // We expect two operation: either to create or to edit AND to delete.
        $this->assertCount(2, $operations);
        $this->assertEquals('Edit draft translation', $operations[0]->getText());
        $this->assertEquals('Delete', $operations[1]->getText());
      }
      else {
        $this->assertEquals('Add new translation', $operations[0]->getText());
        // We expect one operation only.
        $this->assertCount(1, $operations);
      }
    }
  }

  /**
   * Asserts that on the dashboard, we have an operation to add new local trans.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $excluded_languages
   *   The languages for which we don't have.
   */
  protected function assertAddLocalTranslationOperation(NodeInterface $node, array $excluded_languages = []): void {
    foreach ($this->getSession()->getPage()->findAll('css', 'table.existing-translations-table tbody tr') as $row) {
      $langcode = $row->getAttribute('hreflang');
      $col = $row->find('xpath', '//td[3]');
      $link = $col->findLink('Add new local translation');
      if (in_array($langcode, $excluded_languages)) {
        $this->assertNull($link);
        continue;
      }

      $this->assertEquals('/build/en/admin/oe_translation/translate-local/node/' . $node->getRevisionId() . '/en/' . $langcode . '?destination=/build/en/node/' . $node->id() . '/translations', $link->getAttribute('href'));
    }
  }

}
