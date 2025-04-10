<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Form for approving evaluator rankings.
 */
class ApproveRankingForm extends ConfirmFormBase {

  /**
   * The user ID of the evaluator.
   *
   * @var int
   */
  protected $uid;

  /**
   * The taxonomy term ID of the research line.
   *
   * @var int
   */
  protected $tid;

  /**
   * The evaluator user entity.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $evaluator;

  /**
   * The taxonomy term entity.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  /**
   * The ranking file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $rankingFile;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'approve_ranking_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uid = NULL, $tid = NULL) {
    $this->uid = $uid;
    $this->tid = $tid;

    // Load the evaluator.
    $this->evaluator = User::load($this->uid);
    if (!$this->evaluator) {
      $this->messenger()->addError($this->t('Evaluator not found.'));
      return $this->redirect('research_application_workflow.rankings_admin');
    }

    // Load the term.
    $this->term = Term::load($this->tid);
    if (!$this->term) {
      $this->messenger()->addError($this->t('Research line not found.'));
      return $this->redirect('research_application_workflow.rankings_admin');
    }

    // Find the ranking file.
    $this->rankingFile = $this->findRankingFile();
    if (!$this->rankingFile) {
      $this->messenger()->addError($this->t('Ranking file not found.'));
      return $this->redirect('research_application_workflow.rankings_admin');
    }

    // Add file information to the form.
    $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($this->rankingFile->getFileUri());
    
    $form['ranking_info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ranking-info']],
    ];
    
    $form['ranking_info']['evaluator'] = [
      '#type' => 'item',
      '#title' => $this->t('Evaluator'),
      '#markup' => $this->evaluator->getAccountName(),
    ];
    
    $form['ranking_info']['research_line'] = [
      '#type' => 'item',
      '#title' => $this->t('Research Line'),
      '#markup' => $this->term->getName(),
    ];
    
    $form['ranking_info']['file'] = [
      '#type' => 'item',
      '#title' => $this->t('Ranking File'),
      '#markup' => '<a href="' . $file_url . '" target="_blank">' . $this->t('View File') . '</a>',
    ];
    
    $form['approval_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Approval File'),
      '#description' => $this->t('Please upload the approval file.'),
      '#upload_location' => 'private://rankings/approved/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf'],
      ],
      '#required' => TRUE,
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to approve the ranking submitted by @evaluator for @research_line?', [
      '@evaluator' => $this->evaluator ? $this->evaluator->getAccountName() : $this->t('Unknown'),
      '@research_line' => $this->term ? $this->term->getName() : $this->t('Unknown'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('research_application_workflow.rankings_admin');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->evaluator && $this->term && $this->rankingFile) {
      
      // Get the uploaded file.
      $file_id = $form_state->getValue('approval_file', 0);
      if (!$file_id || !isset($file_id[0])) {
        $this->messenger()->addError($this->t('No file was uploaded.'));
        return;
      }
      
      // Load the uploaded file.
      $file = File::load($file_id[0]);
      if (!$file) {
        $this->messenger()->addError($this->t('Could not load the uploaded file.'));
        return;
      }
      
      // Get original file details.
      $original_filename = $this->rankingFile->getFilename();
      
      // Get the file system service
      $file_system = \Drupal::service('file_system');
      
      // Get the current URI and create the new URI 
      // with the original filename.
      $current_uri = $file->getFileUri();
      $file_extension = pathinfo($current_uri, PATHINFO_EXTENSION);
      $directory = dirname($current_uri);
      $new_uri = $directory . '/' . pathinfo($original_filename, PATHINFO_FILENAME) . '.' . $file_extension;
      
      // Move the file to the new URI.
      try {

        // Get the real paths.
        $current_path = $file_system->realpath($current_uri);
        
        // Create the new URI.
        if ($file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY)) {
          // Copy the file to the new location (we use copy then delete to avoid issues with file being in use)
          if (copy($current_path, $file_system->realpath($new_uri))) {
            // Update the file entity with the new URI and filename
            $file->setFileUri($new_uri);
            $file->setFilename($original_filename);
            
            // Set the file as permanent and update owner to match the original file.
            $file->setPermanent();
            $file->setOwner($this->evaluator);
            $file->save();
            
            // Delete the original file
            unlink($current_path);
            
            // Save the file reference in the term's field_approved_erpdf field.
            if ($this->term->hasField('field_approved_erpdf')) {
              $this->term->get('field_approved_erpdf')->appendItem($file->id());
              $this->term->save();
              
              // Send notification to the evaluator.
              $this->notifyEvaluator();
              
              $this->messenger()->addStatus($this->t('The ranking has been approved.'));
            }
            else {
              $this->messenger()->addError($this->t('The field_approved_erpdf field does not exist on the term.'));
            }
          }
          else {
            $this->messenger()->addError($this->t('Could not copy the file to the new location.'));
          }
        }
        else {
          $this->messenger()->addError($this->t('Could not prepare the directory for the file.'));
        }
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('An error occurred while moving the file: @error', ['@error' => $e->getMessage()]));
      }
    }
    else {
      $this->messenger()->addError($this->t('Could not approve the ranking due to missing information.'));
    }
    
    $form_state->setRedirect('research_application_workflow.rankings_admin');
  }
  
  /**
   * Finds the ranking file for the evaluator and research line.
   *
   * @return \Drupal\file\Entity\File|null
   *   The file entity if found, null otherwise.
   */
  protected function findRankingFile() {

    // Check if the term has field_evaluators_ranking_pdf.
    if (!$this->term->hasField('field_evaluators_ranking_pdf')) {
      return NULL;
    }
    
    // Get all file references from the field
    $file_references = $this->term->get('field_evaluators_ranking_pdf')->referencedEntities();
    
    // Check each file to see if it's owned by the specified user.
    foreach ($file_references as $file) {

      // Load the full file entity.
      $loaded_file = File::load($file->id());
      if ($loaded_file && $loaded_file->getOwnerId() == $this->uid) {
        return $loaded_file;
      }
    }
    return NULL;
  }
  
  /**
   * Sends a notification to the evaluator about the approval.
   */
  protected function notifyEvaluator() {

    // Get the evaluator's email.
    $to = $this->evaluator->getEmail();
    if (!$to) {
      return;
    }
    
    // Prepare the email.
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'research_application_workflow';
    $key = 'ranking_approved';
    $langcode = $this->evaluator->getPreferredLangcode();
    $from = 'example@email.com';
    
    // Prepare the params.
    $params = [
      'evaluator' => $this->evaluator,
      'term' => $this->term,
      'file' => $this->rankingFile,
      'subject' => $this->t('Your ranking for @research_line has been approved', [
        '@research_line' => $this->term->getName(),
      ]),
      'message' => $this->t('Dear @name,

          Your ranking for the Field of Research "@research_line" has been approved.
          Thank you for your contribution.

          Regards,
          The Administration Team', [
        '@name' => $this->evaluator->getAccountName(),
        '@research_line' => $this->term->getName(),
      ]),
    ];
    
    // Send the email.
    $send_now = TRUE;
    return $mailManager->mail($module, $key, $to, $langcode, $params, 
        $from, $send_now);
  }
 } 
