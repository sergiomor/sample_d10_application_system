<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a form for filtering applications by field of research.
 */
class FieldOfResearchFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_of_research_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $selected_field = NULL) {
    // Add a wrapper for the form and results
    $form['#prefix'] = '<div id="field-of-research-filter-wrapper">';
    $form['#suffix'] = '</div>';
    
    // Load all taxonomy terms from field_of_research vocabulary
    $vid = 'field_of_research';
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vid);
      
    $options = ['' => $this->t('- All Fields of Research -')];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }
    
    $form['field_of_research'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by Field of Research'),
      '#options' => $options,
      '#default_value' => $selected_field,
    ];
    
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetSubmit'],
      '#limit_validation_errors' => [],
    ];
    
    // Add classes for the form
    $form['#attributes']['class'][] = 'field-of-research-filter-form';
    
    // Add some CSS to improve the appearance
    $form['#attached']['library'][] = 'system/admin';
    
    return $form;
  }

  /**
   * Submit handler for the reset button.
   */
  public function resetSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('research_application_workflow.applications_admin');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field_of_research = $form_state->getValue('field_of_research');
    
    // If not using AJAX, redirect to the same page with the filter parameter
    $url = Url::fromRoute('research_application_workflow.applications_admin', [], 
      ['query' => ['field_of_research' => $field_of_research]])->toString();
    
    $response = new RedirectResponse($url);
    $response->send();
  }
}
