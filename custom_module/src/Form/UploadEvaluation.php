<?php

namespace Drupal\custom_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
     * creates a form for Evaluators and Admins 
     * to upload signed Evaluation nodes
     */
    public function buildForm(array $form, FormStateInterface
    $form_state, $uid = NULL) {
        //check if there's a EF signed file for the candidate
        //owned by the current evaluator
        $c_user = \Drupal::entityTypeManager()
             ->getStorage('user')
             ->load($uid);
        $c_fids = $c_user->get('field_evaluation')->getValue();
        $file_owners = array();
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
        } else {
            // create form to upload file
            $form['attachments'] = [
                '#type' => 'managed_file',
                '#multiple' => false,
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
     * 
     */
    public function validateForm(array &$form, FormStateInterface
    $form_state, $uid = NULL) {
        //validate if Evaluator can submit the EF for the candidate
        $e_user = \Drupal::currentUser();
        $query = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition('field_evaluators', [$e_user->id()], 'IN');
        $uids=$query->execute();
        if (!in_array($form_state->getValue('uid'), $uids)) {
            $this->messenger()->addMessage($this->
            t('You cannot submit this form. 
            Please contact the administrator.'), 'error');  
        } 

        //validate if pdf is adobe signed
        $form_file = $form_state->getValue('attachments', 0);
        $file = \Drupal::entityTypeManager()
            ->getStorage('file')
            ->load($form_file[0]);
         if($file) {
            $signed = $this->isPdfSigned($file->getFileUri());
            if ($signed == false) {
                $this->messenger()->addMessage($this->
                t('Your pdf is not digitally signed.'), 'error');
            }
        } 
    }

    public function submitForm(array &$form, FormStateInterface
      $form_state) {
         $form_file = $form_state->getValue('attachments', 0);
         $file = \Drupal::entityTypeManager()
            ->getStorage('file')
            ->load($form_file[0]);
        $signed = false;
        if ($file) {
          $signed = $this->isPdfSigned($file->getFileUri());
          $signed = true;
        }
        if (isset($form_file[0]) && !empty($form_file[0]) 
            && $signed !== false) {
        //create file
           $e_user = \Drupal::currentUser();
           $c_user = \Drupal::entityTypeManager()
                ->getStorage('user')
                ->load($form_state->getValue('uid'));
           $sourceUri = \Drupal::service('file_system')->realpath($file->getFileUri());
           $realpath = 'private://applications/' . $c_user->GetAccountName() . 
                 '/Evaluation-' . $e_user->GetAccountName() . '.pdf';
           $destinationUri = \Drupal::service('file_system')
               ->realpath($realpath);
           //move and overwrite   
           \Drupal::service('file_system')->move($sourceUri, $destinationUri);
            //save file
            $file->setFilename('Evaluation-' . $e_user->GetAccountName() . '.pdf');
            $file->setPermanent();
            $file->setFileUri($realpath);
            $file->save();
            // save file in candidate's profile
            // not the file, only the fid
            $c_user->get('field_evaluation')->appendItem($file->id());
            $c_user->save();
            //set message
            \Drupal::messenger()->addMessage($this->
            t('The signed Evaluation was submitted sucessfully'), 
            'status', TRUE);
            //redirect to dahsboard
             $current_request = \Drupal::service('request_stack')
            ->getCurrentRequest();
            $current_request->query->set(
                'destination',
                \Drupal\Core\Url::fromRoute('custom_module.applications_evaluator')
                ->toString()
                ); 
            //notify admin
            $this->NotifyAdmin($e_user);
        }
    }

     /**
     * sends email
     */
    public function NotifyAdmin($e_user) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'custom_module';
        $key = 'admin_eval_notify';
        $to = \Drupal::config('custom_module.applicationsettings')->get('email');
        $from = 'servidor@araid.es';
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
     * Checks if a given PDF file contains a digital signature by searching for specific signature keywords.
     * 
     * @param string $pdfPath Path to the PDF file.
     * @return bool True if the PDF contains a signature, false otherwise.
     */
    function isPdfSigned($pdfPath) {
        // Check if the file exists
        if (!file_exists($pdfPath)) {
            return false;
        }
        // Open the PDF file in binary mode
         $fileContent = file_get_contents($pdfPath);
         if ($fileContent === false) {
            return false;
        }
        // Keywords that commonly indicate digital signatures in PDFs
        $signatureKeywords = ['Sig'];
        // Search the file content for any of the signature-related keywords
         foreach ($signatureKeywords as $keyword) {
            if (strpos($fileContent, $keyword) !== false) {
                // Keyword found, the PDF likely contains a digital signature
                return true;
            }
        } 
        
        // No signature-related keywords found
        return false;
    }
}