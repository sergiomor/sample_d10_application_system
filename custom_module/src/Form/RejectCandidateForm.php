<?php

namespace Drupal\custom_module\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a form for rejecting a candidate.
 */
class RejectCandidateForm extends ConfirmFormBase {

  /**
   * The candidate user ID.
   *
   * @var int
   */
  protected $candidateId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'reject_candidate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uid = NULL) {
    $this->candidateId = $uid;
    
    // Load the candidate user
    $candidate = User::load($this->candidateId);
    if (!$candidate) {
      $this->messenger()->addError($this->t('Candidate not found.'));
      return new RedirectResponse(Url::fromRoute('custom_module.expressions_interest')->toString());
    }
    
    // Get candidate's personal data
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $this->candidateId)
      ->condition('type', 'personal_data');
    $pd_nid = $query->execute();
    
    if (count($pd_nid) > 0) {
      $nodePD = Node::load(reset($pd_nid));
      $candidate_name = $nodePD->label() . ', ' . $nodePD->get('field_first_name')->value;
    } else {
      $candidate_name = $candidate->getAccountName();
    }
    
    $form['candidate_name'] = [
      '#markup' => '<p>' . $this->t('Candidate: @name', ['@name' => $candidate_name]) . '</p>',
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to reject this candidate?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('custom_module.expressions_interest');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action will remove the candidate from your list and notify them. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Reject Candidate');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load the candidate user
    $candidate = User::load($this->candidateId);
    if (!$candidate) {
      $this->messenger()->addError($this->t('Candidate not found.'));
      $form_state->setRedirect('custom_module.expressions_interest');
      return;
    }
    
    // Load the current researcher user
    $researcher = \Drupal::currentUser();
    $researcher_id = $researcher->id();
    $researcher_user = User::load($researcher_id);
    
    // 1. Remove candidate from researcher's field_candidate
    if ($researcher_user && $researcher_user->hasField('field_candidate')) {
      $candidates = $researcher_user->get('field_candidate')->getValue();
      $updated_candidates = [];
      
      foreach ($candidates as $candidate_item) {
        if ($candidate_item['target_id'] != $this->candidateId) {
          $updated_candidates[] = $candidate_item;
        }
      }
      
      $researcher_user->set('field_candidate', $updated_candidates);
      $researcher_user->save();
    }
    
    // 2. Remove researcher from candidate's field_researcher
    if ($candidate && $candidate->hasField('field_researcher')) {
      $candidate->set('field_researcher', NULL);
      $candidate->save();
    }
    
    // 3. Send notification email to candidate
    $this->notifyCandidate($candidate, $researcher_user);
    
    // Set success message
    $this->messenger()->addStatus($this->t('The candidate has been rejected and removed from your list.'));
    
    // Redirect back to expressions of interest page
    $form_state->setRedirect('custom_module.expressions_interest');
  }
  
  /**
   * Notify the candidate that they have been rejected.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param \Drupal\user\Entity\User $researcher
   *   The researcher who rejected the candidate.
   */
  protected function notifyCandidate($user, $researcher) {
    // Get the candidate's email
    $to = $user->getEmail();
    if (!$to) {
      return;
    }
    
    // Prepare the email
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'custom_module';
    $key = 'candidate_rejection';
    $langcode = $user->getPreferredLangcode();
    $from = 'servidor@araid.es';
    
    // Get the candidate's name and researcher's name
    $name = $user->getAccountName();
    
    // Prepare the params
    $params = [
      'user' => $user,
      'subject' => $this->t('Your Expression of Interest has been rejected'),
      'message' => $this->t('Dear candidate,

the Research Manager/Host Institution of your choice has declined the invitation to complete the expression of interest.

Best Regards,
ARAID'),
    ];
    
    // Send the email
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, $from, TRUE);
    
    if (!$result['result']) {
      $this->messenger()->addWarning($this->t('There was a problem sending the rejection email to @name.', [
        '@name' => $name,
      ]));
    }
  }

}
