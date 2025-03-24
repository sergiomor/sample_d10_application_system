<?php 

namespace Drupal\research_application_workflow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

//creates dashboard for IR users

 class ActiveExpressionsInterest extends ControllerBase {
    
     public function DashboardIr() {
        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        if (in_array('investigador_responsable', $roles)) {
            $uid = $user->id();
            $user_loaded = User::load($uid);
            $candidates = $user_loaded->field_candidate->getValue();
            $markup = array();
            foreach ($candidates as $candidate) {
                $c_user = User::load($candidate['target_id']);
                //show up if candidate, not enviado
                $c_roles = $c_user->getRoles();
                if (in_array('candidato', $c_roles)) {
                    //get personal data from candidate
                    $queryPD = \Drupal::entityQuery('node')
                        ->accessCheck(TRUE)
                        ->condition('uid', $candidate['target_id'])
                        ->condition('type', 'personal_data');
                    $pd_nid=$queryPD->execute();
                    if (count($pd_nid) > 0) {
                        $nid = reset($pd_nid);
                        $nodePD = Node::load($nid);
                        //get link to EI
                        $queryI = \Drupal::entityQuery('node')
                            ->accessCheck(TRUE)
                            ->condition('field_candidate', $candidate['target_id'])
                            ->condition('type', 'interest');
                        $i_nid=$queryI->execute();
                        //check the EI was created and Status is finished
                        $ei = t('Expression of Interest');
                        if (count($i_nid) > 0) {
                        
                              $link =  '<a href="' . Url::fromUserInput('/node/' . reset($i_nid) . '')->toString() . '">' . $ei . '</a>';
                              //check if EI was signed
                              $c_user = User::load($candidate['target_id']);
                              $c_fid = $c_user->get('field_signed_ei_fid')->target_id;
                              $i_node = Node::load(reset($i_nid));
                              
                              // Check if the interest has field_status with value 'production' or doesn't exist
                              $show_reject_link = false;
                              if ($i_node && $i_node->hasField('field_status')) {
                                $status_value = $i_node->get('field_status')->value;
                                $show_reject_link = ($status_value == 'production');
                              }
                              
                              if ($show_reject_link) {
                                // Add reject link
                                $reject_url = Url::fromRoute('research_application_workflow.reject_candidate', ['uid' => $candidate['target_id']])->toString();
                                $link_upload = '<a href="' . $reject_url . '" class="reject-link">' . 
                                  t('Reject Candidate') . '</a>';
                              } else if (($c_fid) || ($i_node->get('field_status')->value !== 'finished')) {
                                $link_upload = '';
                              } else {
                                //provide link to upload the signed EI
                                $link_upload = '<a href="' . Url::fromUserInput('/researcher/expressions-of-interest/form/'
                                . $candidate['target_id'] . '')->toString() . '" class="submit-form-link">' . 
                                t('Submit the signed Expression of Interest')
                                . '</a>';
                              }
                        } else {
                           // No interest exists, show reject link
                           $link =  '<a href="' . Url::fromUserInput('/node/add/interest?key=' 
                           . $candidate['target_id'] . '')->toString() . '">' . $ei . '</a>';
                           
                           $reject_url = Url::fromRoute('research_application_workflow.reject_candidate', ['uid' => $candidate['target_id']])->toString();
                           $link_upload = '<a href="' . $reject_url . '" class="reject-link">' . 
                             t('Reject Candidate') . '</a>';
                        }
                        //get candidate's CV 
                        $queryRD = \Drupal::entityQuery('node')
                            ->accessCheck(TRUE)
                            ->condition('uid', $candidate['target_id'])
                            ->condition('type', 'research_data');
                        $rd_nid=$queryRD->execute();
                        if (count($rd_nid) > 0) {
                            $nid = reset($rd_nid);
                            $nodeRD = Node::load($nid);
                            $fid = $nodeRD->field_upload_your_cv->target_id;
                            //load the file.
                            $file = \Drupal\file\Entity\File::load($fid);
                            if($file) {
                                //get the URL.
                                $url = \Drupal::service('file_url_generator')
                                ->generateAbsoluteString($file->getFileUri());
                                $cv = '<a href="' . $url .'" target="_blank" 
                                rel="noopener noreferrer" class="file-download-link">' . t('CV') . '</a>';
                            } else {
                                $cv = t('CV not available yet');
                            }
                        } else {
                            $cv = t('CV not available yet');
                        }

                        //get candidate's Research Proposal
                        $queryRD = \Drupal::entityQuery('node')
                            ->accessCheck(TRUE)
                            ->condition('uid', $candidate['target_id'])
                            ->condition('type', 'research_data');
                        $rd_nid=$queryRD->execute();
                        if (count($rd_nid) > 0) {
                            $nid = reset($rd_nid);
                            $nodeRD = Node::load($nid);
                            $fid = $nodeRD->field_upload_research_proposal->target_id;
                            //load the file.
                            $file = \Drupal\file\Entity\File::load($fid);
                            if($file) {
                                //get the URL.
                                $url = \Drupal::service('file_url_generator')
                                ->generateAbsoluteString($file->getFileUri());
                                $rp = '<a href="' . $url .'" target="_blank" 
                                rel="noopener noreferrer" class="file-download-link">' . t('Research proposal') . '</a>';
                            } else {
                                $rp = t('Research proposal not available yet');
                            }
                        } else {
                            $rp = t('Research proposal not available yet');
                        }

                        //provide action link
                        $markup[] = '<tr>
                                        <td>' . $nodePD->getTitle() . ', ' . $nodePD->field_first_name->value . '</td>
                                        <td>' . $link . '</td>
                                        <td>' . $rp . '</td>
                                        <td>' . $cv . '</td>
                                        <td>' . $link_upload . '</td>
                                    </tr>';              
                    }
                };
            }
            //if there EI show list
            if (count($markup) > 0 ) {
                return [
                    '#markup' => '<table>' . implode($markup) . '</table>',
                ]; 
            } else {
                //if not show message
                return [
                    '#markup' => '<p>There are not pending expressions of interest</p>',
                ];
            }
        }
     }
 }