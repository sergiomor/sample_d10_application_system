<?php

namespace Drupal\custom_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

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
    public function buildForm(array $form, FormStateInterface
    $form_state, $uid = NULL) {
        //check if fid exists in candidato's user profile
        //meaning that the IR submitted the signed EI
        $user = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->load($uid);
        $roles = $user->getRoles();
        //add and candidato
        if (in_array('candidato', $roles)) {
            if ($user->get('field_signed_ei_fid')->target_id)  {
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
            } else {
                $form['markup-1'] = [
                    '#markup' => t('<p><strong>The host institution have not 
                        finished the expression of interest yet</strong></p>'),
                    ];
            }
        } else {
            $form['markup-1'] = [
                '#markup' => t('<p><strong>Your application was sent.</strong></p>'),
                ];
        }
        return $form;
    }
 
    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface
    $form_state) {
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function submitForm(array &$form, FormStateInterface
    $form_state) {

        //change role to candidato enviado
        $current_user = \Drupal::currentUser();
        $cr_uid = $current_user->id();
        $c_user = User::load($cr_uid);
        $c_user->addRole('candidato_enviado');
        $c_user->removeRole('candidato');
        $c_user->save();
        //create application pdf
        $this->CreatePdf($cr_uid);
        //copy Research Proposal
        $this->CopyNodeFile($cr_uid);
        //copy CV
        $this->CopyCVFile($cr_uid);
        //change PD node creation day
        $this->SetApplicationDate($cr_uid);
        //set message
        $this->messenger()->addMessage($this->t('Application submitted sucessfully.'));
        //notify admin and candidate
        $this->NotifyAdmin($c_user);
        $this->NotifyCandidate($c_user);
    }

    /**
     * {@inheritdoc}
     * @return boolean
     */
    public function SetApplicationDate($cr_uid) {
        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('uid', $cr_uid)
            ->condition('type', 'personal_data');
        $pd_nid=$query->execute();
        if ($pd_nid) {
            $node = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load(reset($pd_nid))
            ->set('created', time())
            ->save();
            return TRUE;
        } else {
            return FALSE;
        }
    }
    /**
     * sends email
     */
    public function NotifyAdmin($c_user) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'custom_module';
        $key = 'admin_app_notify';
        $to = \Drupal::config('custom_module.applicationsettings')->get('email');
        $from = 'servidor@araid.es';
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

    public function NotifyCandidate($c_user) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'custom_module';
        $key = 'candidate_app_notify';
        $to = $c_user->getEmail();;
        $from = 'servidor@araid.es';
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
     * create the application pdf file
     *   @return bool
     *   TRUE, if the file was created, FALSE otherwise.
     */
    public function CreatePdf($cr_uid) {
        $print_engine = \Drupal::service('plugin.manager.entity_print.print_engine')
            ->createSelectedInstance('pdf');
        $print_builder = \Drupal::service('entity_print.print_builder');
        $c_user = User::load($cr_uid);
        $filename =  'applications/' . $c_user->GetAccountName() . '/Application.' . $print_engine
        ->getExportType()->getFileExtension();

        $view = \Drupal::entityTypeManager()
            ->getStorage('view')
            ->load('application_pdf')
            ->getExecutable();
        $view->initDisplay();
        $view->setDisplay('print');
        $arg = array();
        $arg[] = $cr_uid;
        $view->setArguments($arg);
        $view->execute();
        $entity = $view->result[0]->_entity;
        $uri = $print_builder
            ->savePrintable([$entity], $print_engine, $scheme='private', $filename);
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
        } else {
            return FALSE; 
        }
    }

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
                $file = \Drupal\file\Entity\File::load($fid);
    
                if ($file) {
                    $source_uri = $file->getFileUri();
                    $file_name = $file->getFilename();
                    $destination_uri = $user_directory;
    
                    // Copy the file.
                    if (file_exists($source_uri)) {
                        $file_system = \Drupal::service('file_system');
                        $file_system->prepareDirectory($user_directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
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

    public function CopyCVFile($cr_uid) {

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
                $file = \Drupal\file\Entity\File::load($fid);

                if ($file) {
                    $source_uri = $file->getFileUri();
                    $file_name = $file->getFilename();
                    $destination_uri = $user_directory;

                    // Copy the file.
                    if (file_exists($source_uri)) {
                        $file_system = \Drupal::service('file_system');
                        $file_system->prepareDirectory($user_directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
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