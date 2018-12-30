<?php

namespace Drupal\fruity_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Unit-aware weight filter.
 *
 * Provide the following parameters from the definition:
 * - unit_column: (string) Name of the column where weight units are stored.
 *
 * @ViewsFilter("fruit_weight")
 */
class Weight extends FilterPluginBase {

  /**
   * Conversion factor between LBs and KGs.
   *
   * @var float
   */
  const LB_TO_KG = 0.4535924;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // All the site-builder configurable options should be declared in this
    // method. In our case we need site-builder to specify value, which consists
    // of the absolute value and the units.

    $options['value'] = [
      'contains' => [
        'value' => ['default' => ''],
        'unit' => ['default' => ''],
      ],
    ];

    // Since we are going to handle floating point calculation, it would be a
    // good idea to specify the precision so comparison of 2 floating point
    // values would be executed with up to the specified precision. In other
    // words, comparing 0.250001 to 0.25 might yield FALSE unless we round both
    // numbers to a given amount of digits after the decimal point.
    $options['precision'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions() {
    // Since filtering by its nature has the notion of operator, this method
    // from parent class allows filter handlers to specify which operators they
    // support. The parent class does the job of letting user choose one of
    // these operators on the Views UI and then the chosen operator becomes
    // available to us at $this->operator.
    return [
      '<' => $this->t('Is less than'),
      '<=' => $this->t('Is less than or equal to'),
      '=' => $this->t('Is equal to'),
      '!=' => $this->t('Is not equal to'),
      '>=' => $this->t('Is greater than or equal to'),
      '>' => $this->t('Is greater than'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    // Every option we have defined in ::defineOptions() should have a way to be
    // specified within ::valueForm(). So we throw in 2 elements into the $form:
    // the 'value' (which we make a nested array with 'value' and 'unit' keys)
    // and the 'precision'. Every option of our filter handler is available at
    // $this->options['name_of_the_option']. There is a shortcut for 'value'
    // option, which presumably holds the actual value we are filtering against,
    // $this->value basically equals to $this->options['value'] and it is
    // handled in the parent class for our convenience.

    $form['value']['#tree'] = TRUE;

    // The widget for entering value should be required either when it is not
    // exposed or when it is exposed and marked as required.
    $is_required = !$this->isExposed() || $this->options['expose']['required'];

    $form['value']['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#size' => 30,
      '#default_value' => $this->value['value'],
      '#required' => $is_required,
    ];

    $form['value']['unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Units'),
      '#options' => [
        'kg' => $this->t('Kg'),
        'lb' => $this->t('Lb'),
      ],
      '#default_value' => $this->value['unit'],
      '#required' => $is_required,
    ];

    $form['precision'] = [
      '#type' => 'number',
      '#title' => $this->t('Round till precision of'),
      '#min' => 0,
      '#step' => 1,
      '#field_suffix' => $this->t('digits after comma'),
      '#default_value' => $this->options['precision'],
      '#required' => $is_required,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // The query here is just a tiny bit further complex than the one from
    // weight sort handler. We still employ the same CASE .. WHEN .. THEN SQL
    // expression, but:
    // 1) We wrap it into ROUND() thus enforcing float point comparison up to
    //    a requested precision.
    // 2) Instead of converting everything arbitrarily to KGs, we now convert
    //    all the absolute numbers to the unit specified in
    //    $this->value['unit']. Such approach just produces better UX when
    //    combined with the precision of float comparison.
    // 3) Lastly, because it is not sorting but filtering, after we have the
    //    weight casted to the necessary unit, we compare it per specified
    //    operator to the filter condition which is also casted to the same unit
    //    and rounded to the same precision.

    $unit_column = $this->definition['unit_column'];
    $kg_coefficient = $this->value['unit'] == 'kg' ? 1 : 1 / self::LB_TO_KG;
    $lb_coefficient = $this->value['unit'] == 'lb' ? 1 : self::LB_TO_KG;

    $sql_snippet = <<<EOF
ROUND(
  CASE $this->tableAlias.$unit_column
    WHEN 'kg' THEN $this->tableAlias.$this->realField * :kg_coefficient
    WHEN 'lb' THEN $this->tableAlias.$this->realField * :lb_coefficient
  END
, :precision) $this->operator :value
EOF;

    // If it was a simpler condition, we would have used
    // $this->query->addWhere() but because our SQL snippet involves expression,
    // we ought to use ::addWhereExpression() which gives us enough freedom to
    // inject our formula.
    $this->query->addWhereExpression($this->options['group'], $sql_snippet, [
      ':value' => round($this->value['value'], $this->options['precision']),
      ':kg_coefficient' => $kg_coefficient,
      ':lb_coefficient' => $lb_coefficient,
      ':precision' => $this->options['precision'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    // This method is responsible for generating a one-line summary on the Views
    // UI. Basically we display 'grouped' or 'exposed' if that is the case,
    // otherwise we display the actual filter criterion currently specified in
    // this handler.
    // Hopefully you know the concepts of a 'grouped' and 'exposed' filter from
    // the Views UI.

    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    return $this->operator . ' ' . $this->value['value'] . $this->value['unit'];
  }

}
