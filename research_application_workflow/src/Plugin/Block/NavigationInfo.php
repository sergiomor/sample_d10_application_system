<?php

namespace Drupal\research_application_workflow\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\user\Entity\Role;
use Drupal\Core\Url;

/**
 * @Block(
 * id = "navigation_info",
 * admin_label = @Translation("Navigation Info"),
 * category = @Translation("Custom")
 * )
 *
 * This block adds role infand links to application
 * pdf and submission form
 */
class NavigationInfo extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = \Drupal::currentUser();
    // Get role label.
    $roles = Role::loadMultiple($user->getRoles());
    $label = [];
    foreach ($roles as $role) {
      $label = $role->label();
      $labels[] = $label;
    }
    if (in_array('candidato', $user->getRoles())
          || in_array('candidato_enviado', $user->getRoles())) {
      $queryPD = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('uid', $user->id())
        ->condition('type', 'personal_data');
      $pd_nid = $queryPD->execute();
      if ($pd_nid) {
        $pdf_link = Url::fromUserInput('/print/view/pdf/application_pdf/print?view_args%5B0%5D=' . $user->id() . '')->toString();
      }
      else {
        $pdf_link = Url::fromUserInput('/node/add/personal_data')->toString();
      }
      $user = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($user->id());
      if ($user->get('field_signed_ei_fid')->target_id && !$user->hasRole('candidato_evaluado')) {
        $submit = t('Submit Application');
        $submit_link = Url::fromUserInput('/candidate/submit-application/' . $user->id() . '')->toString();

        $submit = '<a href="' . $submit_link . '" 
                                title="' . $submit . '"> | <strong>' . $submit . '</strong></a>';
      }
      else {
        $submit = '';
      }
      $pdf = t('Create PDF');
      return [
        '#markup' => '
                    <div class="left">
                        <ul class="menu">
                            <li class="leaf first">
                                ' . $labels['1'] . '
                            </li>
                        </ul>
                    </div>
                    <div class="right">
                        <ul class="menu">
                            <li class="leaf first"><a href="' . $pdf_link . '" 
                                title="' . $pdf . '">' . $pdf . '</a>' . $submit . '
                            </li>
                        </ul>
                    </div>
                ',
      ];
    }
    else {
      if (!in_array('anonymous', $user->getRoles())) {
        return [
          '#markup' => '
                        <div class="left">
                            <ul class="menu">
                                <li class="leaf first">
                                    ' . $labels['1'] . '
                                </li>
                            </ul>
                        </div>
                    ',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
