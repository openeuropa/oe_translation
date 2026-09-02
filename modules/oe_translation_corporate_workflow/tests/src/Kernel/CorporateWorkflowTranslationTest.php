<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_corporate_workflow\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\oe_translation_local\Controller\TranslationLocalController;
use Drupal\workflows\Entity\Workflow;

/**
 * Testing custom translation-related logic.
 *
 * @group batch3
 */
class CorporateWorkflowTranslationTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'node_storage_body_field',
    'oe_translation',
    'oe_translation_local',
    'oe_translation_test',
    'content_translation',
    'language',
    'field',
    'options',
    'user',
    'workflows',
    'views',
    'content_moderation',
    'entity_reference_revisions',
    'file',
    'oe_link_lists',
    'oe_editorial',
    'oe_link_lists_test',
    'oe_editorial_corporate_workflow',
    'oe_translation_corporate_workflow',
    'oe_translation_corporate_workflow_test',
    'paragraphs',
    'entity_test',
    'entity_version',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_test_mulrev');
    $this->installEntitySchema('link_list');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('oe_translation_request');

    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'oe_translation',
      'content_translation',
      'language',
      'workflows',
      'content_moderation',
      'oe_editorial_corporate_workflow',
      'entity_test',
      'oe_link_lists',
      'oe_link_lists_test',
    ]);

    $values = ['type' => 'ct_example', 'name' => 'CT example'];
    $node_type = NodeType::create($values);
    $node_type->save();

    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow($node_type->id());

    // Add the workflow to the test entity as well.
    $workflow = Workflow::load('oe_corporate_workflow');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_mulrev', 'entity_test_mulrev');
    $workflow->save();

    \Drupal::service('content_translation.manager')->setEnabled('link_list', 'dynamic', TRUE);
    \Drupal::moduleHandler()->loadInclude('oe_translation_corporate_workflow_test', 'install');
    oe_translation_corporate_workflow_test_install(FALSE);

    ConfigurableLanguage::create(['id' => 'fr'])->save();
  }

  /**
   * Tests that revisions do not get created when deleting a translation.
   */
  public function testTranslationDeletion(): void {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $entity_type_manager->getStorage('node')->create([
      'type' => 'ct_example',
      'title' => 'Test node',
    ]);
    $node->save();
    // Since we have moderation enabled, this will create a new revision of the
    // node.
    $node->addTranslation('fr', $node->toArray());
    $node->save();

    // Assert we do have two translations of the node.
    $this->assertCount(2, $node->getTranslationLanguages());
    // Assert that we have 2 revisions of the node.
    $this->assertCount(2, $entity_type_manager->getStorage('node')->getQuery()->accessCheck(FALSE)->allRevisions()->condition('nid', $node->id())->execute());

    $node->removeTranslation('fr');
    $node->save();

    $entity_type_manager->getStorage('node')->resetCache();
    $node = $entity_type_manager->getStorage('node')->load($node->id());

    // Assert that we still have only 2 revisions of the node and the
    // translation deletion did not create a new one.
    $this->assertCount(1, $node->getTranslationLanguages());
    $this->assertCount(2, $entity_type_manager->getStorage('node')->getQuery()->accessCheck(FALSE)->allRevisions()->condition('nid', $node->id())->execute());

    // Do the same tests with another entity type that uses the workflow AND
    // our translation system.
    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'administrative_title' => 'My editorial content',
      'moderation_state' => 'draft',
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'test_example_source',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'page',
        ],
      ],
      'display' => [
        'plugin' => 'title',
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
      'size' => 1,
      'more_link' => [],
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $link_list->addTranslation('fr', $link_list->toArray());
    $link_list->save();

    // Assert we do have two translations of the link_list.
    $this->assertCount(2, $link_list->getTranslationLanguages());
    // Assert that we have 2 revisions of the link_list.
    $this->assertCount(2, $entity_type_manager->getStorage('node')->getQuery()->accessCheck(FALSE)->allRevisions()->condition('nid', $link_list->id())->execute());

    $link_list->removeTranslation('fr');
    $link_list->save();

    $entity_type_manager->getStorage('node')->resetCache();
    $link_list = $entity_type_manager->getStorage('node')->load($link_list->id());

    // Assert that we still have only 2 revisions of the link_list and the
    // translation deletion did not create a new one.
    $this->assertCount(1, $link_list->getTranslationLanguages());
    $this->assertCount(2, $entity_type_manager->getStorage('node')->getQuery()->accessCheck(FALSE)->allRevisions()->condition('nid', $link_list->id())->execute());

    // Do the same tests but with another entity type which does not use
    // our local translation (but uses the workflow).
    $entity = $entity_type_manager->getStorage('entity_test_mulrev')->create([
      'name' => 'Test entity',
    ]);
    $entity->save();

    // Since we have moderation enabled, this will create a new revision of the
    // entity.
    $entity->addTranslation('fr', $node->toArray());
    $entity->save();

    $this->assertCount(2, $entity->getTranslationLanguages());
    $this->assertCount(2, $entity_type_manager->getStorage('entity_test_mulrev')->getQuery()->accessCheck(FALSE)->allRevisions()->condition('id', $entity->id())->execute());

    $entity->removeTranslation('fr');
    $entity->save();

    $entity_type_manager->getStorage('entity_test_mulrev')->resetCache();
    $entity = $entity_type_manager->getStorage('entity_test_mulrev')->load($entity->id());

    $this->assertCount(1, $entity->getTranslationLanguages());
    // This time we should have an extra revision after deleting the
    // translation because that is the core default.
    $this->assertCount(3, $entity_type_manager->getStorage('entity_test_mulrev')->getQuery()->accessCheck(FALSE)->allRevisions()->condition('id', $entity->id())->execute());
  }

  /**
   * Tests the moderation state restriction on local translation creation.
   */
  public function testLocalTranslationCreateAccess(): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'ct_example',
      'title' => 'Test node',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Create an empty user to avoid having uid=1.
    $this->createUser();

    $account = $this->createUser([]);
    $access_manager = $this->container->get('access_manager');
    $route_name = 'oe_translation_local.create_local_translation_request';
    $parameters = [
      'entity_type' => 'node',
      'entity' => $node->getRevisionId(),
      'source' => 'en',
      'target' => 'fr',
    ];

    // The user does not have global permission to translate entities.
    // We grant the access through the events.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['create' => 'allowed']);

    // The node is still in draft state, so the moderation state requirement
    // on the route keeps the access forbidden, even though the event
    // granted it.
    $access = $access_manager->checkNamedRoute($route_name, $parameters, $account, TRUE);
    $this->assertTrue($access->isForbidden(), 'Translations cannot be added to entities in "draft" state.');

    // Now, publish the entity and check the access again.
    $node->set('moderation_state', 'published');
    $node->save();
    $parameters['entity'] = $node->getRevisionId();
    $access = $access_manager->checkNamedRoute($route_name, $parameters, $account, TRUE);
    $this->assertTrue($access->isAllowed(), 'Translations should be allowed for entities in "published" state.');

    // Disable the event override and fail if the user has no global permission.
    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $access = $access_manager->checkNamedRoute($route_name, $parameters, $account, TRUE);
    $this->assertTrue($access->isForbidden(), 'Translations cannot be added without global permissions.');
  }

  /**
   * Tests that the deprecated TranslationAccessEvent is still functional.
   *
   * The oe_translation_corporate_workflow module no longer subscribes to
   * this event (its moderation state restriction became a requirement on
   * the creation route), but the event itself, and
   * TranslationLocalController's dispatch of it, must remain functional for
   * backwards compatibility with any other subscriber.
   */
  public function testDeprecatedTranslationAccessEvent(): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'ct_example',
      'title' => 'Test node',
      'moderation_state' => 'published',
    ]);
    $node->save();

    // Create an empty user to avoid having uid=1.
    $this->createUser();

    $account = $this->createUser(['translate any entity']);
    $language_manager = $this->container->get('language_manager');
    $source = $language_manager->getLanguage('en');
    $target = $language_manager->getLanguage('fr');

    /** @var \Drupal\oe_translation_local\Controller\TranslationLocalController $controller */
    $controller = TranslationLocalController::create($this->container);

    // The user has the global permission, so access is allowed by default.
    $access = $controller->createLocalTranslationRequestAccess($node, $source, $target, $account);
    $this->assertTrue($access->isAllowed(), 'Translations should be allowed for a user with global permission.');

    // The deprecated event can still revoke access, even for a user with
    // the global permission.
    \Drupal::state()->set('oe_translation_test.operation_access_overrides', ['deprecated' => 'forbidden']);
    $access = $controller->createLocalTranslationRequestAccess($node, $source, $target, $account);
    $this->assertTrue($access->isForbidden(), 'The deprecated event should still be able to revoke access.');

    \Drupal::state()->delete('oe_translation_test.operation_access_overrides');
    $access = $controller->createLocalTranslationRequestAccess($node, $source, $target, $account);
    $this->assertTrue($access->isAllowed(), 'Access should be restored once the override is removed.');
  }

}
