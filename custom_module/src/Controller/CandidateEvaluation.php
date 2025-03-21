<?php 

namespace Drupal\custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;

//creates Evaluation page for candidates
 class CandidateEvaluation extends ControllerBase {
    public function AppEvaluation() {
        $user = \Drupal::currentUser();
        $user_loaded = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($user->id());
        
        // Get evaluations for this candidate
        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'evaluation_form')
            ->condition('field_candidate', $user->id());
        $evaluation_nids = $query->execute();
        
        // If no evaluations found
        if (empty($evaluation_nids)) {
            return [
                '#markup' => '<p>' . t('No evaluations are available for your application.') . '</p>',
            ];
        }
        
        // Load all evaluation nodes
        $evaluations = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadMultiple($evaluation_nids);
        
        // Build header for the table
        $header = [
            t('Evaluator'),
            t('Strengths'),
            t('Recommendations'),
        ];
        
        // Build rows for the table
        $rows = [];
        foreach ($evaluations as $evaluation) {
            // Get the owner (evaluator) of this evaluation
            $evaluator = \Drupal::entityTypeManager()
                ->getStorage('user')
                ->load($evaluation->getOwnerId());
            
            if (!$evaluator) {
                continue;
            }
            
            // Get evaluator name
            static $evaluator_counter = 0;
            $evaluator_counter++;
            $evaluator_name = t('Evaluator @number', ['@number' => $evaluator_counter]);
            
            // Get strengths and recommendations fields
            $strengths = $evaluation->hasField('field_strengths') ? 
                $evaluation->get('field_strengths')->value : '';
            $recommendations = $evaluation->hasField('field_recommendations') ? 
                $evaluation->get('field_recommendations')->value : '';
            
            // Add row to table
            $rows[] = [
                'data' => [
                    'evaluator' => ['data' => $evaluator_name],
                    'strengths' => ['data' => ['#markup' => nl2br($strengths)]],
                    'recommendations' => ['data' => ['#markup' => nl2br($recommendations)]],
                ],
            ];
        }
        
        // Build the table
        $build = [
            '#type' => 'container',
            '#attributes' => ['class' => ['evaluation-results']],
        ];
        
        $build['disclaimer1'] = [
            '#type' => 'markup',
            '#markup' => '<p><i>' . t('The evaluator(s) understand(s) that all information related to the application and 
its evaluation contained in this form is to be handled as confidential.') . '</i></p>',
        ];
        
        $build['disclaimer2'] = [
            '#type' => 'markup',
            '#markup' => '<p><i>' . t('The evaluator(s) declare(s) to have no disqualifying or potential conflict of interest 
with this proposal that I hereby evaluate.') . '</i></p>',
        ];
        
        $build['table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => t('No evaluation data available.'),
            '#attributes' => ['class' => ['evaluation-table']],
        ];
        
        return $build;
    }
 }
