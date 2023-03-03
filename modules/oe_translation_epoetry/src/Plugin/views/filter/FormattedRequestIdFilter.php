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
