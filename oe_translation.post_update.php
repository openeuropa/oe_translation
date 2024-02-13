<?php

/**
 * @file
 * Post update functions for OE Translation.
 */

declare(strict_types=1);

use Drupal\user\Entity\Role;

/**
 * Install 2.x modules.
 */
function oe_translation_post_update_0001(&$sandbox = NULL) {
  \Drupal::service('module_installer')->install([
    'oe_translation_local',
    'oe_translation_remote',
  ]);
}

/**
 * Adds extra permissions to the oe_translator role.
 */
function oe_translation_post_update_0002(&$sandbox = NULL) {
  $role = Role::load('oe_translator');
  if (!$role) {
    return;
  }
  $role->grantPermission('accept translation request');
  $role->grantPermission('sync translation request');
  $role->save();
}
