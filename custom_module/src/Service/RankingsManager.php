<?php

namespace Drupal\research_application_workflow\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class RankingsManager {
  use StringTranslationTrait;
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function getRankingsData($tid) {
    // Load the taxonomy term to get its name
    $term = Term::load($tid);

    // Get users with role 'candidato_enviado'
    $ids = \Drupal::entityQuery('user')
    ->accessCheck(TRUE)
    ->condition('status', 1)
    ->condition('roles', 'candidato_enviado')
    ->execute();
    $users = User::loadMultiple($ids);
    $rankings = [];

    $third_evaluator = false;
    foreach ($users as $user) {

        $evaluators = count($user->get('field_evaluators')->getValue());

        if ($evaluators == 3) {
            $third_evaluator = true;
        }

        // Get Research data node
        $research_data_nids = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', 'research_data')
          ->condition('uid', $user->id())
          ->condition('field_choose_field', $tid)
          ->execute();

        if (empty($research_data_nids)) {
            continue;
        } else {
            // Get personal data node
            $personal_data_nids = \Drupal::entityQuery('node')
              ->accessCheck(TRUE)
              ->condition('type', 'personal_data')
              ->condition('uid', $user->id())
              ->execute();

            if (empty($personal_data_nids)) {
              continue;
            }

            $personal_data_nid = reset($personal_data_nids);
            $personal_data_node = Node::load($personal_data_nid);

            if (!$personal_data_node) {
              continue;
            }

            $first_name = $personal_data_node->hasField('field_first_name') ? 
              $personal_data_node->get('field_first_name')->value : '';
            $title = $personal_data_node->getTitle();

            // Query for interest nodes
            $interest_nids = \Drupal::entityQuery('node')
              ->accessCheck(TRUE)
              ->condition('type', 'interest')
              ->condition('field_candidate', $user->id())
              ->execute();

            if (!empty($interest_nids)) {
              $interest_nid = reset($interest_nids);
              $interest_node = Node::load($interest_nid);

              if ($interest_node) {
                $access_field = $interest_node->get('field_access_to_interview');
                $field_definition = $access_field->getFieldDefinition();
                $settings = $field_definition->getSettings();
                $access_value = $access_field->value ? $settings['on_label'] : $settings['off_label'];

                // Helper function to get field value or default
                $getFieldValue = function($field_name) use ($interest_node) {
                  if (!$interest_node->hasField($field_name)) {
                    return '-';
                  }
                  $value = $interest_node->get($field_name)->value;
                  return ($value === NULL || $value === '') ? '-' : $value;
                };

                $rankings[] = [
                  'uid' => $user->id(),
                  'first_name' => $first_name,
                  'title' => $title,
                  'position_eva1' => $getFieldValue('field_position_eva1'),
                  'position_eva2' => $getFieldValue('field_position_eva2'),
                  'position_eva3' => $getFieldValue('field_position_eva3'),
                  'average_position' => $getFieldValue('field_average_position'),
                  'access_to_interview' => $access_value,
                  'research_center' => !empty($interest_node->getTitle()) ? $interest_node->getTitle() : '-',
                  'marks_eva1' => $getFieldValue('field_marks_eva1'),
                  'marks_eva2' => $getFieldValue('field_marks_eva2'),
                  'marks_eva3' => $getFieldValue('field_marks_eva3'),
                  'average' => $getFieldValue('field_average'),
                  'difference' => $getFieldValue('field_difference'),
                ];
              }
            }
        }
    }
    
    // Sort rankings by average score in descending order
    usort($rankings, function($a, $b) {
      // Extract values, defaulting to highest possible value if missing/invalid
      $a_val = isset($a['average_position']) && is_numeric($a['average_position']) 
          ? (float)$a['average_position'] 
          : PHP_INT_MAX; // Push non-numeric values to the end
          
      $b_val = isset($b['average_position']) && is_numeric($b['average_position']) 
          ? (float)$b['average_position'] 
          : PHP_INT_MAX;
      
      // Sort numerically
      if ($a_val == $b_val) {
          // If average positions are equal, sort by average marks instead
          $a_mark = isset($a['average']) && is_numeric($a['average']) ? (float)$a['average'] : 0;
          $b_mark = isset($b['average']) && is_numeric($b['average']) ? (float)$b['average'] : 0;
          return $b_mark <=> $a_mark; // Higher marks first
      }
      
      return $a_val <=> $b_val; // Lower positions first (1st place, 2nd place, etc.)
    });

    // Add rank position
    $rank = 1;
    foreach ($rankings as &$ranking) {
      $ranking['rank'] = $rank++;
    }

    // Build the table header
    $header = [
      'rank' => $this->t('Nº'),
      'first_name' => $this->t('Name'),
      'title' => $this->t('Surname'),
      'position_eva1' => $this->t('Position ev 1'),
      'position_eva2' => $this->t('Position ev 2'),
      'position_eva3' => $this->t('Position ev 3'),
      'average_position' => $this->t('Average Position'),
      'access_to_interview' => $this->t('Access to Interview'),
      'research_center' => $this->t('Host'),
      'marks_eva1' => $this->t('Marks ev 1'),
      'marks_eva2' => $this->t('Marks ev 2'),
      'marks_eva3' => $this->t('Marks ev 3'),
      'average' => $this->t('Average'),
      'difference' => $this->t('Difference'),
    ];

    // if $third_evaluator is false unset position_eva3 and marks_eva3 from header and rows
    if (!$third_evaluator) {
      // Remove columns from header
      unset($header['position_eva3']);
      unset($header['marks_eva3']);
      
      // Remove data from existing rankings array
      foreach ($rankings as &$rank) {
          unset($rank['position_eva3']);
          unset($rank['marks_eva3']);
      }
      unset($rank); // Break the reference to the last element
      
      // Won't collect these values when building rows
      $include_third_evaluator = false;
    } else {
      $include_third_evaluator = true;
    }    

    // Build the table rows
    $rows = [];
    $marks_eva1 = [];
    $marks_eva2 = [];
    $marks_eva3 = [];
    $marks_average = [];

    // Break references in the array
    $rankings_copy = [];
    foreach ($rankings as $key => $value) {
        $rankings_copy[$key] = unserialize(serialize($value)); // Deep clone
    }
    $rankings = $rankings_copy;

    foreach ($rankings as $index => $ranking) {
      $row = [
          'uid' => $ranking['uid'],
          'first_name' => $ranking['first_name'],
          'name' => $ranking['title'],
          'position_eva1' => $ranking['position_eva1'],
          'position_eva2' => $ranking['position_eva2'],
      ];
      
      // Only include third evaluator data if available
      if ($include_third_evaluator) {
          $row['position_eva3'] = $ranking['position_eva3'];
      }
      
      // Continue adding other fields
      $row['average_position'] = $ranking['average_position'];
      $row['access_to_interview'] = $ranking['access_to_interview'];
      $row['research_center'] = $ranking['research_center']; 
      $row['marks_eva1'] = $ranking['marks_eva1'];
      $row['marks_eva2'] = $ranking['marks_eva2'];
      
      // Only include third evaluator marks if available
      if ($include_third_evaluator) {
          $row['marks_eva3'] = $ranking['marks_eva3'];
      }
      
      $row['average'] = $ranking['average'];
      $row['difference'] = $ranking['difference'];
      
      $rows[] = $row;
    }

    // check if ranking was generated
    $fid = $term->get('field_ranking_pdf')->target_id;
    if ($fid) {
        // Populate marks arrays for statistics
        foreach ($rankings as $ranking) {
            if (isset($ranking['marks_eva1']) && is_numeric($ranking['marks_eva1'])) {
                $marks_eva1[] = (float)$ranking['marks_eva1'];
            }
            if (isset($ranking['marks_eva2']) && is_numeric($ranking['marks_eva2'])) {
                $marks_eva2[] = (float)$ranking['marks_eva2'];
            }
            if ($include_third_evaluator && isset($ranking['marks_eva3']) && is_numeric($ranking['marks_eva3'])) {
                $marks_eva3[] = (float)$ranking['marks_eva3'];
            }
            if (isset($ranking['average']) && is_numeric($ranking['average'])) {
                $marks_average[] = (float)$ranking['average'];
            }
        }

        // Calculate statistics
        $stats = [];
        $mark_arrays = [
            'marks_eva1' => $marks_eva1,
            'marks_eva2' => $marks_eva2,
        ];
        
        // Only include third evaluator stats if available
        if ($include_third_evaluator) {
            $mark_arrays['marks_eva3'] = $marks_eva3;
        }
        
        $mark_arrays['average'] = $marks_average;
        
        foreach ($mark_arrays as $key => $values) {
            if (!empty($values)) {
                $max = max($values);
                $min = min($values);
                $avg = array_sum($values) / count($values);
                
                // Calculate standard deviation
                $variance = array_reduce($values, function($carry, $item) use ($avg) {
                    return $carry + pow($item - $avg, 2); // Square the differences
                }, 0) / count($values);
                $deviation = sqrt($variance);

                $stats[$key] = [
                    'diff_max_min' => number_format($max - $min, 1),
                    'average' => number_format($avg, 1),
                    'deviation' => number_format($deviation, 1),
                ];
            }
        }

        // Calculate appropriate colspan
        $colspan = $include_third_evaluator ? 9 : 8;

        // Add summary rows
        $diff_row = [
            'name' => [
                'data' => [
                    '#type' => 'markup',
                    '#markup' => $this->t('Difference max-min'),
                    '#prefix' => '<strong>',
                    '#suffix' => '</strong>',
                ],
                'class' => ['summary-label'],
                'colspan' => $colspan
            ],
            'marks_eva1' => ['data' => isset($stats['marks_eva1']['diff_max_min']) ? $stats['marks_eva1']['diff_max_min'] : '-', 'class' => ['summary-row']],
            'marks_eva2' => ['data' => isset($stats['marks_eva2']['diff_max_min']) ? $stats['marks_eva2']['diff_max_min'] : '-', 'class' => ['summary-row']],
        ];
        
        if ($include_third_evaluator) {
            $diff_row['marks_eva3'] = ['data' => isset($stats['marks_eva3']['diff_max_min']) ? $stats['marks_eva3']['diff_max_min'] : '-', 'class' => ['summary-row']];
        }
        
        $diff_row['average'] = ['data' => isset($stats['average']['diff_max_min']) ? $stats['average']['diff_max_min'] : '-', 'class' => ['summary-row']];
        $rows[] = $diff_row;

        $avg_row = [
            'name' => [
                'data' => [
                    '#type' => 'markup',
                    '#markup' => $this->t('Average'),
                    '#prefix' => '<strong>',
                    '#suffix' => '</strong>',
                ],
                'class' => ['summary-label'],
                'colspan' => $colspan
            ],
            'marks_eva1' => ['data' => isset($stats['marks_eva1']['average']) ? $stats['marks_eva1']['average'] : '-', 'class' => ['summary-row']],
            'marks_eva2' => ['data' => isset($stats['marks_eva2']['average']) ? $stats['marks_eva2']['average'] : '-', 'class' => ['summary-row']],
        ];
        
        if ($include_third_evaluator) {
            $avg_row['marks_eva3'] = ['data' => isset($stats['marks_eva3']['average']) ? $stats['marks_eva3']['average'] : '-', 'class' => ['summary-row']];
        }
        
        $avg_row['average'] = ['data' => isset($stats['average']['average']) ? $stats['average']['average'] : '-', 'class' => ['summary-row']];
        $rows[] = $avg_row;

        $dev_row = [
            'name' => [
                'data' => [
                    '#type' => 'markup',
                    '#markup' => $this->t('Deviation'),
                    '#prefix' => '<strong>',
                    '#suffix' => '</strong>',
                ],
                'class' => ['summary-label'],
                'colspan' => $colspan
            ],
            'marks_eva1' => ['data' => isset($stats['marks_eva1']['deviation']) ? $stats['marks_eva1']['deviation'] : '-', 'class' => ['summary-row']],
            'marks_eva2' => ['data' => isset($stats['marks_eva2']['deviation']) ? $stats['marks_eva2']['deviation'] : '-', 'class' => ['summary-row']],
        ];
        
        if ($include_third_evaluator) {
            $dev_row['marks_eva3'] = ['data' => isset($stats['marks_eva3']['deviation']) ? $stats['marks_eva3']['deviation'] : '-', 'class' => ['summary-row']];
        }
        
        $dev_row['average'] = ['data' => isset($stats['average']['deviation']) ? $stats['average']['deviation'] : '-', 'class' => ['summary-row']];
        $rows[] = $dev_row;
    }

    $end_date = \Drupal::config('research_application_workflow.applicationsettings')->get('end_date');;
    $year = '';
    if (!empty($end_date)) {
      $date = new DrupalDateTime($end_date);
      $year = $date->format('Y');
    }

    // Get the position of the term in the vocabulary
    $position = '';
    $vid = 'field_of_research';
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vid, 0, NULL, TRUE);
    
    foreach ($terms as $index => $vocab_term) {
      if ($vocab_term->id() == $tid) {
        $position = $index + 1;
        break;
      }
    }

    return [
      'title' => [
        '#markup' => '<h1>ARAID CALL ' . $year . ' ' . $position . '. ' . $term->getName() . '</h1>',
      ],
      'description' => [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Below are the rankings of candidates in this field of research, sorted by average score.') . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => ['class' => ['ranking-table']],
        '#empty' => $this->t('No rankings available for this field.'),
        '#attached' => [
          'library' => ['research_application_workflow/ranking-styles'],
          'html_head' => [
            [
              [
                '#type' => 'html_tag',
                '#tag' => 'style',
                '#value' => '
                  .ranking-table .summary-row {
                    background-color: #f5f5f5;
                    font-weight: bold;
                  }
                ',
              ],
              'ranking-table-styles',
            ],
          ],
        ],
      ]
    ];
  } 
}