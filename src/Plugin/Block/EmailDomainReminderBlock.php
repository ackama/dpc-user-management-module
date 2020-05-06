<?php

namespace Drupal\dpc_user_management\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;

/**
 * Provides a 'Remind user to add a valid email in their account' Block.
 *
 * @Block(
 *   id = "dpc_emaildomain_reminder_block",
 *   admin_label = @Translation("Remind Valid Email"),
 *   category = @Translation("DPC User Management"),
 * )
 */
class EmailDomainReminderBlock extends BlockBase {

    use HandlesEmailDomainGroupMembership;

    /**
     * @var string
     */
    public static $_id = 'dpc_emaildomain_reminder_block';

    /**
     * @var string
     */
    public static $_default_text = 'Add a VPS e-mail address to <a href="%s">your profile</a> in order to have access to the site\'s content';

    /**
     * @var string
     */
    public static $_div_class = 'dpc-user-email-reminder';

    /**
     * {@inheritdoc}
     */
    public function build() {
        // Formats text to include user edit link
        $text = $this->t(sprintf(self::$_default_text, '/user/' . \Drupal::currentUser()->id() . '/edit'));

        return [
            '#markup' => sprintf('<div class"%s">%s</div>', self::$_div_class, $text)
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {
        return !$account->isAnonymous()
            ? AccessResult::allowedIf(!self::isUserInGroups($account))
            : AccessResult::forbidden();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
        $config = $this->getConfiguration();

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
        $this->configuration[self::$_id] = $form_state->getValue(self::$_id);
    }

}
