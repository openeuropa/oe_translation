<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Generates a new ID for a given field value translation_id.
 */
class MultivalueTranslationIdGenerator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new MultivalueTranslationIdGenerator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UuidInterface $uuid, Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->uuid = $uuid;
    $this->database = $database;
  }

  /**
   * Generates a UUID for the translation value.
   *
   * We also do a check to make sure the UUID doesn't exist already in the DB.
   *
   * @param string $field_id
   *   The field ID in the format entity_type.field_name.
   *
   * @return string
   *   The UUID.
   */
  public function generateTranslationUuid(string $field_id): string {
    [$entity_type_id, $field_name] = explode('.', $field_id);
    $table_mapping = $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping();
    $table = $table_mapping->getFieldTableName($field_name);
    $column = $field_name . '_translation_id';
    $exists = TRUE;
    while ($exists) {
      $uuid = $this->uuid->generate();
      $query = sprintf("SELECT %s FROM {%s} WHERE %s = '%s'", $column, $table, $column, $uuid);
      $exists = !empty($this->database->query($query)->fetchAll());
      if (!$exists) {
        // Normally, it shouldn't exist because it's a UUID, but just in case.
        return $uuid;
      }
    }
  }

}
