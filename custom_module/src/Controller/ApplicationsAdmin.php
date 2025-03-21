<?php 

namespace Drupal\custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

//creates dashboard for Admin
 class ApplicationsAdmin extends ControllerBase {
    public function DashboardAdmin() {
        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        if (in_array('administrator', $roles)) {
            // Get the selected field of research filter from the URL query parameter
            $field_of_research_filter = \Drupal::request()->query->get('field_of_research');
            
            // Build the filter form
            $filter_form = \Drupal::formBuilder()->getForm('Drupal\custom_module\Form\FieldOfResearchFilterForm', $field_of_research_filter);
            
            // Get the filtered results
            $results = $this->getFilteredResults($field_of_research_filter);
            
            // Return the complete page with form and results
            return [
                'filter_form' => $filter_form,
                'results' => [
                    '#markup' => $results,
                ],
            ];
        }
    }
    
    /**
     * Get filtered results based on field of research.
     *
     * @param string $field_of_research_filter
     *   The field of research ID to filter by.
     *
     * @return string
     *   The HTML markup for the filtered results.
     */
    public function getFilteredResults($field_of_research_filter = '') {
        $query = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition('roles', ['candidato_enviado'], 'IN');
        $c_users = $query->execute();
        
        if (count($c_users) == 0) {
            return '<div class="messages messages--warning">' . t('There are no applications.') . '</div>';
        }
        
        $row = [];
        foreach ($c_users as $c_user) {
            //get candidates personal data
            $c_user_loaded = \Drupal::entityTypeManager()
               ->getStorage('user')
               ->load($c_user);
            
            //get candidate's research data
            $nodeRD = $this->getNodePerType($node_type = 'research_data', 
                $c_user_loaded);
            
            // Skip this user if a field of research filter is applied and doesn't match
            if ($field_of_research_filter && !empty($field_of_research_filter)) {
                // Check if the user has research data and the field_choose_field matches the filter
                if (!$nodeRD || $nodeRD->get('field_choose_field')->isEmpty() || 
                    $nodeRD->get('field_choose_field')->target_id != $field_of_research_filter) {
                    continue;
                }
            }
            
            $nodePD = $this->getNodePerType($node_type = 'personal_data', 
                $c_user_loaded);
            $name = $nodePD->label() . ', ' . $nodePD
                ->get('field_first_name')->value;
            //check if Evaluators completed their evaluations
            $e_fids = $c_user_loaded->get('field_evaluation')->GetValue();
            if ($e_fids[0]) {
                $signed1 = '<a href="' . 
                      $this->getFileUrl(reset($e_fids[0])) . '">' . t('View') . '</a>';
            } else {
                $signed1 =  t('-');
            }
            if ($e_fids[1]) {
                $signed2 = '<a href="' . 
                      $this->getFileUrl(reset($e_fids[1])) . '">' . t('View') . '</a>';
            } else {
                $signed2 = t('-');
            }
            if ($e_fids[2]) {
                $signed3 = '<a href="' . 
                      $this->getFileUrl(reset($e_fids[2])) . '">' . t('View') . '</a>';
            } else {
                $signed3 = t('-');
            }
            $number_evaluators = $c_user_loaded->get('field_evaluation')->GetValue();
            if (count($e_fids) == count($number_evaluators)) {
                $status = 'Complete';

            } else {
                $status = 'Waiting';
            }
            // get Research Proposal
            $research_proposal_fid = $nodeRD->field_upload_research_proposal->target_id;
            //load the Research Proposal file.
            if($research_proposal_fid) {
                $rp_url = $this->GetFileUrl($research_proposal_fid);
                $research_proposal = '<a href="' . $rp_url .'" target="_blank" 
                rel="noopener noreferrer">' . t('Research Proposal') . '</a>';
            } else {
                $research_proposal = t('Research Proposal not available');
            }
            
            $fid = $nodeRD->field_upload_your_cv->target_id;
            //load the CV file.
            if($fid) {
                $url = $this->GetFileUrl($fid);
                $cv = '<a href="' . $url .'" target="_blank" 
                rel="noopener noreferrer">' . t('View') . '</a>';
            } else {
                $cv = t('CV not available');
            }
            //get candidate's letters
            $nodeL = $this->getNodePerType($node_type = 'letters', 
                $c_user_loaded);
            
            // Initialize letter variables with default values
            $rl1 = t('Letter 1 not available');
            $rl2 = t('Letter 2 not available');
            $additional_letters = [];
            $node_edit_link = '';
            
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
                
                //load the file 2
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
                // Get node edit link for $nodeL
                if ($nodeL && $nodeL->id()) {
                    $node_add_letters = Url::fromRoute('entity.node.edit_form', ['node' => $nodeL->id()], ['query' => ['uid' => $c_user]])->toString();
                } else {
                    // Create a link to add a new recommendation letters node
                    $node_add_letters = Url::fromRoute('node.add', ['node_type' => 'letters'], ['query' => ['uid' => $c_user]])->toString();
                }
                $node_edit_link = '<a href="' . $node_add_letters . '">' . t('Add') . '</a>';
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
            //get candidate's application
            $a_fid = $c_user_loaded->field_application->target_id;
            if ($a_fid) {
                $a_url = $this->GetFileUrl($a_fid);
                $app = '<a href="' . $a_url .'" target="_blank" 
                rel="noopener noreferrer">' . t('View') . '</a>';
            } else {
                $app = '';
            }
            
            // Get the field of research name
            $field_of_research = '';
            if ($nodeRD && !$nodeRD->get('field_choose_field')->isEmpty()) {
                $term_id = $nodeRD->get('field_choose_field')->target_id;
                if ($term_id) {
                    $term = Term::load($term_id);
                    if ($term) {
                        $field_of_research = $term->getName();
                    }
                }
            }
            
            $row[] = '<tr>
                        <td>' . $name . '</td>
                        <td>' . $field_of_research . '</td>
                        <td>' . $status . '</td>
                        <td>' . $research_proposal . '</td> 
                        <td>' . $cv . '</td>
                        <td>
                            <ul>
                              <li>' . $rl1 . '</li>
                              <li>' . $rl2 . '</li>
                              ' . implode('', $additional_letters) . '
                            </ul>
                        </td>
                        <td>' . $node_edit_link . '</td>
                        <td>' . $ei . '</td>
                        <td>' . $app . '</td>
                        <td>' . $signed1 . '</td>
                        <td>' . $signed2 . '</td>
                        <td>' . $signed3 . '</td>

                     </tr>'; 
        }
        
        // Check if we have any results after filtering
        if (empty($row)) {
            return '<div class="messages messages--warning">' . 
                ($field_of_research_filter ? t('No applications found for the selected Field of Research.') : t('No applications found.')) . 
                '</div>';
        }
        
        // Build the table with results
        $output = '
            <table>
                <tr>
                    <td>' . t('Applicant') . '</td>
                    <td>' . t('Field of Research') . '</td>
                    <td>' . t('Status') . '</td>
                    <td>' . t('Research Proposal') . '</td>
                    <td>' . t('CV') . '</td>
                    <td>' . t('Recommendation letters') . '</td>
                    <td>' . t('Edit letters') . '</td>
                    <td>' . t('Expression of Interest') . '</td>
                    <td>' . t('Application') . '</td>
                    <td>' . t('Evaluation 1') . '</td>
                    <td>' . t('Evaluation 2') . '</td>
                    <td>' . t('Evaluation 3') . '</td>
                </tr>
                ' . implode($row) . '
            </table>
            <div class="details">
                <h5>' . t('Status details') . ':</h5>
                <ul>
                    <li><strong>' . t('Waiting') . '</strong> - ' . t('At least one of the Evaluators did not submit their evaluation.') . '</li>
                    <li><strong>' . t('Complete') . '</strong> - ' . t('All Evaluators submitted their evaluations.') . '</li>
                </ul>
            </div>';
            
        return $output;
    }

    /**
     * Build the filter form for Field of Research.
     *
     * @param string $selected_field
     *   The currently selected field of research.
     *
     * @return array
     *   The filter form render array.
     */
    protected function buildFilterForm($selected_field = NULL) {
        // Load all taxonomy terms from field_of_research vocabulary
        $vid = 'field_of_research';
        $terms = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadTree($vid);
            
        $options = ['' => $this->t('- All Fields of Research -')];
        foreach ($terms as $term) {
            $options[$term->tid] = $term->name;
        }
        
        $form = [
            '#type' => 'form',
            '#method' => 'get',
            '#action' => Url::fromRoute('custom_module.applications_admin')->toString(),
            'field_of_research' => [
                '#type' => 'select',
                '#title' => $this->t('Filter by Field of Research'),
                '#options' => $options,
                '#default_value' => $selected_field,
            ],
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Filter'),
            ],
            'reset' => [
                '#type' => 'link',
                '#title' => $this->t('Reset'),
                '#url' => Url::fromRoute('custom_module.applications_admin'),
                '#attributes' => [
                    'class' => ['button'],
                ],
            ],
        ];
        
        return $form;
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
 }
