<?php 

namespace Drupal\custom_module\Plugin\Block;

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
        $path =\Drupal::service('path.current')->getPath();
        if (in_array('candidato', $roles) 
            || in_array('candidato_enviado', $roles)) {
            if (in_array('candidato_evaluado', $roles)) {
                $tab7 = t('Evaluation');
                if ($path == '/candidate/evaluation') {
                    $css_class_7 = 'active';
                } else {
                    $css_class_7 ='inactive';
                }
                $link7_markup = '<li class="' . $css_class_7 . '"><a href="' . Url::fromUserInput('/candidate/evaluation')->toString() . '" title="' 
                    . $tab7 .'">' . $tab7 . '</a></li>';
            } else {
                $link7_markup = '';
            }
            if ($path == '/candidate/expressions-of-interest') {
                $css_class_6 = 'active';
            } else {
                $css_class_6 ='inactive';
            }
            //Build the navigation markup 
            return [
                '#markup' => t('
                  <ul class="menu">
                    <li class="' . $this->LinkActive($user, $content_type = 'personal_data') . '"><a href="' . 
                        $this->GetLink($user, $content_type = 'personal_data'). '
                        " title="' . $this->GetLabel($content_type = 'personal_data') . '
                        ">' . $this->GetLabel($content_type = 'personal_data') . '</a></li>
                    <li class="' . $this->LinkActive($user, $content_type = 'research_data') . '"><a href="' . 
                        $this->GetLink($user, 'research_data'). '
                        " title="' . $this->GetLabel($content_type = 'research_data') . '
                        ">' . $this->GetLabel($content_type = 'research_data') . '</a></li>
                    <li class="' . $this->LinkActive($user, $content_type = 'more_information') . '"
                        ><a href="' . $this->GetLink($user, 'more_information'). '
                        " title="' . $this->GetLabel('more_information'). '
                        ">' . $this->GetLabel('more_information'). '</a></li>
                    <li class="' . $this->LinkActive($user, $content_type = 'letters') . '"><a href="' . 
                        $this->GetLink($user, 'letters'). '
                        "="' . $this->GetLabel('letters'). '
                            ">' . $this->GetLabel('letters'). '</a></li>
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
        } else {
            return;
        }
    }

    public function GetLink($user, $content_type) {
        //check if the user created a node 
        //of type $content_type.
        //build tab link accordingly
        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('uid', $user->id())
            ->condition('type', $content_type);
        $nid=$query->execute();
        if (count($nid) > 0) {
            $link = Url::fromUserInput('/node/' . reset($nid) . '')->toString();
        } else {
            $link = Url::fromUserInput('/node/add/' . $content_type . '')->toString();
        };
        return $link;
    }

    public function GetLabel($content_type) {
        //get content type label
        $types = NodeType::loadMultiple();
        return $types[$content_type]->label();
    }

    public function LinkActive($user, $content_type) {
        //get content type label
        $active = \Drupal::service('path.current')->getPath();
        if ($active == $this->GetLink($user, $content_type)) {
            return 'active';
        } else {
            return 'inactive';
        }
    }
    public function getCacheMaxAge() {
        return 0;
    }
}