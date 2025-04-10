<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Form for publishing evaluations and updating user roles.
 */
class PublishEvaluationsForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'publish_evaluations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get all users with role 'candidato_enviado'.
    $query = \Drupal::entityQuery('user')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('roles', 'candidato_enviado');
    $uids = $query->execute();

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('If all Submitted Candidates have been evaluated, and Fields of Research Rankings were approved, this action will:') . '</p>' .
      '<ul>' .
      '<li>' . $this->t('Add the Evaluated Candidate role to all users with the Submitted Candidate role.') . '</li>' .
      '<li>' . $this->t('Make evaluation results visible to candidates.') . '</li>' .
      '</ul>',
    ];

    $form['candidates'] = [
      '#type' => 'details',
      '#title' => $this->t('Candidates who will access the published evaluations'),
      '#open' => TRUE,
    ];

    if (!empty($uids)) {
      $candidates = [];
      foreach ($uids as $uid) {
        $user = User::load($uid);
        if ($user) {
          // Check if user already has the candidato_evaluado role.
          if (!$user->hasRole('candidato_evaluado')) {
            $candidates[] = $user->getDisplayName();
          }
        }
      }

      if (!empty($candidates)) {
        $form['candidates']['list'] = [
          '#theme' => 'item_list',
          '#items' => $candidates,
        ];
      }
      else {
        $form['candidates']['empty'] = [
          '#markup' => '<p>' . $this->t('All candidates with Submitted Candidate role already have the Evaluated Candidate role.') . '</p>',
        ];
      }
    }
    else {
      $form['candidates']['empty'] = [
        '#markup' => '<p>' . $this->t('No candidates with Submitted Candidate role found.') . '</p>',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to publish evaluation results?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get all users with role 'candidato_enviado'.
    $query = \Drupal::entityQuery('user')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('roles', 'candidato_enviado');
    $uids = $query->execute();

    $candidates_not_fully_evaluated = [];

    if (!empty($uids)) {
      foreach ($uids as $uid) {
        $user = User::load($uid);
        if ($user) {
          // Get the number of evaluators assigned to this candidate.
          $evaluators = $user->get('field_evaluators')->getValue();
          $number_evaluators = count($evaluators);

          // Get the number of evaluations submitted for this candidate.
          $evaluations = $user->get('field_evaluation')->getValue();
          $number_evaluations = count($evaluations);

          // Check if all evaluators have submitted their evaluations.
          if ($number_evaluators > $number_evaluations) {
            // Get candidate name for the error message.
            $nodePD = $this->getNodePerType('personal_data', $user);
            if ($nodePD) {
              $name = $nodePD->label();
              if ($nodePD->hasField('field_first_name') && !$nodePD->get('field_first_name')->isEmpty()) {
                $name .= ', ' . $nodePD->get('field_first_name')->value;
              }
              $candidates_not_fully_evaluated[] = $name;
            }
            else {
              $candidates_not_fully_evaluated[] = $user->getDisplayName();
            }
          }
        }
      }
    }

    // If there are candidates not fully evaluated, set an error message.
    if (!empty($candidates_not_fully_evaluated)) {
      $form_state->setError($form, $this->t('The following candidates have not been evaluated by all assigned evaluators: @candidates', [
        '@candidates' => implode(', ', $candidates_not_fully_evaluated),
      ]));
    }

    // Get all taxonomy terms of type field_of_research.
    $vid = 'field_of_research';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);

    foreach ($terms as $term) {
      $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term->tid);
      if (!$term_obj) {
        continue;
      }

      // Check if ranking PDF exists (approved by administrator)
      if ($term_obj->hasField('field_ranking_pdf') && $term_obj->get('field_ranking_pdf')->isEmpty()) {
        // Check if there are candidates with role 'candidato_enviado'
        // who have chosen this term in their research_data.
        $candidato_enviado_uids = \Drupal::entityQuery('user')
          ->accessCheck(TRUE)
          ->condition('status', 1)
          ->condition('roles', 'candidato_enviado')
          ->execute();

        // Only proceed if there are users with candidato_enviado role.
        if (!empty($candidato_enviado_uids)) {

          // Find research_data nodes that belong to
          // candidato_enviado users and reference this term.
          $research_data_query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'research_data')
            ->condition('field_choose_field', $term->tid)
            ->condition('uid', $candidato_enviado_uids, 'IN');
          $research_data_nids = $research_data_query->execute();

          // Only throw error if there are candidates who have chosen this term.
          if (!empty($research_data_nids)) {
            $form_state->setError($form, $this->t('Ranking was not approved by the Administrator for the field @name', [
              '@name' => $term->name,
            ]));
            continue;
          }
        }
      }

      // Check if all evaluator rankings are approved.
      if ($term_obj->hasField('field_evaluators_ranking_pdf') && $term_obj->hasField('field_approved_erpdf')) {
        $evaluator_rankings = $term_obj->get('field_evaluators_ranking_pdf')->referencedEntities();
        $approved_rankings = $term_obj->get('field_approved_erpdf')->referencedEntities();

        if (count($evaluator_rankings) != count($approved_rankings)) {
          // Get owners of files in both fields.
          $evaluator_owners = [];
          $approved_owners = [];

          foreach ($evaluator_rankings as $file) {
            $evaluator_owners[$file->getOwnerId()] = $file->getOwnerId();
          }

          foreach ($approved_rankings as $file) {
            $approved_owners[$file->getOwnerId()] = $file->getOwnerId();
          }

          // Find evaluators whose rankings are not approved.
          $missing_approvals = array_diff(array_keys($evaluator_owners), array_keys($approved_owners));

          if (!empty($missing_approvals)) {
            $missing_names = [];
            foreach ($missing_approvals as $uid) {
              $evaluator = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
              if ($evaluator) {
                $missing_names[] = $evaluator->getAccountName();
              }
            }

            $form_state->setError($form, $this->t('Field of Research "@name": The rankings submitted by the following evaluator(s) require approval before proceeding: @evaluators', [
              '@name' => $term->name,
              '@evaluators' => implode(', ', $missing_names),
            ]));
          }
        }
      }
    }
  }

  /**
   * Helper function to get a node of a specific type for a user.
   *
   * @param string $node_type
   *   The node type to retrieve.
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return \Drupal\node\Entity\Node|null
   *   The node entity or null if not found.
   */
  protected function getNodePerType($node_type, $user) {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $user->id())
      ->condition('type', $node_type);
    $nids = $query->execute();

    if (!empty($nids)) {
      return \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load(reset($nids));
    }

    return NULL;
  }

  /**
   * Notify the candidate that their evaluation results are available.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   */
  protected function notifyCandidate($user) {
    // Get the candidate's email.
    $to = $user->getEmail();
    if (!$to) {
      return;
    }

    // Prepare the email.
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'research_application_workflow';
    $key = 'evaluation_published';
    $langcode = $user->getPreferredLangcode();
    $from = 'mail@example.com';

    // Get the candidate's name.
    $name = $user->getAccountName();

    // Prepare the params.
    $params = [
      'user' => $user,
      'subject' => $this->t('Your application evaluation results are now available'),
      'message' => $this->t('Dear @name,

We are pleased to inform you that the evaluation results for your application are now available.
You can view your evaluation results by logging into your account and navigating to the "Evaluation" tab.
Thank you for your participation in our application process.

Regards,
ARAID'),
    ];

    // Send the email.
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, $from, TRUE);

    if (!$result['result']) {
      $this->messenger()->addWarning($this->t('There was a problem sending the notification email to @name.', [
        '@name' => $name,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get all users with role 'candidato_enviado'.
    $query = \Drupal::entityQuery('user')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('roles', 'candidato_enviado');
    $uids = $query->execute();

    $updated_count = 0;

    if (!empty($uids)) {
      foreach ($uids as $uid) {
        $user = User::load($uid);
        if ($user && !$user->hasRole('candidato_evaluado')) {
          // Add the candidato_evaluado role.
          $user->addRole('candidato_evaluado');
          $user->save();
          $updated_count++;

          // Notify the candidate that their evaluation results are available.
          $this->notifyCandidate($user);
        }
      }
    }

    if ($updated_count > 0) {
      $this->messenger()->addMessage($this->t('@count candidates have been given the Evaluated Candidate role.', [
        '@count' => $updated_count,
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('No candidates needed role updates.'));
    }

    $this->messenger()->addMessage($this->t('Evaluation results are now published and visible to candidates.'));

    $form_state->setRedirect('research_application_workflow.publish_evaluations');
  }

}
