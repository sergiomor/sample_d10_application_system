<?php

namespace Drupal\research_application_workflow\EventSubscriber;

use Drupal\entity_print\Plugin\PrintEngineBase;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PreSendPrintEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\research_application_workflow\Service\RankingsManager;

/**
 * Class to manage taxonomies printing.
 */
class TaxonomyPrintConfigurationSubscriber implements EventSubscriberInterface {

  /**
   * The RankingsManager service.
   *
   * @var \Drupal\research_application_workflow\Service\RankingsManager
   */
  protected $rankingsManager;

  public function __construct(RankingsManager $rankingsManager) {
    $this->rankingsManager = $rankingsManager;
  }

  /**
   * Alters the configuration for the PDF engine for Fields of Reasearch.
   *
   * @param \Symfony\Component\EventDispatcher\GenericEvent $event
   *   The event object.
   */
  public function alterConfiguration(GenericEvent $event): void {

    // Only apply the configuration if the first entity is a taxonomy term.
    $route = \Drupal::routeMatch()->getRouteName();

    if ($route !== 'research_application_workflow.rankings_by_field') {
      return;
    }

    // Define the paper size. Adjust as needed.
    $size = 'A4';

    // Retrieve the current configuration.
    $configuration = $event->getArgument('configuration');

    // Prepare your updated configuration.
    $update_config = [
      'default_paper_size' => $size,
      'default_paper_orientation' => PrintEngineBase::LANDSCAPE,
    ];

    // Merge the new config with the existing configuration.
    $new_configuration = $update_config + $configuration;

    // Set the updated configuration.
    $event->setArgument('configuration', $new_configuration);
  }

  /**
   * Alters the render array before the PDF is generated.
   *
   * @param \Drupal\entity_print\Event\PreSendPrintEvent $event
   *   The event object.
   */
  public function alterRenderArray(PreSendPrintEvent $event): void {

    $parameters = \Drupal::routeMatch()->getParameters();
    $tid = $parameters->get('tid');
    if (!$tid) {
      return;
    }

    // Fetch rankings data from your service.
    $rankingsData = $this->rankingsManager->getRankingsData($tid);

    // Get Drupal's renderer service.
    $renderer = \Drupal::service('renderer');

    // Add CSS styles.
    $styles = '<style>
            @page {
                margin: 1cm;
                size: A4 landscape;
            }
            h1 {
                font-size: 14pt;
                font-weight: bold;
                margin-bottom: 20px;
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 9pt;
                line-height: 1.2;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                border-top: 1px solid #ddd;
            }
            th {
                background-color: #f9f9f9;
                font-weight: bold;
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            td {
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
        </style>';

    $clauses1 = t('I understand that all information related to the applications and their evaluation is 
        to be handled as confidential.');
    $clauses2 = t(' I declare to have no disqualifying or potential conflict of interest with any of the 
        applications that I have evaluated.');

    // Convert the render array to HTML.
    $html = $styles . $renderer->renderRoot($rankingsData) . '<div>' . $clauses1 . '</div><div>' . $clauses2 . '</div>';

    // Clear any existing content.
    $printEngine = $event->getPrintEngine();
    $reflection = new \ReflectionObject($printEngine);
    $property = $reflection->getProperty('html');
    $property->setAccessible(TRUE);
    $property->setValue($printEngine, '');

    $event->getPrintEngine()->addPage($html);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PrintEvents::CONFIGURATION_ALTER => 'alterConfiguration',
      PrintEvents::PRE_SEND => 'alterRenderArray',
    ];
  }

}
