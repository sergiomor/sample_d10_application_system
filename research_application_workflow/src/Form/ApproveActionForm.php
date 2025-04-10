<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Provides a form with a submit button for rankings.
 */
class ApproveActionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'approve_action_form';
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
      '#value' => $this->t('Aprove Rankings'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * Create a PDF file from the rankings controller output.
   *
   * @param int $tid
   *   The taxonomy term ID.
   *
   * @return bool
   *   TRUE if the file was created, FALSE otherwise.
   */
  protected function createPdf($tid) {
    try {
      $print_engine = \Drupal::service('plugin.manager.entity_print.print_engine')
        ->createSelectedInstance('pdf');
      $print_builder = \Drupal::service('entity_print.print_builder');

      // Get the term name for the filename.
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($tid);
      $term_name = $term ? $term->getName() : 'unknown';

      // Generate filename - ensure no colons or special characters.
      $base_name = preg_replace('/[^a-z0-9]+/', '_', strtolower($term_name));
      $filename = 'Ranking_' . $base_name . '.pdf';

      // Ensure private directory exists.
      $private_path = \Drupal::service('file_system')->realpath('private://');
      if (!file_exists($private_path . '/rankings')) {
        mkdir($private_path . '/rankings', 0755, TRUE);
      }

      $pdf_path = "rankings/" . $filename;
      // Generate PDF.
      $uri = $print_builder
        ->savePrintable([$term], $print_engine, $scheme = 'private', $pdf_path);

      // Create managed file entity.
      $file = File::create([
        'filename' => $filename,
        'uri' => $uri,
        'uid' => \Drupal::currentUser()->id(),
        'status' => 1,
      ]);
      $file->setPermanent();
      $file->save();

      // Store the file ID in the term.
      if ($term) {
        $term->set('field_ranking_pdf', $file->id());
        $term->save();
      }

      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('research_application_workflow')->error('PDF generation failed: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Prevent evaluators from editing evaluation forms for candidates in an approved ranking.
   *
   * @param int $tid
   *   The taxonomy term ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  protected function preventEvaluatorsEdit($tid) {
    try {
      // Get taxonomy term.
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($tid);

      if (!$term) {
        \Drupal::logger('research_application_workflow')->error('Term @tid not found', ['@tid' => $tid]);
        return FALSE;
      }

      // Check if term has a ranking PDF (meaning it's approved)
      $fid = $term->get('field_ranking_pdf')->target_id;
      if (!$fid) {
        \Drupal::logger('research_application_workflow')->notice('No ranking PDF for term @tid, skipping permission update', ['@tid' => $tid]);
        // No PDF, so nothing to do.
        return TRUE;
      }

      // Get candidates with research lines equal to $tid.
      $user_ids = \Drupal::entityQuery('user')
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('roles', 'candidato_enviado')
        ->execute();

      $candidate_ids = [];
      foreach ($user_ids as $uid) {
        // Check if user has research_data for this field.
        $research_query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'research_data')
          ->condition('uid', $uid)
          ->condition('field_choose_field', $tid);
        $research_nids = $research_query->execute();

        if (!empty($research_nids)) {
          $candidate_ids[$uid] = $uid;
        }
      }

      if (empty($candidate_ids)) {
        \Drupal::logger('research_application_workflow')->notice('No candidates found for term @tid', ['@tid' => $tid]);
        return TRUE;
      }

      // Load the candidate users.
      $candidates = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadMultiple($candidate_ids);

      $count = 0;

      // For each candidate, find their evaluators
      // and the corresponding evaluation forms.
      foreach ($candidates as $candidate) {
        $evaluators = $candidate->get('field_evaluators')->getValue();

        foreach ($evaluators as $evaluator) {
          if (!isset($evaluator['target_id'])) {
            continue;
          }

          $evaluator_id = $evaluator['target_id'];

          // Find evaluation forms created by this evaluator for this candidate.
          $evaluation_nids = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'evaluation_form')
            ->condition('uid', $evaluator_id)
            ->condition('field_candidate', $candidate->id())
            ->execute();

          if (!empty($evaluation_nids)) {
            // Update the node access settings for these evaluation forms.
            foreach ($evaluation_nids as $nid) {
              $node = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->load($nid);

              if ($node) {
                // Set a flag field indicating this evaluation is locked.
                $node->set('field_evaluation_locked', TRUE);
                $node->save();
                $count++;

                \Drupal::logger('research_application_workflow')->notice(
                  'Evaluation form @nid locked for candidate @cid and evaluator @eid',
                  [
                    '@nid' => $nid,
                    '@cid' => $candidate->id(),
                    '@eid' => $evaluator_id,
                  ]
                );
              }
            }
          }
          // Notify evaluators of Ranking Approval.
          $term_name = $term ? $term->getName() : '';
          $this->notifyEvaluators($evaluator_id, $term_name);
        }
      }

      \Drupal::messenger()->addMessage(t('Locked @count evaluation forms for field @field.',
        [
          '@count' => $count,
          '@field' => $term->getName(),
        ]
      ));

      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('research_application_workflow')->error('Failed to update evaluation permissions: @message', ['@message' => $e->getMessage()]);
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

    // Generate PDF.
    if ($this->createPdf($tid)) {
      $this->preventEvaluatorsEdit($tid);
      \Drupal::messenger()->addMessage($this->t('Rankings PDF has been generated for @term.', ['@term' => $term_name]));
    }
    else {
      \Drupal::messenger()->addError($this->t('Failed to generate PDF for @term.', ['@term' => $term_name]));
    }
  }

  /**
  * Notifies evaluators about rankings ready for approval via email.
  *
  * @param int $evaluator_id
  *   The evaluator's user ID.
  * @param string $term_name
  *   The name of the term/application period being evaluated.
  *
  * @return bool
  *   TRUE if notification was sent successfully, FALSE otherwise.
  */
  public function notifyEvaluators($evaluator_id, $term_name) {
    try {
      $e_user = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($evaluator_id);
      $email = $e_user->getEmail();
      $this->sendEmailEvaluator($email, $term_name);
      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('research_application_workflow')->error('Failed to notify evaluator: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
  * Sends email notification to evaluator about rankings ready for approval.
  *
  * @param string $email
  *   The evaluator's email address.
  * @param string $term_name
  *   The name of the term/application period being evaluated.
  */
  public function sendEmailEvaluator($email, $term_name) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'research_application_workflow';
    $key = 'evaluator_ranking_approval_notify';
    $to = $email;
    $from = 'mail@example.com';
    $params = '
        <p>Rankings for ' . $term_name .
        ' ready for Approval.</p>
        <p>Please login to your account to submit your Rankings Approval.</p>
        <br>
        <br>
        <p>This is an automatically generated email.';
    $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    return $mailManager->mail($module, $key, $to, $language_code, $params,
        $from, $send_now);
  }

}
