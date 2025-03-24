<?php

namespace Drupal\research_application_workflow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Controller for downloading candidate data as CSV.
 */
class DownloadCandidatesCsv extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a DownloadCandidatesCsv object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Generates and downloads a CSV file with candidate data.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response containing the CSV file.
   */
  public function downloadCsv() {
    // Create a file handle for PHP output
    $handle = fopen('php://temp', 'w+');
    
    // Define the CSV header
    $header = [
      'Name',
      'Area',
      'Position in the ranking',
      'Email',
      'Gender',
      'Date of birth',
      'Place',
      'Nacionality',
      'Current position',
      'Institution',
      'City',
      'Ph. D.',
      'Ph. D. University',
      'Ph. D. date',
      'Years of postdoctoral experience outside Aragón',
      'Years of postdoctoral experience outside Spain',
      'Host Institution',
      'Research manager',
      'Email of the Research manager',
      'Submission Date',
    ];
    
    // Write the header to the CSV
    fputcsv($handle, $header);
    
    // Get all users with the 'candidato_enviado' role
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['roles' => 'candidato_enviado', 'status' => 1]);
    
    foreach ($users as $user) {
      $user_id = $user->id();
      $row = [];
      
      // Get personal data node for this user
      $personal_data_nodes = $this->entityTypeManager->getStorage('node')
        ->loadByProperties([
          'type' => 'personal_data',
          'uid' => $user_id,
          'status' => 1,
        ]);
      
      if (empty($personal_data_nodes)) {
        continue; // Skip if no personal data found
      }
      
      $personal_data = reset($personal_data_nodes);
      
      // Get research data node for this user
      $research_data_nodes = $this->entityTypeManager->getStorage('node')
        ->loadByProperties([
          'type' => 'research_data',
          'uid' => $user_id,
          'status' => 1,
        ]);
      
      if (empty($research_data_nodes)) {
        continue; // Skip if no research data found
      }
      
      $research_data = reset($research_data_nodes);
      
      // Get interest nodes for this user
      $interest_query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'interest')
        ->condition('field_candidate', $user_id)
        ->condition('status', 1)
        ->accessCheck(TRUE);
      
      $interest_nids = $interest_query->execute();
      
      if (empty($interest_nids)) {
        continue; // Skip if no interest nodes found
      }
      
      $interest_node = $this->entityTypeManager->getStorage('node')->load(reset($interest_nids));
      
      // Get research manager (owner of the interest node)
      $research_manager_id = $interest_node->getOwnerId();
      $research_manager = User::load($research_manager_id);
      
      // Extract field values
      
      // Personal Data
      $name = '';
      if (!$personal_data->get('field_first_name')->isEmpty()) {
        $name = $personal_data->get('field_first_name')->value;
      } elseif (!$personal_data->getTitle()) {
        $name = $personal_data->getTitle();
      }
      
      $email = $personal_data->get('field_e_mail')->isEmpty() ? '' : $personal_data->get('field_e_mail')->value;
      $gender = $personal_data->get('field_sex')->isEmpty() ? '' : $personal_data->get('field_sex')->value;
      
      $date_of_birth = '';
      if (!$personal_data->get('field_date_of_birth')->isEmpty()) {
        $date_of_birth_obj = $personal_data->get('field_date_of_birth')->date;
        if ($date_of_birth_obj) {
          $date_of_birth = $date_of_birth_obj->format('Y-m-d');
        }
      }
      
      $place_of_birth = $personal_data->get('field_place_of_birth')->isEmpty() ? '' : $personal_data->get('field_place_of_birth')->value;
      $nationality = $personal_data->get('field_nationality')->isEmpty() ? '' : $personal_data->get('field_nationality')->value;
      $current_position = $personal_data->get('field_current_position')->isEmpty() ? '' : $personal_data->get('field_current_position')->value;
      $institution = $personal_data->get('field_institution_company')->isEmpty() ? '' : $personal_data->get('field_institution_company')->value;
      $city = $personal_data->get('field_city_country')->isEmpty() ? '' : $personal_data->get('field_city_country')->value;
      $phd = $personal_data->get('field_ph_d')->isEmpty() ? '' : $personal_data->get('field_ph_d')->value;
      $phd_date = '';
      if (!$personal_data->get('field_year_end_2')->isEmpty()) {
        $phd_date = $personal_data->get('field_year_end_2')->value;
      }
      
      $postdoc_aragon = $personal_data->get('field_postdoctoral_aragon')->isEmpty() ? '' : $personal_data->get('field_postdoctoral_aragon')->value;
      $postdoc_spain = $personal_data->get('field_postdoctoral_spain')->isEmpty() ? '' : $personal_data->get('field_postdoctoral_spain')->value;
      
      // Get the creation date of the personal_data node
      $submission_date = '';
      if ($personal_data->getCreatedTime()) {
        $submission_date = \Drupal::service('date.formatter')->format($personal_data->getCreatedTime(), 'custom', 'Y-m-d H:i:s');
      }
      
      // Research Data
      $area = '';
      if (!$research_data->get('field_choose_field')->isEmpty()) {
        $term_id = $research_data->get('field_choose_field')->target_id;
        if ($term_id) {
          $term = Term::load($term_id);
          if ($term) {
            $area = $term->getName();
          }
        }
      }
      
      // Interest Data
      $host_institution = $interest_node->get('field_research_center_or_group')->isEmpty() ? '' : $interest_node->get('field_research_center_or_group')->value;
      $position_ranking = $interest_node->get('field_average_position')->isEmpty() ? '' : $interest_node->get('field_average_position')->value;
      $research_manager_name = $research_manager ? $research_manager->getDisplayName() : '';
      $research_manager_email = $research_manager ? $research_manager->getEmail() : '';
      
      // Build the row in the specified order
      $row = [
        $name,                 // Name
        $area,                 // Area
        $position_ranking,     // Position in the ranking
        $email,                // Email
        $gender,               // Gender
        $date_of_birth,        // Date of birth
        $place_of_birth,       // Place of Birth
        $nationality,          // Nationality
        $current_position,     // Current Position
        $institution,          // Institution
        $city,                 // City
        $phd,                  // Ph. D
        '',                    // Ph. D. University (not specified in the requirements)
        $phd_date,             // Ph. D date
        $postdoc_aragon,       // Years of postdoctoral experience outside Aragón
        $postdoc_spain,        // Years of postdoctoral experience outside Spain
        $host_institution,     // Host institution
        $research_manager_name, // Research Manager
        $research_manager_email, // Email of the Research manager
        $submission_date,      // Submission Date (personal_data creation date)
      ];
      
      // Write the row to the CSV
      fputcsv($handle, $row);
    }
    
    // Get the content of the CSV
    rewind($handle);
    $csv_content = stream_get_contents($handle);
    fclose($handle);
    
    // Create the response with the CSV content
    $response = new Response($csv_content);
    
    // Set the headers for a CSV file download
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="candidates_data.csv"');
    
    return $response;
  }
}
