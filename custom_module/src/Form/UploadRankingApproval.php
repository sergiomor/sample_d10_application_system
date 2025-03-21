<?php

namespace Drupal\custom_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class UploadRankingApproval extends FormBase {

    /**
     * {@inheritdoc}
     */
     public function getFormId() {
        return 'upload_ranking_approval_form';
     }

    /**
     * {@inheritdoc}
     * 
     * creates a form for Evaluators and Admins 
     * to upload signed Evaluation nodes
     */
    public function buildForm(array $form, FormStateInterface
    $form_state, $tid = NULL) {

        //owned by the current evaluator
        $e_user = \Drupal::currentUser();
        $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->load($tid);

        $file_owners = [];

        $term = \Drupal\taxonomy\Entity\Term::load($tid);

        // Check if the term has field_evaluators_ranking_pdf
        if ($term->hasField('field_evaluators_ranking_pdf') && !$term->get('field_evaluators_ranking_pdf')->isEmpty()) {
            $ranking_pdf_fid = $term->get('field_evaluators_ranking_pdf')->target_id;
            
            if ($ranking_pdf_fid) {
                $file = \Drupal::entityTypeManager()
                    ->getStorage('file')
                    ->load($ranking_pdf_fid);
                
                // If the file is owned by the current user, add to file_owners
                if ($file && $file->get('uid')->target_id == $e_user->id()) {
                    $file_owners[] = $e_user->id();
                }
            }
        }

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
            $form['tid'] = [
                '#type' => 'hidden',
                '#value' => $tid,
            ];
            $form['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Submit'),
            ];
            
            // Add back button
            $form['back_button'] = [
                '#type' => 'link',
                '#title' => $this->t('Back to Rankings'),
                '#url' => Url::fromRoute('custom_module.applications_evaluator'),
                '#attributes' => [
                'class' => ['button', 'button--primary'],
                ],
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
        //validate if Evaluator can submit the Ranking for Field of Research
        $tid = $form_state->getValue('tid');
        $e_user = \Drupal::currentUser();

        // Load the full user entity
        $user_entity = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($e_user->id());

        // Get the user's research lines (taxonomy terms)
        $tids = [];
        if ($user_entity->hasField('field_research_lines')) {
            $research_lines = $user_entity->field_research_lines->getValue();

            foreach ($research_lines as $line) {
                $tids[] = $line['target_id'];
            }
        }

        if (!in_array($tid, $tids)) {
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
        $tid = $form_state->getValue('tid');
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
           $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->load($tid);
          $term_name = $term ? $term->getName() : '';
          $base_name = preg_replace('/[^a-z0-9]+/', '_', strtolower($term_name));

           $sourceUri = \Drupal::service('file_system')->realpath($file->getFileUri());
           $realpath = 'private://rankings/Ranking-' . $base_name . '-' . $e_user->GetAccountName() . '.pdf';
           $destinationUri = \Drupal::service('file_system')
               ->realpath($realpath);
           //move and overwrite   
           \Drupal::service('file_system')->move($sourceUri, $destinationUri);
            //save file
            $file->setFilename('Ranking-' . $base_name . '-' . $e_user->GetAccountName() . '.pdf');
            $file->setPermanent();
            $file->setFileUri($realpath);
            $file->save();
            // save file in candidate's profile
            // not the file, only the fid
            $term->get('field_evaluators_ranking_pdf')->appendItem($file->id());
            $term->save();
            //set message
            \Drupal::messenger()->addMessage($this->
            t('The Ranking Approval was submitted sucessfully'), 
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
        $key = 'admin_ranking_notify';
        $to = \Drupal::config('custom_module.applicationsettings')->get('email');
        $from = 'servidor@araid.es';
        $params = '
            <p>The evaluator ' . $e_user->getAccountName() . ' submitted a Ranking Approval.</p>
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