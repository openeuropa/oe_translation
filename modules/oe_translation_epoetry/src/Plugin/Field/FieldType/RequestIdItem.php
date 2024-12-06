<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldType;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'epoetry_request_id' field type.
 *
 * @FieldType(
 *   id = "epoetry_request_id",
 *   label = @Translation("ePoetry Request ID"),
 *   category = @Translation("OE Translation"),
 *   default_widget = "epoetry_request_id_widget",
 *   default_formatter = "epoetry_request_id_formatter"
 * )
 */
class RequestIdItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $columns = static::getColumns();
    foreach ($columns as $column) {
      $value = $this->get($column)->getValue();
      if ($value === NULL || $value === '') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['code'] = DataDefinition::create('string')
      ->setLabel(t('Code'))
      ->setDescription(t('The code of the website or application that is requesting a translation'))
      ->setRequired(TRUE);
    $properties['year'] = DataDefinition::create('string')
      ->setLabel(t('Year'))
      ->setDescription(t('The year in which the request is made'))
      ->setRequired(TRUE);
    $properties['number'] = DataDefinition::create('integer')
      ->setLabel(t('Number'))
      ->setDescription(t('The request number given by ePoetry to be used in future requests until the version or part reach 99.'))
      ->setRequired(TRUE);
    $properties['version'] = DataDefinition::create('integer')
      ->setLabel(t('Version'))
      ->setDescription(t('The version of the content being requested. Can go up to 99.'))
      ->setRequired(TRUE);
    $properties['part'] = DataDefinition::create('integer')
      ->setLabel(t('Part'))
      ->setDescription(t('There can be 99 requests that use the same number given by ePoetry. The part keeps track of this.'))
      ->setRequired(TRUE);
    $properties['service'] = DataDefinition::create('string')
      ->setLabel(t('Service type'))
      ->setDescription(t('The type of service being made. Mostly WEBTRA (web translation).'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'code' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Code.',
        'length' => 255,
      ],
      'year' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Year.',
        'length' => 255,
      ],
      'number' => [
        'type' => 'int',
        'not null' => TRUE,
        'description' => 'Number.',
        'size' => 'normal',
      ],
      'version' => [
        'type' => 'int',
        'not null' => TRUE,
        'description' => 'Version.',
        'size' => 'normal',
      ],
      'part' => [
        'type' => 'int',
        'not null' => TRUE,
        'description' => 'Part.',
        'size' => 'normal',
      ],
      'service' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Service type.',
        'length' => 255,
      ],
    ];

    $schema = [
      'columns' => $columns,
    ];

    return $schema;
  }

  /**
   * Builds a reference string of all the values in this field type.
   *
   * @param array $values
   *   The values in this field.
   *
   * @return string
   *   The reference string.
   */
  public static function toReference(array $values): string {
    return implode('/', $values);
  }

  /**
   * Builds a DGT specific format of the reference.
   *
   * @param array $values
   *   The values in this field.
   *
   * @return string
   *   The reference string.
   */
  public static function toDgtFormattedReference(array $values): string {
    // Append leading zeroes to the number.
    return (string) new FormattableMarkup('@code-@year-@number(@version)-@part-@service', [
      '@code' => $values['code'],
      '@year' => $values['year'],
      '@number' => sprintf("%05d", $values['number']),
      '@version' => sprintf("%02d", $values['version']),
      '@part' => $values['part'],
      '@service' => $values['service'],
    ]);
  }

  /**
   * Returns a list of columns the field supports.
   *
   * @return array
   *   The column names.
   */
  public static function getColumns(): array {
    return ['code', 'year', 'number', 'version', 'part', 'service'];
  }

}
