<?php

namespace Drupal\research_application_workflow\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Url;

/**
 * @Block(
 * id = "pestanas_cv",
 * admin_label = @Translation("Pestanas CV"),
 * category = @Translation("Custom")
 * )
 *
 * This block adds navigation tabs to candidato users
 */
class PestanasBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    $path = \Drupal::service('path.current')->getPath();
    if (in_array('candidato', $roles)
          || in_array('candidato_enviado', $roles)) {
      if (in_array('candidato_evaluado', $roles)) {
        $tab7 = t('Evaluation');
        if ($path == '/candidate/evaluation') {
          $css_class_7 = 'active';
        }
        else {
          $css_class_7 = 'inactive';
        }
        $link7_markup = '<li class="' . $css_class_7 . '"><a href="' . Url::fromUserInput('/candidate/evaluation')->toString() . '" title="'
                    . $tab7 . '">' . $tab7 . '</a></li>';
      }
      else {
        $link7_markup = '';
      }
      if ($path == '/candidate/expressions-of-interest') {
        $css_class_6 = 'active';
      }
      else {
        $css_class_6 = 'inactive';
      }
      // Build the navigation markup.
      return [
        '#markup' => t('
                  <ul class="menu">
                    <li class="' . $this->linkActive($user, $content_type = 'personal_data') . '"><a href="' .
                    $this->getLink($user, $content_type = 'personal_data') . '
                        " title="' . $this->getLabel($content_type = 'personal_data') . '
                        ">' . $this->getLabel($content_type = 'personal_data') . '</a></li>
                    <li class="' . $this->linkActive($user, $content_type = 'research_data') . '"><a href="' .
                    $this->getLink($user, 'research_data') . '
                        " title="' . $this->getLabel($content_type = 'research_data') . '
                        ">' . $this->getLabel($content_type = 'research_data') . '</a></li>
                    <li class="' . $this->linkActive($user, $content_type = 'more_information') . '"
                        ><a href="' . $this->getLink($user, 'more_information') . '
                        " title="' . $this->getLabel('more_information') . '
                        ">' . $this->getLabel('more_information') . '</a></li>
                    <li class="' . $this->linkActive($user, $content_type = 'letters') . '"><a href="' .
                    $this->getLink($user, 'letters') . '
                        "="' . $this->getLabel('letters') . '
                            ">' . $this->getLabel('letters') . '</a></li>
                    <li class="' . $css_class_6 . '"><a href="@link6" title="@tab6">@tab6</a></li>
                    ' . $link7_markup . '
                  </ul>
                    ', [
                      '@link6' => Url::fromUserInput('/candidate/expressions-of-interest')->toString(),
                      '@tab1' => t('Personal Data'),
                      '@tab2' => t('Research Data'),
                      '@tab4' => t('More Information'),
                      '@tab5' => t('Letters'),
                      '@tab6' => t('Expressions of Interest'),
                    ]),
      ];
    }
    else {
      return;
    }
  }

/**
 * Generates appropriate link for a navigation tab.
 *
 * @param \Drupal\Core\Session\AccountInterface $user
 *   The current user account.
 * @param string $content_type
 *   The content type machine name for the tab.
 *
 * @return string
 *   The URL for either:
 *   - Existing node if user has created one
 *   - Node add form if no node exists
 *
 * This enables dynamic tab links that adapt to whether
 * content already exists or needs to be created.
 */
  public function getLink($user, $content_type) {

    // Check if the user created a node
    // of type $content_type.
    // build tab link accordingly.
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('uid', $user->id())
      ->condition('type', $content_type);
    $nid = $query->execute();
    if (count($nid) > 0) {
      $link = Url::fromUserInput('/node/' . reset($nid) . '')->toString();
    }
    else {
      $link = Url::fromUserInput('/node/add/' . $content_type . '')->toString();
    }
    return $link;
  }

  /**
   *
   */
  public function getLabel($content_type) {

    // Get content type label.
    $types = NodeType::loadMultiple();
    return $types[$content_type]->label();
  }

/**
 * Determines if a navigation tab should be marked as active.
 *
 * @param \Drupal\Core\Session\AccountInterface $user
 *   The current user account.
 * @param string $content_type
 *   The content type machine name for the tab.
 *
 * @return string
 *   Returns 'active' if current path matches tab link, 'inactive' otherwise.
 *
 * This helps highlight the current active tab in the navigation block.
 */
  public function linkActive($user, $content_type) {
    // Get content type label.
    $active = \Drupal::service('path.current')->getPath();
    if ($active == $this->getLink($user, $content_type)) {
      return 'active';
    }
    else {
      return 'inactive';
    }
  }

/**
 * Disables caching for this block.
 *
 * @return int
 *   Returns 0 to indicate no caching should occur.
 *
 * This ensures the navigation tabs block always shows
 * current active/inactive states by preventing caching.
 */
  public function getCacheMaxAge() {
    return 0;
  }

}
