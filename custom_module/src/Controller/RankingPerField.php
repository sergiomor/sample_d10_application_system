<?php

namespace Drupal\research_application_workflow\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\research_application_workflow\Service\RankingsManager;
use Drupal\taxonomy\Entity\Term;

/**
 * Controller for displaying rankings per field of research.
 */
class RankingPerField extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The RankingsManager service.
   *
   * @var \Drupal\research_application_workflow\Service\RankingsManager
   */
  protected $rankingsManager;

  /**
   * Constructs a RankingPerField object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\research_application_workflow\Service\RankingsManager $rankings_manager
   */
  public function __construct(ConfigFactoryInterface $config_factory,
    RankingsManager $rankings_manager) {
    $this->configFactory = $config_factory;
    $this->rankingsManager = $rankings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('research_application_workflow.rankings_manager')
    );
  }

  /**
   * Displays the ranking per field page.
   *
   * @return array
   *   A render array.
   */
  public function content() {
    $end_date = \Drupal::config('research_application_workflow.applicationsettings')->get('end_date');
    if (!empty($end_date)) {
      $end_date = new DrupalDateTime($end_date);
      $now = new DrupalDateTime('now');
      
      if ($end_date > $now) {
        return [
          '#markup' => $this->t('Currently there is a Call for Applications process. Rankings can only be displayed after the current Call ends.'),
        ];
      }
    }

    // Load all taxonomy terms from field_of_research vocabulary
    $vid = 'field_of_research';
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vid);

    $items = [];
    foreach ($terms as $term) {
      $url = Url::fromRoute('research_application_workflow.rankings_by_field', ['tid' => $term->tid]);
      $items[] = Link::fromTextAndUrl($term->name, $url)->toRenderable();
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#list_type' => 'ul',
    ];
  }

  /**
   * Displays the rankings for a specific field.
   *
   * @param string $tid
   *   The taxonomy term ID of the field of research.
   *
   * @return array
   *   A render array.
   */
  public function fieldRankings($tid) {

    $rankings = $this->rankingsManager->getRankingsData($tid);

    // Check there are canditates for this Field of Research
    $research_data_nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'research_data')
      ->condition('field_choose_field', $tid)
      ->execute();
  
    if (!empty($research_data_nids)) {
        // Add action form
        $rankings['action_form'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-actions']],
          'form' => \Drupal::formBuilder()->getForm('Drupal\research_application_workflow\Form\RankingActionForm', $tid),
        ];
    }

    // Load the taxonomy term to get its name
    $term = Term::load($tid);
    // check if ranking was generated
    $fid = $term->get('field_ranking_pdf')->target_id;
    if ($fid) { 
      // add Aprove Ranking
      $rankings['approve_form'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['form-actions']],
        'form' => \Drupal::formBuilder()->getForm('Drupal\research_application_workflow\Form\ApproveActionForm', $tid),
      ];
    }
    
    // Add back button
    $rankings['back_button'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Fields of Research'),
      '#url' => Url::fromRoute('research_application_workflow.ranking_per_field'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    return $rankings;
  } 
}
