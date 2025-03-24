<?php 

namespace Drupal\research_application_workflow\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * @Block(
 * id = "links_block",
 * admin_label = @Translation("Links Block"),
 * category = @Translation("Custom")
 * )
 * 
 * This block adds links to account/logout/etc
 */
 class LinksBlock extends BlockBase {
    /**
     * {@inheritdoc}
     */
    public function build() {
        $user = \Drupal::currentUser();
        $uid = $user->id();
        $link1 = t('My account');
        $link2 = t('Log out');
        return [
            '#markup' => '
                <h2 class="block__title">' . $user->getAccountName() . '</h2>
                <ul class=menu>
                    <li class="leaf first"><a href="' . Url::fromUserInput('/user/' . $uid . '')->toString() . '" 
                    title="' . $link1 . '">' . $link1 . '</a></li>
                    <li class="leaf last"><a href="' . Url::fromUserInput('/user/logout')->toString() . '" 
                    title="' . $link2 . '">' . $link2 . '</a></li>
                </ul>
        '
    ];
    }
    public function getCacheMaxAge() {
        return 0;
    }
}