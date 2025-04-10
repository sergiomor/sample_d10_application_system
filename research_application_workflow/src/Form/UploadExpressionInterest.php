<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Url;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 *
 */
class UploadExpressionInterest extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'upload_expression_interest_form';
  }

  /**
   * {@inheritdoc}
   *
   * Creates a form for IR's to upload signed EI's.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $uid = NULL,
  ) {
    // Check if there's a EI signed file for the candidate.
    $c_user = User::load($uid);
    $c_fid = $c_user->get('field_signed_ei_fid')->value;
    if ($c_fid) {
      $form['no-submission'] = [
        '#markup' => t('<p>This form was already submitted.</p>'),
      ];
    }
    else {
      // Create form to upload file.
      $form['attachments'] = [
        '#type' => 'managed_file',
        '#multiple' => FALSE,
        '#description'  => $this->t('One file only.<br>18 MB limit.
                    <br>Allowed types: pdf.'),
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf'],
          'file_validate_size' => [18485760],
        ],
      ];
      $form['uid'] = [
        '#type' => 'hidden',
        '#value' => $uid,
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
    $uid = NULL,
  ) {
    // Validate if IR can submit the EI for the candidate.
    $ir_user = \Drupal::currentUser();
    $ir_loaded = User::load($ir_user->id());
    $candidates = $ir_loaded->field_candidate->getValue();
    $candidates_array = [];
    foreach ($candidates as $candidate) {
      $candidates_array[] = $candidate['target_id'];
    }
    if (!in_array($form_state->getValue('uid'), $candidates_array)) {
      $this->messenger()->addMessage($this
        ->t('You cannot submit this form. 
            Please contact the administrator.'), 'error');
    }
    else {
      return;
    }
  }

  /**
   *
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $form_file = $form_state->getValue('attachments', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      try {
        // Create file.
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($form_file[0]);
        // Source and destination.
        $c_user = User::load($form_state->getValue('uid'));

        // Ensure the private directory exists.
        $file_system = \Drupal::service('file_system');
        $private_dir = 'private://';
        $file_system->prepareDirectory($private_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Ensure the applications directory exists.
        $applications_dir = 'private://applications';
        $file_system->prepareDirectory($applications_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Ensure the user's directory exists.
        $user_dir = 'private://applications/' . $c_user->GetAccountName();
        $file_system->prepareDirectory($user_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Get source and destination paths.
        $sourceUri = $file->getFileUri();
        $destinationUri = $user_dir . '/ExpressionInterest.pdf';

        // Move the file.
        $file_system->move($sourceUri, $destinationUri, FileSystemInterface::EXISTS_REPLACE);

        // Update file entity.
        $file->setFilename('ExpressionInterest.pdf');
        $file->setPermanent();
        $file->setFileUri($destinationUri);
        $file->save();

        // Save file in candidate's profile.
        $c_user->set('field_signed_ei_fid', ['target_id' => $file->id()]);
        $c_user->save();

        // Set success message.
        \Drupal::messenger()->addMessage($this
          ->t('The signed Expression of Interest was submitted sucessfully'),
          'status', TRUE);

        // Redirect to dashboard.
        $current_request = \Drupal::service('request_stack')
          ->getCurrentRequest();
        $current_request->query->set(
              'destination',
              Url::fromRoute('research_application_workflow.expressions_interest')
                ->toString()
          );
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Error uploading file: @message', ['@message' => $e->getMessage()]));
        \Drupal::logger('research_application_workflow')->error('Error uploading Expression of Interest: @message', ['@message' => $e->getMessage()]);
      }

      // Notify candidate.
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'research_application_workflow';
      $key = 'c_EI_notify';
      $to = $c_user->getEmail();
      $from = 'mail@example.com';
      $params = '';
      $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
      $send_now = TRUE;
      $result = $mailManager->mail($module, $key, $to, $language_code, $params,
            $from, $send_now);
      if ($result['result'] == TRUE) {
        $this->messenger()->addMessage($this->t('The Candidate has been 
                 contacted.'));
      }
      else {
        $this->messenger()->addMessage($this->t('There was a problem 
                 sending your message and it was not sent.'), 'error');
      }
    }
  }

}
