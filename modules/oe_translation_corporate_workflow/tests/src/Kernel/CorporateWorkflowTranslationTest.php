<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_corporate_workflow\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\workflows\Entity\Workflow;

/**
 * Testing custom translation-related logic.
 *
 * @group batch1
 */
class CorporateWorkflowTranslationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'oe_translation',
    'content_translation',
    'language',
    'field',
    'options',
    'user',
    'workflows',
    'views',
    'content_moderation',
    'oe_editorial',
    'oe_editorial_corporate_workflow',
    'oe_translation_corporate_workflow',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_test_mulrev');
    $this->installEntitySchema('content_moderation_state');

    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'oe_translation',
      'content_translation',
      'language',
      'workflows',
      'content_moderation',
      'oe_editorial_corporate_workflow',
      'entity_test',
    ]);

    $values = ['type' => 'ct_example', 'name' => 'CT example'];
    $node_type = NodeType::create($values);
    $node_type->save();

    $this->container->get('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow($node_type->id());

    // Add the workflow to the test entity as well.
    $workflow = Workflow::load('oe_corporate_workflow');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_mulrev', 'entity_test_mulrev');
    $workflow->save();

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

    // Do the same tests but with another entity type which does not use
    // our local translation.
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

}
