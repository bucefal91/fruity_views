<?php

namespace Drupal\fruity_views\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Argument for measured weight columns.
 *
 * Provide the following parameters from the definition:
 * - unit_column: (string) Name of the column where weight units are stored.
 *
 * @ViewsArgument("fruit_weight")
 */
class Weight extends ArgumentPluginBase {

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

    // Let's keep the precision of float comparison trick here in the argument
    // too, just as we had it in the filter handler.
    $options['precision'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // The ::buildOptionsForm() is the method to use whenever our argument
    // handler wants to allow site-builder to configure it somehow.
    $form['precision'] = [
      '#type' => 'number',
      '#title' => $this->t('Round till precision of'),
      '#min' => 0,
      '#step' => 1,
      '#field_suffix' => $this->t('digits after comma'),
      '#default_value' => $this->options['precision'],
      '#required' => TRUE,
    ];
  }

  /**
   * Build the query based upon the formula
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    $arg = $this->explodeArgument($this->argument);

    $unit_column = $this->definition['unit_column'];
    $kg_coefficient = $arg['unit'] == 'kg' ? 1 : 1 / self::LB_TO_KG;
    $lb_coefficient = $arg['unit'] == 'lb' ? 1 : self::LB_TO_KG;

    $sql_snippet = <<<EOF
ROUND(
  CASE $this->tableAlias.$unit_column
    WHEN 'kg' THEN $this->tableAlias.$this->realField * :kg_coefficient
    WHEN 'lb' THEN $this->tableAlias.$this->realField * :lb_coefficient
  END
, :precision) = :value
EOF;

    // The difference in this invocation of ::addWhereExpression() compared to
    // the invocation in the filter handler is that here part of the DB
    // placeholders come from $this->options (such as precision) and others come
    // from the actual argument we have received, i.e. derived from
    // $this->argument.
    $this->query->addWhereExpression(0, $sql_snippet, [
      ':value' => round($arg['value'], $this->options['precision']),
      ':kg_coefficient' => $kg_coefficient,
      ':lb_coefficient' => $lb_coefficient,
      ':precision' => $this->options['precision'],
    ]);
  }

  /**
   * Explode the raw argument into the value and unit pieces.
   *
   * @param string $argument
   *   Raw argument to be exploded.
   *
   * @return array
   *   Array with the following structure:
   *   - value: (float) absolute value supplied in the input argument.
   *   - unit: (string) unit of measure supplied in the input argument.
   */
  protected function explodeArgument($argument) {
    // This is just a supportive method not to pollute the ::query() method with
    // such low-level details as actual parsing of string into 'value' and
    // 'unit' properties.

    return [
      'value' => mb_substr($argument, 0, -2),
      'unit' => mb_substr($argument, -2),
    ];
  }

}
