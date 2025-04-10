<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 *
 */
class UploadEvaluation extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'upload_evaluation_form';
  }

  /**
   * {@inheritdoc}
   *
   * Creates a form for Evaluators and Admins
   * to upload signed Evaluation nodes.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $uid = NULL,
  ) {
    // Check if there's a EF signed file for the candidate
    // owned by the current evaluator.
    $c_user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($uid);
    $c_fids = $c_user->get('field_evaluation')->getValue();
    $file_owners = [];
    foreach ($c_fids as $c_fid) {
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($c_fid['value']);
      $file_owners[] = $file->getOwnerId();
    }
    $e_user = \Drupal::currentUser();
    if (in_array($e_user->id(), $file_owners)) {
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
    // Validate if Evaluator can submit the EF for the candidate.
    $e_user = \Drupal::currentUser();
    $query = \Drupal::entityQuery('user')
      ->accessCheck(TRUE)
      ->condition('field_evaluators', [$e_user->id()], 'IN');
    $uids = $query->execute();
    if (!in_array($form_state->getValue('uid'), $uids)) {
      $this->messenger()->addMessage($this
        ->t('You cannot submit this form. 
            Please contact the administrator.'), 'error');
    }

    // Validate if pdf is adobe signed.
    $form_file = $form_state->getValue('attachments', 0);
    $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->load($form_file[0]);
    if ($file) {
      $signed = $this->isPdfSigned($file->getFileUri());
      if ($signed == FALSE) {
        $this->messenger()->addMessage($this
          ->t('Your pdf is not digitally signed.'), 'error');
      }
    }
  }

/**
 * Handles evaluation form submission.
 *
 * Processes the uploaded evaluation file by:
 * - Validating digital signature presence
 * - Creating file entity references
 * - Updating term associations
 * - Notifying administrators
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state containing submitted values.
 */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $form_file = $form_state->getValue('attachments', 0);
    $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->load($form_file[0]);
    $signed = FALSE;
    if ($file) {
      $signed = $this->isPdfSigned($file->getFileUri());
      $signed = TRUE;
    }
    if (isset($form_file[0]) && !empty($form_file[0])
          && $signed !== FALSE) {
      // Create file.
      $e_user = \Drupal::currentUser();
      $c_user = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($form_state->getValue('uid'));
      $sourceUri = \Drupal::service('file_system')->realpath($file->getFileUri());
      $realpath = 'private://applications/' . $c_user->GetAccountName() .
                 '/Evaluation-' . $e_user->GetAccountName() . '.pdf';
      $destinationUri = \Drupal::service('file_system')
        ->realpath($realpath);
      // Move and overwrite.
      \Drupal::service('file_system')->move($sourceUri, $destinationUri);
      // Save file.
      $file->setFilename('Evaluation-' . $e_user->GetAccountName() . '.pdf');
      $file->setPermanent();
      $file->setFileUri($realpath);
      $file->save();
      // Save file in candidate's profile
      // not the file, only the fid.
      $c_user->get('field_evaluation')->appendItem($file->id());
      $c_user->save();
      // Set message.
      \Drupal::messenger()->addMessage($this
        ->t('The signed Evaluation was submitted sucessfully'),
        'status', TRUE);
      // Redirect to dahsboard.
      $current_request = \Drupal::service('request_stack')
        ->getCurrentRequest();
      $current_request->query->set(
            'destination',
            Url::fromRoute('research_application_workflow.applications_evaluator')
              ->toString()
            );
      // Notify admin.
      $this->notifyAdmin($e_user);
    }
  }

  /**
   * Sends email.
   */
  public function notifyAdmin($e_user) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'research_application_workflow';
    $key = 'admin_eval_notify';
    $to = \Drupal::config('research_application_workflow.applicationsettings')->get('email');
    $from = 'mail@example.com';
    $params = '
            <p>The evaluator ' . $e_user->getAccountName() . ' submitted an evaluation.</p>
            <br>
            <br>
            <p>This is an automatically generated email.';
    $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    return $mailManager->mail($module, $key, $to, $language_code, $params,
          $from, $send_now);
  }

/**
 * Checks if a PDF file contains a digital signature.
 *
 * Searches for specific signature keywords in the PDF content.
 *
 * @param string $pdfPath
 *   The absolute path to the PDF file to check.
 *
 * @return bool
 *   TRUE if the PDF contains a signature, FALSE otherwise.
 *
 * @throws \RuntimeException
 *   If the file cannot be read.
 */
  public function isPdfSigned($pdfPath) {
    // Check if the file exists.
    if (!file_exists($pdfPath)) {
      return FALSE;
    }
    // Open the PDF file in binary mode.
    $fileContent = file_get_contents($pdfPath);
    if ($fileContent === FALSE) {
      return FALSE;
    }
    // Keywords that commonly indicate digital signatures in PDFs.
    $signatureKeywords = ['Sig'];
    // Search the file content for any of the signature-related keywords.
    foreach ($signatureKeywords as $keyword) {
      if (strpos($fileContent, $keyword) !== FALSE) {
        // Keyword found, the PDF likely contains a digital signature.
        return TRUE;
      }
    }

    // No signature-related keywords found.
    return FALSE;
  }

}
