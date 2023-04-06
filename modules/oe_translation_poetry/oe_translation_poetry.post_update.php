<?php

/**
 * @file
 * OpenEuropa Translation Poetry post updates file.
 */

declare(strict_types = 1);

use Drupal\oe_translation_poetry\Plugin\Field\FieldType\PoetryRequestIdItem;
use Drupal\tmgmt\Entity\Translator;

/**
 * Removing the poetry endpoint from the configuration.
 */
function oe_translation_poetry_post_update_0001(): void {
  $translator = Translator::load('poetry');
  if (!$translator) {
    return;
  }

  $settings = $translator->getSettings();
  if (!isset($settings['service_wsdl'])) {
    return;
  }

  // Remove the old service WSDL from the configuration to keep it inline
  // with the schema.
  unset($settings['service_wsdl']);
  $translator->setSettings($settings);
  $translator->save();
}

/**
 * Track existing Poetry translation requests.
 */
function oe_translation_poetry_post_update_0002(array &$sandbox) {
  if (!isset($sandbox['total'])) {
    // Enable the new ePoetry module and the legacy module.
    \Drupal::service('module_installer')->install([
      'oe_translation_epoetry',
      'oe_translation_poetry_legacy',
    ]);

    // Query database to find all the nodes that have at least a Poetry
    // translation request.
    $database = \Drupal::database();
    $query = $database->query("SELECT ji.item_id FROM tmgmt_job j
        INNER JOIN tmgmt_job_item ji ON j.tjid = ji.tjid
        WHERE j.translator = 'poetry' GROUP BY ji.item_id");
    $node_ids = $query->fetchAll();
    if (empty($node_ids)) {
      return t('No Poetry translations have been requested.');
    }

    // Initialize sandbox counters.
    $sandbox['node_ids'] = $node_ids;
    $sandbox['total'] = count($node_ids);
    $sandbox['current'] = 0;
    $sandbox['nodes_per_batch'] = 10;
  }

  // Get a slice from the results.
  $ids = array_slice($sandbox['node_ids'], $sandbox['current'], $sandbox['nodes_per_batch']);
  $entity_type_manager = \Drupal::entityTypeManager();
  $poetry_legacy_reference_storage = $entity_type_manager->getStorage('poetry_legacy_reference');
  $node_storage = $entity_type_manager->getStorage('node');
  $database = \Drupal::database();
  foreach ($ids as $id) {
    // Load the current node id.
    $node = $node_storage->load($id->item_id);
    if (!$node) {
      // If the doesn't exist, we skip this id.
      continue;
    }
    // Get the last Poetry translation request for the current node id. For
    // this we sort them first the entity revision ID and then by the version
    // in case the same revision has multiple requests.
    $query = $database->query("SELECT ji.item_id, ji.item_rid,
       j.poetry_request_id__code,j.poetry_request_id__year,
       j.poetry_request_id__number,j.poetry_request_id__version,
       j.poetry_request_id__part,j.poetry_request_id__product FROM tmgmt_job j
       INNER JOIN tmgmt_job_item ji ON j.tjid = ji.tjid
       WHERE j.translator = 'poetry' AND ji.item_id = $id->item_id
       ORDER BY ji.item_rid, j.poetry_request_id__version DESC LIMIT 1");
    $poetry_request_id_parts = $query->fetchAll();
    $poetry_request_id_parts = reset($poetry_request_id_parts);
    // Remove the first two elements of the array (node id and revision id).
    $poetry_request_id_parts = (array) $poetry_request_id_parts;
    array_shift($poetry_request_id_parts);
    array_shift($poetry_request_id_parts);
    // Retrieve the Poetry request ID.
    $poetry_request_id = PoetryRequestIdItem::toReference($poetry_request_id_parts);
    // Create a Legacy poetry request entity.
    $poetry_legacy_reference_storage->create([
      'node' => $node,
      'poetry_request_id' => $poetry_request_id,
    ])->save();
    $sandbox['current']++;
  }
  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);
  if ($sandbox['#finished'] === 1) {
    return t('A total of @created Legacy Poetry Requests have been created.', ['@created' => $sandbox['current']]);
  }
}
