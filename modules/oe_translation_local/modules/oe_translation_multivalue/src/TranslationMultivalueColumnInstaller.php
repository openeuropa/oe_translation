<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue;

use Drupal\field\Entity\FieldStorageConfig;

/**
 * Enables the multivalue translation_id column on a given field.
 */
class TranslationMultivalueColumnInstaller {

  /**
   * Installs the column on the field.
   *
   * @param string $field_id
   *   The field ID in the format entity_type_id.field_name.
   */
  public static function installColumn(string $field_id) {
    $database = \Drupal::database();

    [$entity_type_id, $field_name] = explode('.', $field_id);

    $field_schema = [
      'description' => 'The translation ID.',
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
    ];

    /** @var \Drupal\Core\Entity\Sql\TableMappingInterface $table_mapping */
    $table_mapping = \Drupal::entityTypeManager()->getStorage($entity_type_id)->getTableMapping();
    $tables = $table_mapping->getAllFieldTableNames($field_name);
    $column_name = $field_name . '_translation_id';

    foreach ($tables as $table_name) {
      // Backup data from original table if there is data.
      $count = $database->select($table_name, 'p')
        ->countQuery()
        ->execute()
        ->fetchField();

      $has_data = $count > 0;

      if ($has_data) {
        $original_table = '{' . $table_name . '}';
        $backup_table = "{_$table_name}";
        $query_string = 'CREATE TABLE ' . $backup_table . ' LIKE ' . $original_table;
        $database->query($query_string);
        $query_string = 'INSERT ' . $backup_table . ' SELECT * FROM ' . $original_table;
        $database->query($query_string);

        // Wipe it.
        $database->truncate($table_name)->execute();
      }

      $field_exists = $database->schema()->fieldExists($table_name, $column_name);
      if (!$field_exists) {
        $database->schema()->addField($table_name, $column_name, $field_schema);
      }
    }

    try {
      // Update the field definition.
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      $field_storage->setSetting('translation_multivalue', TRUE);
      $field_storage->save();
    }
    catch (\Exception $exception) {
      // Do nothing, we want to restore the data below even if something
      // crashed here.
    }

    // Restore the data if we made a backup.
    foreach ($tables as $table_name) {
      $original_table = '{' . $table_name . '}';
      $backup_table = "{_$table_name}";
      if ($database->schema()->tableExists("_$table_name")) {
        $query_string = 'INSERT ' . $original_table . ' SELECT *, NULL as ' . $column_name . ' FROM ' . $backup_table;
        $database->query($query_string);
        $database->query('DROP TABLE ' . $backup_table);
      }
    }
  }

}
