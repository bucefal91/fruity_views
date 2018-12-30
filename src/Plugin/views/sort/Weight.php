<?php

namespace Drupal\fruity_views\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Unit-aware sorting by weight.
 *
 * To expose you further to the definition items, in this sort handler let's
 * receive the following parameters from the definition:
 * - unit_column: (string) Name of the column where weight units are stored.
 * - lb_to_kg: (float) A coefficient, multiplying by which we can convert lbs to
 *   kgs. Just for fun, let's pretend this coefficient might vary so we receive
 *   it from external environment instead of hard coding it internally in the
 *   handler.
 *
 * @ViewsSort("fruit_weight")
 */
class Weight extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This line makes sure our table (fruit) is properly JOINed within the
    // SQL. In our particular case it poses little benefit, because our table is
    // actually the base table, so there is nothing to JOIN, but remember that
    // your sort handler can be reused virtually anywhere in $views_data, and at
    // some point actually might be invoked on a non-base table.
    $this->ensureMyTable();

    // Do read the docblock comment of the
    // \Drupal\views\Plugin\views\query\Sql::addOrderBy() to understand better
    // what is happening here. We dynamically multiply value of 'weight' column
    // to appropriate coefficient so to have all the weights on the same scale
    // of KGs. Then we sort by the result of multiplication.

    $unit_column = $this->definition['unit_column'];

    // Never hurts to cast to the expected data type (float) thus making sure we
    // are not accidentally injecting anything malicious into our SQL.
    $lb_to_kg = (float) $this->definition['lb_to_kg'];

    $sql_snippet = <<<EOF
CASE $this->tableAlias.$unit_column
  WHEN 'kg' THEN $this->tableAlias.$this->realField
  WHEN 'lb' THEN $this->tableAlias.$this->realField * $lb_to_kg
END
EOF;

    $this->query->addOrderBy(NULL, $sql_snippet, $this->options['order'], $this->realField);
  }

}
