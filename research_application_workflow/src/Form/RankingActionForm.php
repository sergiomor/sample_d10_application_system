<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form with a submit button for rankings.
 */
class RankingActionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ranking_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $parameters = \Drupal::routeMatch()->getParameters();
    $tid = $parameters->get('tid');

    $form['tid'] = [
      '#type' => 'hidden',
      '#default_value' => $tid,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Calculate Rankings'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * Populates EI content type fields.
   *
   * @param int $tid
   *   The taxonomy term ID.
   *
   * @return bool
   *   TRUE if the file was created, FALSE otherwise.
   */
  public function markCalculations($tid) {
    try {

      // Get users with role 'candidato_enviado' who have
      // research_data for this field.
      $query = \Drupal::entityQuery('user')
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('roles', 'candidato_enviado');
      $user_ids = $query->execute();

      // Get candiates to be ranked.
      $candidates_rank = [];
      foreach ($user_ids as $uid) {
        // Check if user has research_data for this field.
        $research_query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'research_data')
          ->condition('uid', $uid)
          ->condition('field_choose_field', $tid);
        $research_nids = $research_query->execute();

        if (empty($research_nids)) {
          continue;
        }

        // Get the user's personal data for messages.
        $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
        $personal_query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'personal_data')
          ->condition('uid', $uid);
        $personal_nids = $personal_query->execute();
        $personal_node = !empty($personal_nids) ? \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load(reset($personal_nids)) : NULL;
        $candidate_name = $personal_node ? $personal_node->getTitle() : $user->getAccountName();

        // Get evaluators from user entity.
        $evaluator_ids = $user->get('field_evaluators')->getValue();
        if (count($evaluator_ids) < 2) {
          \Drupal::messenger()->addWarning(t('Missing assigned evaluators for candidate @name.',
           ['@name' => $candidate_name]));
          continue;
        }

        // Get interest node for this candidate.
        $interest_query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'interest')
          ->condition('field_candidate', $uid);
        $interest_nids = $interest_query->execute();

        if (empty($interest_nids)) {
          \Drupal::messenger()->addWarning(t('No Expression of Interest found for candidate @name.',
            ['@name' => $candidate_name]));
          continue;
        }

        $interest_node = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load(reset($interest_nids));

        // Process each evaluator's marks.
        $marks = [];
        foreach ($evaluator_ids as $index => $evaluator) {
          $evaluator_id = $evaluator['target_id'];
          $evaluator_user = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->load($evaluator_id);

          // Get evaluations.
          $evaluations_fids = $user->get('field_evaluation')->getValue();
          $found_evaluation = FALSE;
          foreach ($evaluations_fids as $evaluation) {
            $evaluation_fid = $evaluation['value'];
            $evaluation_file = \Drupal::entityTypeManager()
              ->getStorage('file')
              ->load($evaluation_fid);
            // Check if this evaluation belongs to current evaluator.
            if ($evaluation_file && $evaluation_file->getOwnerId() == $evaluator_id) {
              // Found the evaluation for this evaluator.
              $found_evaluation = TRUE;
              break;
            }
          }

          if ($found_evaluation == FALSE) {
            \Drupal::messenger()->addWarning(t('No evaluation found from evaluator @evaluator for candidate @candidate.',
              ['@evaluator' => $evaluator_user->getAccountName(), '@candidate' => $candidate_name]));
            continue;
          }

          // Get marks from this evaluator for the candidate.
          $eval_query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'evaluation_form')
            ->condition('uid', $evaluator_id)
            ->condition('field_candidate', $uid);
          $eval_nids = $eval_query->execute();

          $eval_node = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load(reset($eval_nids));

          $total_mark = $eval_node->get('field_total_mark')->value;

          if (empty($total_mark)) {
            \Drupal::messenger()->addWarning(t('Mark not found from evaluator @evaluator for candidate @candidate.',
              ['@evaluator' => $evaluator_user->getAccountName(), '@candidate' => $candidate_name]));
            continue;
          }

          $marks[$index] = $total_mark;
        }

        if (count($marks) > 0) {
          $average = array_sum($marks) / count($marks);
          $interest_node->set('field_average', number_format($average, 1));
          // Calculate difference between min and max marks.
          $difference = number_format(max($marks) - min($marks), 1);
        }

        // If we have all three marks, update the interest node.
        if (isset($marks[0])) {
          $interest_node->set('field_marks_eva1', $marks[0]);

        }
        if (isset($marks[1])) {
          $interest_node->set('field_marks_eva2', $marks[1]);

        }
        if (isset($marks[2])) {
          $interest_node->set('field_marks_eva3', $marks[2]);

        }
        if (isset($difference)) {
          $interest_node->set('field_difference', $difference);

        }
        $interest_node->save();
        $candidates_rank[] = $uid;
      }

      if (!empty($candidates_rank)) {
        return TRUE;
      }
      else {
        \Drupal::messenger()->addWarning(t('No evaluation results found for the candidates.'));
        return FALSE;
      }

      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError('Error calculating rankings: ' . $e->getMessage());
      return FALSE;
    }
  }

