<?php

namespace Drupal\research_application_workflow\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Block\BlockBase;

/**
 * @Block(
 * id = "dashboard_link_block",
 * admin_label = @Translation("Dashboard Link Block"),
 * category = @Translation("Custom")
 * )
 *
 * This block adds a link to user's dashboard form
 */
class DashboardLinkBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if (in_array('candidato', $roles)) {
      $title = t('Form');
      $link = t('My Application Form');
      return [
        '#markup' => '
                        <h2 class="block__title">' . $title . '</h2>
                        <ul class="menu">
                            <li class="leaf first"><a href="' . Url::fromUserInput('/node/add/personal_data')->toString() . '" 
                            title="' . $link . '">' . $link . '</a></li>
                        </ul>',
      ];
    }
    if (in_array('candidato_enviado', $roles)) {
      $title = t('Form');
      $link = t('My Application Form');
      $query = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('uid', $user->id())
        ->condition('type', 'personal_data');
      $pd_nid = $query->execute();
      return [
        '#markup' => '
                    <h2 class="block__title">' . $title . '</h2>
                    <ul class="menu">
                        <li class="leaf first"><a href="' . Url::fromUserInput('/node/' . reset($pd_nid) . '')->toString() . '" 
                        title="' . $link . '">' . $link . '</a></li>
                    </ul>',
      ];
    }
    if (in_array('investigador_responsable', $roles)) {
      $title = t('Active expressions of interest');
      $link = t('Expressions');
      return [
        '#markup' => '
                   <h2 class="block__title">' . $title . '</h2>
                    <ul class="menu">
                        <li class="leaf first"><a href="' . Url::fromUserInput('/researcher/expressions-of-interest')->toString() . '" 
                        title="' . $link . '">' . $link . '</a></li>
                    </ul>',
      ];
    }
    if (in_array('evaluador', $roles)) {
      $title = t('Guias');
      $link = t('Evaluations and Rankings');
      $link2 = t('User Manual');
      return [
        '#markup' => '
                   <h2 class="block__title">' . $title . '</h2>
                    <ul class="menu">
                        <li class="leaf first"><a href="' . Url::fromUserInput('/evaluator/applications')->toString() . '" 
                        title="' . $link . '">' . $link . '</a></li>
                        <li class="leaf last"><a href="' . Url::fromUserInput('/evaluator/user-manual')->toString() . '" 
                        title="' . $link2 . '">' . $link2 . '</a></li>
                    </ul>',
      ];
    }
    if (in_array('administrator', $roles)) {

      $title = t('Guias');
      $link = t('Applications Management');
      return [
        '#markup' => '
                   <h2 class="block__title">' . $title . '</h2>
                    <ul class="menu">
                        <li class="leaf first"><a href="' . Url::fromUserInput('/administrator/applications')->toString() . '"
                        title="' . $link . '">' . $link . '</a></li>
                    </ul>',
      ];
    }
  }

  /**
   *
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
