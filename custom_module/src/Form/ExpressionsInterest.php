<?php

namespace Drupal\custom_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

class ExpressionsInterest extends FormBase {

    /**
     * {@inheritdoc}
     */
     public function getFormId() {
        return 'expressions_interest_form';
     }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface
    $form_state) {

        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        $uid = $user->id();
        $user_loaded = User::load($uid);

        //add logic for candidato roles
        if (in_array('candidato', $roles)) {
            //if user already submitted the email address
            if ($user_loaded->get('field_researcher')->target_id) { 
                //if IR compeleted the EI and the status is finished
                $queryI = \Drupal::entityQuery('node')
                    ->accessCheck(TRUE)
                    ->condition('field_candidate', $uid)
                    ->condition('type', 'interest')
                    ->condition('field_status', 'finished');
                $i_nid=$queryI->execute();
                if (count($i_nid) > 0) {
                     $interest = Node::load(reset($i_nid));
                     $title = $interest->getTitle();
                     $nid = $interest->id();
                     $form['no-expressions'] = [
                        '#markup' => '<ul><li><a href="' . Url::fromUserInput('/node/' . $nid .'')->toString() . '">' 
                        . $title . '</a></li></ul>',
                    ];
                } else {
                    //the IR did not completed the EI yet
                    $form['no-expressions'] = [
                    '#markup' => t('<p>There are not expressions of interest.</p>'),
                    ];
                }
                $form['introduction'] = [
                    '#markup' => t('<p>
                        In order for the candidate to submit the application, the Research Manager must have previously uploaded the Expression of Interest (EI) signed electronically by the Research Manager, the Administrative Manager of the Host Institution/Center and the Legal Representative of the Center. Alternatively, the EI may be uploaded with only the Research Manager signature. However, in this case, the EI signed by all required parts must be sent to convocatorias@araid.es before the deadline for closing the call.
                    </p>'),
                ];
             } else {
                //if not display email form
                $query = \Drupal::entityQuery('node')
                    ->accessCheck(TRUE)
                    ->condition('uid', $user->id())
                    ->condition('type', ['personal_data','research_data'], 'IN');
                $nids=$query->execute();
                //check if candidato completed both 
                //personal data and research line content
                //provide an email addres field
                if (count($nids) > 1) {
                    //Return array of Form API elements
                    $form['introduction'] = [
                        '#markup' => t('
                            <p>Enter the email address of the Researcher/Responsible person 
                            of your Host Institution of choice. The system will immediately send 
                            to her/him an email with the required username and password to access 
                            the Expression of Interest form at the application. Once completed and 
                            submitted back by the Host Institution it will appear at the Expression
                            Of Interest page, until then this page will be blank.</p>'),
                    ];
                    $form['email'] = [
                        '#type' => 'email',
                        '#title' => $this->t('Researcher&#39;s mail:'),
                        '#required' => 'TRUE',
                    ];
                    $form['submit'] = [
                        '#type' => 'submit',
                        '#value' => $this->t('Save'),
                    ];
                } else {
                    //if not set a warning
                    $form['warning'] = [
                        '#markup' => t('<p>Before requesting for expressions 
                        of interest must complete the Personal Data and 
                        the Research Data tabs.</p>'),
                    ];
                }            
             }
        } 
        if (in_array('candidato_enviado', $roles)) {
            $queryI = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('field_candidate', $uid)
                ->condition('type', 'interest')
                ->condition('field_status', 'finished');
            $i_nid=$queryI->execute();
            if (count($i_nid) > 0) {
                $interest = Node::load(reset($i_nid));
                $title = $interest->getTitle();
                $nid = $interest->id();
                $form['no-expressions'] = [
                   '#markup' => '<ul><li><a href="' . Url::fromUserInput('/node/' . $nid .'')->toString() . '">' 
                   . $title . '</a></li></ul>',
               ];
           }
            $form['introduction'] = [
                '#markup' => t(' 
                      In order for the candidate to submit the application, the Research Manager must have previously uploaded the Expression of Interest (EI) signed electronically by the Research Manager, the Administrative Manager of the Host Institution/Center and the Legal Representative of the Center. Alternatively, the EI may be uploaded with only the Research Manager signature. However, in this case, the EI signed by all required parts must be sent to convocatorias@araid.es before the deadline for closing the call.
                '),
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
        //check if email address belongs to a candidato
        //set error if yes
        $ir_email = $form_state->getValue('email');
        $query = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition('mail', $ir_email)
            ->condition('roles', ['candidato','candidato_enviado'], 'IN');
        $users=$query->execute();
        if (count($users) > 0) {
            $form_state->setErrorByName('email', t('The Researcher&#39;s email address is 
            discharged into the system as a candidate address, please enter 
            another or contact your administrator.'));
        } 
        //Set validation error if the IR reached the maximum 
        // number of allowed candidates(3):
        $query2 = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition('mail', $ir_email)
            ->condition('roles', 'investigador_responsable', 'IN');
        $users2=$query2->execute();
        if (count($users2) > 0) {
            $ir_uid = reset($users2);
            $ir_user = User::load($ir_uid);
            $candidates = $ir_user->field_candidate->getValue();
            if (count($candidates) > 2) {
                $form_state->setErrorByName('email', t('This Researcher 
                reached the maximum number of allowed candidates'));
            }
        }
    }

    public function submitForm(array &$form, FormStateInterface
    $form_state) {
        //get the researcher's email address
        $ir_email = $form_state->getValue('email');
        //check if there's a user with email address above
        $query = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition('mail', $ir_email);
        $ir_users=$query->execute();
        $ir_uid = '';
        //@TODO in production $copia = 'araideu@araid.es';
        if (count($ir_users) > 0) {
            //a user exists with the researcher's email address.
            //get investigador_responsable uid 
            $ir_uid = reset($ir_users);
            //add role investigador_responsable
            $ir_user = User::load($ir_uid);
            $roles = $ir_user->getRoles();
            if ((!in_array('investigador_responsable', $roles))) {
                $ir_user->addRole('investigador_responsable');
                $ir_user->save();
            }
            //send email
            $mailManager = \Drupal::service('plugin.manager.mail');
            $form_values = $form_state->getValues();
            $module = 'custom_module';
            $key = 'ir_existing';
            $to = $form_values['email'];
            $from = 'servidor@araid.es';
            $params = '';
            $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
            $send_now = TRUE;
            $result = $mailManager->mail($module, $key, $to, $language_code, $params, 
                $from, $send_now);
            // $result = $mailManager->mail($module, $key, $copia, $language_code, $params, 
            //     $from, $send_now);
            if ($result['result'] == TRUE) {
                 $this->messenger()->addMessage($this->t('The Researcher has been 
                 contacted.'));
            }
            else {
                 $this->messenger()->addMessage($this->t('There was a problem 
                 sending your message and it was not sent.'), 'error');
            }
        } else {
            //create a user with the role investigador_responsable
            //and with the researcher's email address.
            $language = \Drupal::languageManager()->getCurrentLanguage()
                ->getId();
            $user_name = explode("@", $ir_email);
            $user = \Drupal\user\Entity\User::create();
            $user_pass = \Drupal::service('password_generator')->generate();
            $user->setPassword($user_pass);
            $user->enforceIsNew();
            $user->setEmail($ir_email);
            $user->setUsername($user_name);
            $user->set('langcode', $language);
            $user->set('preferred_langcode', $language);
            $user->addRole('investigador_responsable');
            $user->activate();
            //save user account.
            $user->save();
            //get investigador_responsable id 
            //and username
            $ir_uid = $user->id();
            $username = $user->getDisplayName();
            //send email
            $mailManager = \Drupal::service('plugin.manager.mail');
            $form_values = $form_state->getValues();
            $module = 'custom_module';
            $key = 'ir_non_existing';
            $to = $form_values['email'];
            $from = 'servidor@araid.es';
            $params = '
                <p>' . $username . '</p>
                <p>A new candidate has selected you to complete a expression of interest.</p>
                <p>Your can fill it by clicking this link or copying and pasting it in your browser:</p>
                <p>http://convocatorias.araid.es/</p>
                <p>using the following username and password:</p>
                <br>
                <p>username: ' . $username . '<br>
                password: ' . $user_pass . '</p>
                <br>
                <br>
                <p>This is an automatically generated email. Please, do not reply. If you have any question you can contact us at convocatorias@araid.es.</p>';
            $language_code = \Drupal::LanguageManager()->getDefaultLanguage()->getId();
            $send_now = TRUE;
            $result = $mailManager->mail($module, $key, $to, $language_code, $params, 
                $from, $send_now);
            // $result = $mailManager->mail($module, $key, $copia, $language_code, $params, 
            //     $from, $send_now);
            if ($result['result'] == TRUE) {
                    $this->messenger()->addMessage($this->t('The Researcher has been 
                    contacted.'));
            }
            else {
                    $this->messenger()->addMessage($this->t('There was a problem 
                    sending your message and it was not sent.'), 'error');
            }
         }
         
        //add IR user to the candidato's 
        //account 'field_researcher'
        $current_user = \Drupal::currentUser();
        $cr_uid = $current_user->id();
        $current_user = User::load($cr_uid);
        $current_user->set('field_researcher',['target_id' => $ir_uid]);
        $current_user->save();
        //add candidato user to the IR's 
        //account 'field_candidate'
        $ir_user = User::load($ir_uid);
        //if candidato already exists in field_candidate
        $candidates = $ir_user->field_candidate->getValue();
        //add if there are not candidates 
        if (count($candidates) == 0) {
            $ir_user->set('field_candidate',['target_id' => $cr_uid]);
            $ir_user->save();
        } else {
            //if there are candidates add only if it was not added yet
            foreach ($candidates as $candidate) {
                $array_can = array();
                $array_can[] = $candidate['target_id'];
            }
            if (!in_array($cr_uid, $array_can)) {
                $ir_user->get('field_candidate')->appendItem($cr_uid);
                $ir_user->save();
            }
        }
        //set message
        \Drupal::messenger()->addMessage($this->
        t('Email address submitted successfully'), 'status', TRUE);
    }
}
