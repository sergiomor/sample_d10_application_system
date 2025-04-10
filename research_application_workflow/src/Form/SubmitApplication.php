<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * Handles the submission of a research application.
 *
 * This form:
 * - Checks if the candidate has submitted the signed Expression of Interest
 * - Prepares and sends application confirmation and notification emails
 * - Creates a PDF of the application
 * - Copies research proposal and CV files to the application directory
 * - Updates the personal data node's creation timestamp
 * - Grants the 'candidato_enviado' role to the candidate
 */
class SubmitApplication extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $uid = NULL,
  ) {
    // Check if fid exists in candidato's user profile
    // meaning that the IR submitted the signed EI.
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($uid);
    $roles = $user->getRoles();
    // Add and candidato.
    if (in_array('candidato', $roles)) {
      if ($user->get('field_signed_ei_fid')->target_id) {
        $form['markup-1'] = [
          '#markup' => t('<h3>Are you sure you want to finalize 
                            and submit your application?</h3>'),
        ];
        $form['markup-2'] = [
          '#markup' => t('<p><strong>This action cannot be undone.</strong></p>
                    <p>Once submitted for evaluation you will not be able 
                        to modify your application.</p>'),
        ];
        $form['uid'] = [
          '#type' => 'hidden',
          '#value' => $uid,
        ];
        $form['submit'] = [
          '#type' => 'submit',
          '#weight' => 1,
          '#value' => $this->t('Submit your application'),
        ];
      }
      else {
        $form['markup-1'] = [
          '#markup' => t('<p><strong>The host institution have not 
                        finished the expression of interest yet</strong></p>'),
        ];
      }
    }
    else {
      $form['markup-1'] = [
        '#markup' => t('<p><strong>Your application was sent.</strong></p>'),
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
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ) {

    // Change role to candidato enviado.
    $current_user = \Drupal::currentUser();
    $cr_uid = $current_user->id();
    $c_user = User::load($cr_uid);
    $c_user->addRole('candidato_enviado');
    $c_user->removeRole('candidato');
    $c_user->save();
    // Create application pdf.
    $this->createPdf($cr_uid);
    // Copy Research Proposal.
    $this->copyNodeFile($cr_uid);
    // Copy CV.
    $this->copyCVFile($cr_uid);
    // Change PD node creation day.
    $this->setApplicationDate($cr_uid);
    // Set message.
    $this->messenger()->addMessage($this->t('Application submitted sucessfully.'));
    // Notify admin and candidate.
    $this->notifyAdmin($c_user);
    $this->notifyCandidate($c_user);
  }

/**
 * Updates application date timestamp for candidate's personal data.
 *
 * @param int $cr_uid
 *   The candidate's user ID.
 *
 * @return bool
 *   TRUE if date was updated successfully, FALSE otherwise.
 *
 * This method:
 * - Finds the candidate's personal data node
 * - Updates the node's creation timestamp to current time
 * - Ensures accurate application submission time tracking
 */
  public function setApplicationDate($cr_uid) {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $cr_uid)
      ->condition('type', 'personal_data');
    $pd_nid = $query->execute();
    if ($pd_nid) {
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load(reset($pd_nid))
        ->set('created', time())
        ->save();
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
  * Notifies administrator about new application submission.
  *
  * @param \Drupal\user\Entity\User $c_user
  *   The candidate user entity who submitted the application.
  *
  * @return bool
  *   TRUE if email was sent successfully, FALSE otherwise.
  *
  * This method:
  * - Constructs notification email with candidate details
  * - Uses configured admin email from application settings
  * - Leverages Drupal's mail manager service
  */
  public function notifyAdmin($c_user) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'research_application_workflow';
    $key = 'admin_app_notify';
    $to = \Drupal::config('research_application_workflow.applicationsettings')->get('email');
    $from = 'mail@example.com';
    $params = '
            <p>The candidate ' . $c_user->getAccountName() . ' submitted an application.</p>
            <br>
            <br>
            <p>This is an automatically generated email.';
    $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    return $mailManager->mail($module, $key, $to, $language_code, $params,
          $from, $send_now);
  }

  /**
  * Sends confirmation email to candidate after application submission.
  *
  * @param \Drupal\user\Entity\User $c_user
  *   The candidate user entity.
  *
  * @return bool
  *   TRUE if email was sent successfully, FALSE otherwise.
  *
  * This method:
  * - Constructs confirmation email content
  * - Uses Drupal's mail manager service
  * - Provides feedback on submission status
  */
  public function notifyCandidate($c_user) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'research_application_workflow';
    $key = 'candidate_app_notify';
    $to = $c_user->getEmail();

    $from = 'mail@example.com';
    $params = '
            <p>Dear Candidate,</p>
            <br>
            <br>
            <p>We are pleased to inform you that your application has been successfully received. From this point forward, you will have access to the content, but modifications will no longer be possible.</p>
            <br>
            <br>
            <p>We will contact you to inform of the results of the evaluation process.</p>
            <br>
            <br>
            <p>Best regards,</p>
            <p>ARAID</p>
            ';
    $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    return $mailManager->mail($module, $key, $to, $language_code, $params,
          $from, $send_now);
  }

  /**
   * Create the application pdf file.
   *
   * @return bool
   *   TRUE, if the file was created, FALSE otherwise.
   */
  public function createPdf($cr_uid) {
    $print_engine = \Drupal::service('plugin.manager.entity_print.print_engine')
      ->createSelectedInstance('pdf');
    $print_builder = \Drupal::service('entity_print.print_builder');
    $c_user = User::load($cr_uid);
    $filename = 'applications/' . $c_user->GetAccountName() . '/Application.' . $print_engine
      ->getExportType()->getFileExtension();

    $view = \Drupal::entityTypeManager()
      ->getStorage('view')
      ->load('application_pdf')
      ->getExecutable();
    $view->initDisplay();
    $view->setDisplay('print');
    $arg = [];
    $arg[] = $cr_uid;
    $view->setArguments($arg);
    $view->execute();
    $entity = $view->result[0]->_entity;
    $uri = $print_builder
      ->savePrintable([$entity], $print_engine, $scheme = 'private', $filename);
    if ($uri) {
      /** @var \Drupal\file\Entity\File $file */
      $file = File::create([
        'filename' => 'Application.' . $print_engine
          ->getExportType()->getFileExtension(),
        'uri' => $uri,
        'status' => 1,
        'uid' => $cr_uid,
      ]);
      $file->setPermanent();
      $file->save();
      $c_user->set('field_application', ['target_id' => $file->id()]);
      $c_user->save();
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

/**
 * Copies a candidate's research proposal file to the application directory.
 *
 * @param int $cr_uid
 *   The candidate's user ID.
 *
 * @return bool
 *   TRUE if the file was copied successfully, FALSE otherwise.
 *
 * This method:
 * - Locates the candidate's research proposal
 * - Creates a copy in the application directory
 * - Updates file references in the system
 * - Maintains file ownership and permissions
 */
  public function CopyNodeFile($cr_uid) {

    // Load the user.
    $c_user = User::load($cr_uid);

    // Get the user's directory.
    $user_directory = 'private/applications/' . $c_user->getAccountName();

    // Query to get the user's node of type 'research_data'.
    $queryRD = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $cr_uid)
      ->condition('type', 'research_data');
    $rd_nid = $queryRD->execute();

    if (!empty($rd_nid)) {
      $nid = reset($rd_nid);
      $nodeRD = Node::load($nid);

      // Ensure the node has the file field and retrieve the file ID.
      if ($nodeRD->hasField('field_upload_research_proposal') && !$nodeRD->get('field_upload_research_proposal')->isEmpty()) {
        $fid = $nodeRD->field_upload_research_proposal->target_id;
        $file = File::load($fid);

        if ($file) {
          $source_uri = $file->getFileUri();
          $file_name = $file->getFilename();
          $destination_uri = $user_directory;

          // Copy the file.
          if (file_exists($source_uri)) {
            $file_system = \Drupal::service('file_system');
            $file_system->prepareDirectory($user_directory, FileSystemInterface::CREATE_DIRECTORY);
            $copied_uri = $file_system->copy($source_uri, $destination_uri);

            if ($copied_uri) {
              // Save the copied file as a new file entity.
              $copied_file = File::create([
                'filename' => $file_name,
                'uri' => $copied_uri,
                'status' => 1,
                'uid' => $cr_uid,
              ]);
              $copied_file->setPermanent();
              $copied_file->save();

              return TRUE;
            }
          }
        }
      }
    }

    return FALSE;
  }

/**
 * Copies a candidate's CV file to the application directory.
 *
 * @param int $cr_uid
 *   The candidate's user ID.
 *
 * @return bool
 *   TRUE if the file was copied successfully, FALSE otherwise.
 *
 * This method:
 * - Locates the candidate's CV file
 * - Creates a copy in the application directory
 * - Updates file references in the system
 */
  public function copyCVFile($cr_uid) {

    // Load the user.
    $c_user = User::load($cr_uid);

    // Get the user's directory.
    $user_directory = 'private/applications/' . $c_user->getAccountName();

    // Query to get the user's node of type 'research_data'.
    $queryRD = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $cr_uid)
      ->condition('type', 'research_data');
    $rd_nid = $queryRD->execute();

    if (!empty($rd_nid)) {
      $nid = reset($rd_nid);
      $nodeRD = Node::load($nid);

      // Ensure the node has the CV file field and retrieve the file ID.
      if ($nodeRD->hasField('field_upload_your_cv') && !$nodeRD->get('field_upload_your_cv')->isEmpty()) {
        $fid = $nodeRD->field_upload_your_cv->target_id;
        $file = File::load($fid);

        if ($file) {
          $source_uri = $file->getFileUri();
          $file_name = $file->getFilename();
          $destination_uri = $user_directory;

          // Copy the file.
          if (file_exists($source_uri)) {
            $file_system = \Drupal::service('file_system');
            $file_system->prepareDirectory($user_directory, FileSystemInterface::CREATE_DIRECTORY);
            $copied_uri = $file_system->copy($source_uri, $destination_uri);

            if ($copied_uri) {
              // Save the copied file as a new file entity.
              $copied_file = File::create([
                'filename' => $file_name,
                'uri' => $copied_uri,
                'status' => 1,
                'uid' => $cr_uid,
              ]);
              $copied_file->setPermanent();
              $copied_file->save();

              return TRUE;
            }
          }
        }
      }
    }

    return FALSE;
  }

}
