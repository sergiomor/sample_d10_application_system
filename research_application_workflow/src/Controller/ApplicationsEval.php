<?php 

namespace Drupal\research_application_workflow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

//creates dashboard for Evaluators
 class ApplicationsEval extends ControllerBase {
    public function DashboardEval() {
        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        if (in_array('evaluador', $roles)) {
            $query = \Drupal::entityQuery('user')
                ->accessCheck(TRUE)
                ->condition('field_evaluators', [$user->id()], 'IN')
                ->condition('roles', ['candidato_enviado'], 'IN');
            $c_users=$query->execute();
           if (count($c_users) > 0) {
            $row = array();
            foreach ($c_users as $c_user) {
                    //get candidates personal data
                    $c_user_loaded = \Drupal::entityTypeManager()
                       ->getStorage('user')
                       ->load($c_user);
                    $nodePD = $this->getNodePerType($node_type = 'personal_data', 
                        $c_user_loaded);
                    $name = $nodePD->label() . ', ' . $nodePD->get('field_first_name')->value;
                    //build link to evalution form
                    $query = \Drupal::entityQuery('node')
                        ->accessCheck(TRUE)
                        ->condition('field_candidate', $c_user)
                        ->condition('uid', $user->id())
                        ->condition('type', 'evaluation_form');
                    $nodeEF=$query->execute();
                    //get files from evaluator
                    $query2 = \Drupal::entityQuery('file')
                        ->accessCheck(TRUE)
                        ->condition('uid', $user->id());
                    $fids=$query2->execute();
                    if ($nodeEF) {
                        //there is an already created EF
                        $nodeEF_loaded = \Drupal::entityTypeManager()
                            ->getStorage('node')
                            ->load(reset($nodeEF));
                        $ev_url = Url::fromUserInput('/node/')->toString() . '/' . reset($nodeEF);
                        $text_ev = t('View');
                        if ($nodeEF_loaded->get('field_status')->value == 'finished') {
                            //the EF is finished
                            $status = t('Finished');
                            $c_fids = $c_user_loaded->get('field_evaluation')->getValue();
                            $signed = '<a href="' . Url::fromUserInput('/evaluation/form/' . 
                             $c_user)->toString() . '">' . t('Submit') . '</a>';
                            if($c_fids) {
                                foreach ($c_fids as $c_fid) {
                                     if (in_array($c_fid['value'], $fids)) {
                                        //EF was submitted
                                        //create view link
                                        
                                        $url = $this->getFileUrl($c_fid['value']);
                                        $signed = '<a href="' . 
                                            $url . '">' . t('View') . '</a>';
                                     }
                                 }   
                            }
                        } else {
                            //the EF is still in production
                            $status = t('In production');
                            $signed = '' . t('Evaluation in production') . '';
                        }
                    } else {
                        $text_ev = t('Create');
                        $ev_url = Url::fromUserInput('/node/add/evaluation_form', ['query' => ['eval' => $c_user]])->toString();
                        $status = ' - ';
                        $signed = '' . t('Not evaluated') . '';
                    }
            
                    //link to evaluation form
                    $link_ev = '<a href="' . $ev_url .'">' . $text_ev . '</a>';
                    //get candidate's research data
                    $nodeRD = $this->getNodePerType($node_type = 'research_data', 
                        $c_user_loaded);
                    $fid = $nodeRD->field_upload_your_cv->target_id;
                    //load the CV file.
                    if($fid) {
                        $url = $this->GetFileUrl($fid);
                        $cv = '<a href="' . $url .'" target="_blank" 
                        rel="noopener noreferrer">' . t('View') . '</a>';
                    } else {
                        $cv = t('CV not available');
                    }
                    //get candidate's Research Proposal
                    $queryRL = \Drupal::entityQuery('node')
                        ->accessCheck(TRUE)
                        ->condition('uid', $c_user)
                        ->condition('type', 'research_line');
                    $rl_nid=$queryRL->execute();
                    if (count($rl_nid) > 0) {
                        $nid = reset($rl_nid);
                        $nodeRL = Node::load($nid);
                        $fid = $nodeRL->field_upload_research_proposal->target_id;
                        //load the file.
                        $file = \Drupal\file\Entity\File::load($fid);
                        if($file) {
                            //get the URL.
                            $url = \Drupal::service('file_url_generator')
                            ->generateAbsoluteString($file->getFileUri());
                            $rp = '<a href="' . $url .'" target="_blank" 
                            rel="noopener noreferrer" class="file-download-link">' . t('View') . '</a>';
                        } else {
                            $rp = t('-');
                        }
                    } else {
                        $rp = t('-');
                    }

                    //get candidate's letters
                    $nodeL = $this->getNodePerType($node_type = 'letters', 
                        $c_user_loaded);
                    
                    // Initialize letter variables with default values
                    $rl1 = t('Letter 1 not available');
                    $rl2 = t('Letter 2 not available');
                    $additional_letters = [];
                    
                    // Only process letters if the node exists
                    if ($nodeL) {
                        $fid1 = $nodeL->field_letter_1->target_id;
                        $fid2 = $nodeL->field_letter_2->target_id;
                        //load the file 1.
                        if($fid1) {
                            $urllet1 = $this->GetFileUrl($fid1);
                            $rl1 = '<a href="' . $urllet1 .'" target="_blank" 
                            rel="noopener noreferrer">' . t('Letter 1') . '</a>';
                        }
                        
                        if($fid2) {
                            $urllet2 = $this->GetFileUrl($fid2);
                            $rl2 = '<a href="' . $urllet2 .'" target="_blank" 
                            rel="noopener noreferrer">' . t('Letter 2') . '</a>';
                        }
                        
                        // Handle additional letters if available
                        $letter_count = 3; // Start from letter 3
                        
                        if ($nodeL->hasField('field_additional_letters') && !$nodeL->get('field_additional_letters')->isEmpty()) {
                            // Handle multi-value file field
                            foreach ($nodeL->get('field_additional_letters') as $item) {
                                $fid = $item->target_id;
                                if ($fid) {
                                    $url = $this->GetFileUrl($fid);
                                    $additional_letters[] = '<li><a href="' . $url .'" target="_blank" 
                                    rel="noopener noreferrer">' . t('Letter @num', ['@num' => $letter_count]) . '</a></li>';
                                } else {
                                    $additional_letters[] = '<li>' . t('Letter @num not available', ['@num' => $letter_count]) . '</li>';
                                }
                                $letter_count++;
                            }
                        }
                    }
                    //get candidate's expression of interest
                    $ei_fid = $c_user_loaded->field_signed_ei_fid->target_id;
                    if ($ei_fid) {
                        $ei_url = $this->GetFileUrl($ei_fid);
                        $ei = '<a href="' . $ei_url .'" target="_blank" 
                        rel="noopener noreferrer">' . t('View') . '</a>';
                    } else {
                        $ei = '';
                    }  
                    $row[] = '<tr>
                                <td>' . $name . '</td>
                                <td>' . $link_ev . '</td>
                                <td>' . $status . '</td>
                                <td>' . $cv . '</td>
                                <td>' . $rp . '</td>
                                <td>
                                    <ul>
                                      <li>' . $rl1 . '</li>
                                      <li>' . $rl2 . '</li>
                                      ' . implode('', $additional_letters) . '
                                    </ul>
                                </td>
                                <td>' . $ei . '</td>
                                <td>' . $signed . '</td>
                             </tr>';

                    // Get rankings
                    $rankings_table = $this->getRankings($user);      
               }
               return [
                '#markup' => '
                           <div class="evaluations-section">
                            <table>
                                <tr>
                                    <td>' . t('Applicant') . '</td>
                                    <td>' . t('Evaluation') . '</td>
                                    <td>' . t('Status') . '</td>
                                    <td>' . t('CV') . '</td>
                                    <td>' . t('Research Proposal') . '</td>
                                    <td>' . t('Recommendation letters') . '</td>
                                    <td>' . t('Expression of Interest') . '</td>
                                    <td>' . t('Signed Evaluation') . '</td>
                                </tr>
                    
                            ' . implode($row) . ' 
                            </table>
                           </div>
                           ' . $rankings_table,
               ];

           } else {
                return [
                    '#markup' => '<p>There are not pending evaluations</p>',
                ];
           }
        }
    }

    /**
     * {@inheritdoc}
     * @return entity
     */
    //get nodes per node type created by the current user
    public function getNodePerType($node_type, $c_user_loaded) {
        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('uid', $c_user_loaded->id())
            ->condition('type', $node_type);
        $nid=$query->execute();
        $node = \Drupal::entityTypeManager()
             ->getStorage('node')
             ->load(reset($nid));
        return $node;
    }

    /**
     * {@inheritdoc}
     * @return string
     */
    //get file url
    public function getFileUrl($fid) {
        $file = \Drupal\file\Entity\File::load($fid);
        
        // Check if file was successfully loaded
        if (!$file) {
            return '';
        }
        
        //get the URL.
        $url = \Drupal::service('file_url_generator')
        ->generateAbsoluteString($file->getFileUri());
        return $url;
    }

    /**
     * {@inheritdoc}
     */
    //get rankings
    public function GetRankings($user) {
        $rankings_table = '';

        // Load the full user entity
        $user_entity = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($user->id());

        // Get the user's research lines (taxonomy terms)
        if ($user_entity->hasField('field_research_lines')) {
            $research_lines = $user_entity->field_research_lines->getValue();
            
            // Check if there are research lines and if the taxonomy term type has the field_ranking_pdf field
            $has_ranking_field = false;
            
            // Check if at least one research line taxonomy term has the field_ranking_pdf
            if (!empty($research_lines)) {
                // Load one term to check if the field exists in this taxonomy vocabulary
                $sample_tid = reset($research_lines)['target_id'];
                if ($sample_tid) {
                    $sample_term = \Drupal\taxonomy\Entity\Term::load($sample_tid);
                    if ($sample_term && $sample_term->hasField('field_ranking_pdf')) {
                        // Check if the field has a file and if the file exists
                        $file_exists = false;
                        if (!$sample_term->get('field_ranking_pdf')->isEmpty()) {
                            $file_reference = $sample_term->get('field_ranking_pdf')->first();
                            if ($file_reference && $file_reference->entity) {
                                $file_exists = true;
                            }
                        }
                        
                        // Only set has_ranking_field to true if the field exists and has a valid file
                        if ($file_exists) {
                            $has_ranking_field = true;
                        }
                    }
                }
            }
            
            // Check if all candidates have evaluation files submitted by this evaluator
            $all_evaluations_submitted = true;
            
            // Get all candidates assigned to this evaluator
            $query = \Drupal::entityQuery('user')
                ->accessCheck(TRUE)
                ->condition('field_evaluators', [$user->id()], 'IN')
                ->condition('roles', ['candidato_enviado'], 'IN');
            $c_users = $query->execute();
            
            // Get all files owned by this evaluator
            $query = \Drupal::entityQuery('file')
                ->accessCheck(TRUE)
                ->condition('uid', $user->id());
            $evaluator_files = $query->execute();
            
            if (!empty($c_users)) {
                foreach ($c_users as $c_user) {
                    $c_user_loaded = \Drupal::entityTypeManager()
                        ->getStorage('user')
                        ->load($c_user);
                    
                    // Check if this candidate has a submitted evaluation file from this evaluator
                    $has_evaluation_file = false;
                    
                    if ($c_user_loaded && $c_user_loaded->hasField('field_evaluation')) {
                        $c_fids = $c_user_loaded->get('field_evaluation')->getValue();
                        
                        if (!empty($c_fids)) {
                            foreach ($c_fids as $c_fid) {
                                if (in_array($c_fid['value'], $evaluator_files)) {
                                    // Verify the file exists and is accessible
                                    $file_url = $this->getFileUrl($c_fid['value']);
                                    if (!empty($file_url)) {
                                        $has_evaluation_file = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$has_evaluation_file) {
                        $all_evaluations_submitted = false;
                        break;
                    }
                }
            }
            
            // Only show rankings if both conditions are met:
            // 1. Has ranking field with valid file
            // 2. All candidates have evaluation files submitted by this evaluator
            if (!empty($research_lines) && $has_ranking_field && $all_evaluations_submitted) {
                $rankings_table = '<div class="rankings-section pt-4"><h3>' . t('Available Rankings') . '</h3>';
                $rankings_table .= '<table class="rankings-table"><thead><tr>';
                $rankings_table .= '<th>' . t('Field of Research') . '</th>';
                $rankings_table .= '<th>' . t('Ranking PDF') . '</th>';
                $rankings_table .= '<th>' . t('Ranking Approval') . '</th>';
                $rankings_table .= '</tr></thead><tbody>';
                
                $has_rankings = false;
                
                foreach ($research_lines as $line) {
                    $tid = $line['target_id'];
                    
                    if ($tid) {
                        // Load the taxonomy term
                        $term = \Drupal\taxonomy\Entity\Term::load($tid);
                        
                        if ($term) {
                            $term_name = $term->getName();
                            
                            // Check if the term has a ranking PDF
                            if ($term->hasField('field_ranking_pdf') && !$term->get('field_ranking_pdf')->isEmpty()) {
                                $ranking_pdf_fid = $term->get('field_ranking_pdf')->target_id;
                                
                                if ($ranking_pdf_fid) {
                                    $has_rankings = true;
                                    $pdf_url = $this->getFileUrl($ranking_pdf_fid);
                                    
                                    // Check if the evaluator has already uploaded an approval file
                                    $has_uploaded_approval = false;
                                    if ($term->hasField('field_evaluators_ranking_pdf')) {
                                        $file_references = $term->get('field_evaluators_ranking_pdf')->referencedEntities();
                                        foreach ($file_references as $file) {
                                            $loaded_file = \Drupal\file\Entity\File::load($file->id());
                                            if ($loaded_file && $loaded_file->getOwnerId() == $user->id()) {
                                                $has_uploaded_approval = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    $approval_url = Url::fromRoute('research_application_workflow.upload_ranking_approval.form', ['tid' => $tid])->toString();                                    
                                    $rankings_table .= '<tr>';
                                    $rankings_table .= '<td>' . $term_name . '</td>';
                                    $rankings_table .= '<td><a href="' . $pdf_url . '" target="_blank">' . t('View Ranking') . '</a></td>';
                                    
                                    if ($has_uploaded_approval) {
                                        $rankings_table .= '<td>' . t('Already submitted') . '</td>';
                                    } else {
                                        $rankings_table .= '<td><a href="' . $approval_url . '">' . t('Submit') . '</a></td>';
                                    }
                                    
                                    $rankings_table .= '</tr>';
                                }
                            }
                        }
                    }
                }
                
                $rankings_table .= '</tbody></table>';
                
                if (!$has_rankings) {
                    $rankings_table .= '<p>' . t('No rankings available for your research lines.') . '</p>';
                }
                
                $rankings_table .= '</div>';
            }
        }
        
        return $rankings_table;
    }
}