<?php

namespace Drupal\research_application_workflow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;

/**
 * Controller for managing rankings per evaluator.
 */
class RankingsEvaluatorsAdmin extends ControllerBase {

  /**
   * Displays a list of rankings submitted by evaluators.
   *
   * @return array
   *   Render array for the rankings admin page.
   */
  public function RankingsAdmin() {
    // Get all taxonomy terms for research lines
    $vid = 'field_of_research';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    
    $output = '';
    
    // For each research line, get evaluators and their rankings
    foreach ($terms as $term) {
      $term_obj = Term::load($term->tid);
      if (!$term_obj) {
        continue;
      }
      
      $output .= '<div class="pt-2"><h2>' . $term->name . '</h2>';
      
      // Get users with evaluator role who have this research line
      $query = \Drupal::entityQuery('user')
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('roles', 'evaluador')
        ->condition('field_research_lines', $term->tid);
      
      $evaluator_ids = $query->execute();
      
      if (empty($evaluator_ids)) {
        $output .= '<p>' . $this->t('No evaluators assigned to this Field of Research.') . '</p>';
        continue;
      }
      
      // Start table for this research line
      $output .= '<table class="evaluator-rankings-table">';
      $output .= '<thead><tr>';
      $output .= '<th>' . $this->t('Evaluator') . '</th>';
      $output .= '<th>' . $this->t('Ranking') . '</th>';
      $output .= '<th>' . $this->t('Status') . '</th>';
      $output .= '<th>' . $this->t('Actions') . '</th>';
      $output .= '</tr></thead>';
      $output .= '<tbody>';
      
      $evaluators = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($evaluator_ids);
      
      foreach ($evaluators as $evaluator) {
        $evaluator_name = $evaluator->getAccountName();
        
        // Check if ranking file exists for this evaluator and research line
        $ranking_file = $this->findRankingFile($evaluator->id(), $term->tid);
        
        $output .= '<tr>';
        $output .= '<td>' . $evaluator_name . '</td>';
        
        // Display ranking file if it exists
        if ($ranking_file) {
          $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($ranking_file->getFileUri());
          $output .= '<td><a href="' . $file_url . '" target="_blank">' . $this->t('View Ranking') . '</a></td>';
          
          // Check if ranking is approved
          $is_approved = $this->isRankingApproved($evaluator->id(), $term->tid);
          $status = $is_approved ? $this->t('Approved') : $this->t('Pending');
          $output .= '<td>' . $status . '</td>';
          
          // Action links
          $output .= '<td>';
          if (!$is_approved) {
            $approve_url = Url::fromRoute('research_application_workflow.approve_ranking', [
              'uid' => $evaluator->id(),
              'tid' => $term->tid,
            ])->toString();
            $output .= '<a href="' . $approve_url . '">' . $this->t('Approve') . '</a>';
          } else {
            $output .= $this->t('No actions available');
          }
          $output .= '</td>';
        } else {
          $output .= '<td>' . $this->t('No ranking submitted') . '</td>';
          $output .= '<td>-</td>';
          $output .= '<td>-</td>';
        }
        
        $output .= '</tr>';
      }
      
      $output .= '</tbody></table></div>';
    }
    
    return [
      '#markup' => $output,
      '#attached' => [
        'library' => [
          'araid_admin/global-styling',
        ],
      ],
    ];
  }
  
  /**
   * Finds a ranking file for a specific evaluator and research line.
   *
   * @param int $uid
   *   The user ID of the evaluator.
   * @param int $tid
   *   The taxonomy term ID of the research line.
   *
   * @return \Drupal\file\Entity\File|null
   *   The file entity if found, null otherwise.
   */
  private function findRankingFile($uid, $tid) {
    // Load the term to get its name
    $term = Term::load($tid);
    if (!$term) {
      return NULL;
    }
    
    // Check if the term has field_evaluators_ranking_pdf
    if (!$term->hasField('field_evaluators_ranking_pdf')) {
      return NULL;
    }
    
    // Get all file references from the field
    $file_references = $term->get('field_evaluators_ranking_pdf')->referencedEntities();
    
    // Check each file to see if it's owned by the specified user
    foreach ($file_references as $file) {
      // Load the full file entity
      $loaded_file = File::load($file->id());
      if ($loaded_file && $loaded_file->getOwnerId() == $uid) {
        return $loaded_file;
      }
    }
    
    return NULL;
  }
  
  /**
   * Checks if a ranking is approved.
   *
   * @param int $uid
   *   The user ID of the evaluator.
   * @param int $tid
   *   The taxonomy term ID of the research line.
   *
   * @return bool
   *   TRUE if the ranking is approved, FALSE otherwise.
   */
  private function isRankingApproved($uid, $tid) {
    // Load the term
    $term = Term::load($tid);
    if (!$term || !$term->hasField('field_approved_erpdf')) {
      return FALSE;
    }
    
    // Find the ranking file for this evaluator
    $ranking_file = $this->findRankingFile($uid, $tid);
    if (!$ranking_file) {
      return FALSE;
    }
    
    // Check if there's a file in field_approved_erpdf owned by this evaluator
    $approved_files = $term->get('field_approved_erpdf')->referencedEntities();
    foreach ($approved_files as $file) {
      $loaded_file = File::load($file->id());
      if ($loaded_file && $loaded_file->getOwnerId() == $uid) {
        return TRUE;
      }
    }
    
    return FALSE;
  }
}
