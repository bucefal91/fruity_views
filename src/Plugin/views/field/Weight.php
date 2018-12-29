<?php

namespace Drupal\fruity_views\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Views field plugin to display 'measured weight'.
 *
 * Definition items:
 * - additional fields:
 *   - units: Supply name of the DB column where units are stored
 *
 * @ViewsField("fruit_weight")
 */
class Weight extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();

    // We actually do not even have to introduce the additional 'units' column
    // ourselves because 'additional fields' property of field definition, in
    // fact, is magical one - whatever addtional columns are defined there get
    // automatically into the SELECT query in FieldPluginBase::query() method.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Let's not play smart about the conversion.. A hardcoded constant will do
    // it.
    $lbs_to_kg = 0.4535924;

    // Since our primary column is weight, we can get its value without
    // supplying the 2nd argument into the ::getValue() method.
    $absolute_weight = $this->getValue($values);

    // To retrieve a value of an additional field, just use the construction as
    // below. The 'units' key of $this->additional_fields is the name of
    // additional field whose value we intend to retrieve from $values. In fact
    // $this->additional_fields['units'] will get us alias of the additional
    // field 'units' under which it was included into the SELECT query.
    $units = $this->getValue($values, $this->additional_fields['units']);

    // If the actual value is in lbs, convert it to kilograms.
    if ($units == 'lb') {
      $absolute_weight *= $lbs_to_kg;
    }

    // Now it all reduces to just pretty-printing the kilogram amount. This is
    // the actual content Views will display for our field.
    return $this->t('@weight kg', [
      '@weight' => round($absolute_weight, 2),
    ]);
  }

}