/**
 * Calculates candidate rankings based on evaluation results.
 *
 * @param int $tid
 *   The taxonomy term ID for the application period.
 *
 * @return bool
 *   TRUE if rankings were calculated successfully, FALSE otherwise.
 *
 * This method:
 * - Retrieves candidates with role 'candidato_enviado'
 * - Gathers their evaluation results
 * - Calculates weighted rankings
 * - Stores the final rankings
 */
  public function rankingCalculations($tid) {
    try {
      // Get users with role 'candidato_enviado' who have
      // research_data for this field.
      $query = \Drupal::entityQuery('user')
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('roles', 'candidato_enviado');
      $user_ids = $query->execute();

      // Arrays to store rankings for each evaluation.
      $rankings_ev1 = [];
      $rankings_ev2 = [];
      $rankings_ev3 = [];

      // Get candidates marks and store them in arrays.
      foreach ($user_ids as $uid) {
        // Check if user has research_data for this field.
        $research_query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'research_data')
          ->condition('uid', $uid)
          ->condition('field_choose_field', $tid);
        $research_nids = $research_query->execute();

        if (empty($research_nids)) {
          continue;
        }

        $user = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->load($uid);

        // Get evaluation files for this candidate.
        $evaluation_files = $user->get('field_evaluation')->getValue();

        $evaluator_index = 0;
        if (!empty($evaluation_files)) {
          foreach ($evaluation_files as $file_ref) {
            $file = \Drupal::entityTypeManager()
              ->getStorage('file')
              ->load($file_ref['value']);

            if ($file) {
              // Get the owner (evaluator) of the file.
              $evaluator_id = $file->getOwnerId();
              $evaluator_index = $evaluator_index + 1;

              // Get evaluation marks from the evaluation form node.
              $query = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'evaluation_form')
                ->condition('field_candidate', $uid)
                ->condition('uid', $evaluator_id);
              $nids = $query->execute();

              if (!empty($nids)) {
                $nid = reset($nids);
                $node = \Drupal::entityTypeManager()
                  ->getStorage('node')
                  ->load($nid);

                // Store marks based on evaluator number.
                if ($node) {
                  $total_mark = $node->get('field_total_mark')->value;

                  switch ($evaluator_index) {
                    case 1:
                      $rankings_ev1[$uid] = $total_mark;
                      break;

                    case 2:
                      $rankings_ev2[$uid] = $total_mark;
                      break;

                    case 3:
                      $rankings_ev3[$uid] = $total_mark;
                      break;
                  }
                }
              }
            }
          }
        }

        // Sort rankings by marks (descending)
        arsort($rankings_ev1);
        arsort($rankings_ev2);
        arsort($rankings_ev3);

        // Get interest node for this candidate.
        $interest_query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'interest')
          ->condition('field_candidate', $uid);
        $interest_nids = $interest_query->execute();

        $interest_node = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load(reset($interest_nids));
        $average_position = [];
        if (isset($rankings_ev1[$uid])) {
          $position_ev1 = array_search($uid, array_keys($rankings_ev1)) + 1;
          $interest_node->set('field_position_eva1', $position_ev1);
          $average_position[] = $position_ev1;
        }
        if (isset($rankings_ev2[$uid])) {
          $position_ev2 = array_search($uid, array_keys($rankings_ev2)) + 1;
          $interest_node->set('field_position_eva2', $position_ev2);
          $average_position[] = $position_ev2;
        }
        if (isset($rankings_ev3[$uid])) {
          $position_ev3 = array_search($uid, array_keys($rankings_ev3)) + 1;
          $interest_node->set('field_position_eva3', $position_ev3);
          $average_position[] = $position_ev3;
        }
        if (count($average_position) > 0) {
          $interest_node->set('field_average_position', round(array_sum($average_position) / count($average_position)));
        }
        $interest_node->save();
      }
      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('research_application_workflow')->error('Error in rankingCalculations: @message', ['@message' => $e->getMessage]);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tid = $form_state->getValue('tid');
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->load($tid);
    $term_name = $term ? $term->getName() : '';

    // If rankingCalculations returns True run CreatePdf functon.
    if ($this->markCalculations($tid) && $this->rankingCalculations($tid)) {
      \Drupal::messenger()->addMessage($this->t('Rankings have been calculated for @name.', ['@name' => $term_name]));
    }
  }

}
