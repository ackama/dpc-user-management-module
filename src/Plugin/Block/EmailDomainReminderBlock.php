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

    protected static $_id = 'dpc_emaildomain_reminder_block';

    use HandlesEmailDomainGroupMembership;

    /**
     * {@inheritdoc}
     */
    public function build() {
        return [
            '#markup' => $this->t('Remind this!'),
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
