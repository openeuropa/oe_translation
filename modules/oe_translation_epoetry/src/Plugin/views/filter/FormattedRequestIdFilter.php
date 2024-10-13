<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Plugin\views\filter;

use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\RequestIdItem;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filters by the formatted request ID.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("oe_translation_epoetry_request_id_filter")
 */
class FormattedRequestIdFilter extends StringFilter {

  /**
   * {@inheritdoc}
   */
  public function operators() {
    return [
      '=' => [
        'title' => $this->t('Is equal to'),
        'short' => $this->t('='),
        'method' => 'opEqual',
        'values' => 1,
      ],
      'contains' => [
        'title' => $this->t('Contains'),
        'short' => $this->t('contains'),
        'method' => 'opContains',
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $columns = [];
    foreach (RequestIdItem::getColumns() as $column) {
      $columns[] = 'request_id_' . $column;
    }

    $expression = implode(", '/', ", $columns);
    $expression = "CONCAT($expression)";

    // Process the value into a simple concatenation of all the column data
    // so that we can run a simple sql expression.
    $value = preg_replace_callback('/\d+/', function ($matches) {
      // Remove leading zeros using ltrim.
      $trimmed = ltrim($matches[0], '0');

      // Ensure at least one digit is present
      // (i.e., replace an empty string with '0').
      return $trimmed === '' ? '0' : $trimmed;
    }, $this->value);
    $value = str_replace('-', '/', $value);
    $this->value = str_replace(['(', ')'], ['/', ''], $value);

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($expression) {
    $placeholder = $this->placeholder();
    $operator = $this->getConditionOperator('=');
    $this->query->addWhereExpression($this->options['group'], "$expression $operator $placeholder", [$placeholder => $this->value]);
  }

  /**
   * {@inheritdoc}
   */
  protected function opContains($expression) {
    $placeholder = $this->placeholder();
    $operator = $this->getConditionOperator('LIKE');
    $this->query->addWhereExpression($this->options['group'], "$expression $operator $placeholder", [$placeholder => '%' . $this->connection->escapeLike($this->value) . '%']);
  }

}
