<?php

namespace Drupal\fruity_views\Plugin\views\argument_validator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;

/**
 * Validate whether an argument is a number that ends with a given substring.
 *
 * @ViewsArgumentValidator(
 *   id = "number_ends_with",
 *   title = @Translation("Numeric ends with")
 * )
 */
class NumericEndsWith extends ArgumentValidatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    // This option will hold a white list of strings argument may terminate
    // with.
    $options['white_list'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Let the site-builder specify white list of allowed terminations for the
    // argument.

    $form['white_list'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ends with'),
      '#description' => $this->t('Specify through comma substrings the argument may end with.'),
      '#default_value' => implode(', ', $this->options['white_list']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    // Because we receive the white list of allowed terminations as a string,
    // we need to explode it into array during the validation step and place it
    // back as the value of the 'white_list' form element.

    // This list of parents was complied in empirical way. I just placed a break
    // point here and examined the contents of $form_state->getValues() to
    // figure out what is the actual nesting of form elements.
    $parents = ['options', 'validate', 'options', $this->getPluginId(), 'white_list'];

    $white_list = $form_state->getValue($parents);
    $white_list = array_filter(array_map('trim', explode(',', $white_list)));

    $form_state->setValue($parents, $white_list);
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    // Actually execute the validation logic. First, make sure the argument
    // finishes with a white listed termination. In case it does, also make sure
    // all the rest of it is numeric.
    foreach ($this->options['white_list'] as $white_list) {
      $suffix_length = mb_strlen($white_list);
      if (mb_substr($argument, -$suffix_length) == $white_list && is_numeric(mb_substr($argument, 0, -$suffix_length))) {
        return TRUE;
      }
    }

    // If none of the above yielded TRUE, it means the argument hasn't passed
    // validation and we should fail it by returning FALSE.
    return FALSE;
  }

}
