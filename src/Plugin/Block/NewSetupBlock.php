<?php

namespace Drupal\dpc_user_management\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a 'New Account Setup Message' Block.
 *
 * @Block(
 *   id = "new_setup_block",
 *   admin_label = @Translation("New Account Setup Message"),
 *   category = @Translation("New Account Setup Message"),
 * )
 */
class NewSetupBlock extends BlockBase {

    public static $_default_text = 'Welcome to our new site, if it’s your first time logging in, you’ll need to reset your password.';
    /**
     * @var \Drupal\Component\Plugin\Context\ContextInterface[]
     */
    public $field_id_content = 'content';

    /**
     * {@inheritdoc}
     */
    public function build() {
        $content = !empty($config[$this->field_id_content]) ? $config[$this->field_id_content] : self::$_default_text;

        return [
            '#markup' => $this->t($content),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
        $form = parent::blockForm($form, $form_state);

        $config = $this->getConfiguration();

        $form[$this->field_id_content] = [
            '#type' => 'textfield',
            '#title' => $this->t('Text for new account setup'),
            '#description' => $this->t('This will be displayed on the profile page for new users.'),
            '#default_value' => !empty($config[$this->field_id_content]) ? $config[$this->field_id_content] : self::$_default_text
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
        parent::blockSubmit($form, $form_state);
        $this->configuration[$this->field_id_content] = $form_state->getValue($this->field_id_content);
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {

        return AccessResult::allowedIf(true);//$account->getLastAccessedTime() == 0);
    }
}