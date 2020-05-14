<?php

namespace Drupal\dpc_user_management\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
use Drupal\dpc_user_management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\Core\Block\BlockPluginInterface;

/**
 * Provides a 'Remind user to add a valid email in their account' Block.
 *
 * @Block(
 *   id = "dpc_access_reminder_block",
 *   admin_label = @Translation("Remind Valid Email"),
 *   category = @Translation("DPC User Management"),
 * )
 */
class AccessReminderBlock extends BlockBase implements BlockPluginInterface{

    use HandlesEmailDomainGroupMembership;

    /**
     * @var string
     */
    public static $_id = 'dpc_access_reminder_block';

    /**
     * @var string
     */
    public static $_default_text = 'Add a VPS e-mail address to <a href="%s">your profile</a> in order to have access to the site\'s content';

    /**
     * @var string
     */
    public static $_div_id = 'dpc-user-email-reminder';

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
            '#markup' => sprintf('<div id="%s" class="%s">%s</div>', self::$_div_id, self::$_div_class, $text),
            '#attached' => [
                'library' => [
                    'dpc_user_management/user_frontend',
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {
        // @ToDo Possibly refactor after merging with PR https://github.com/ackama/dpc-user-management-module/pull/32
        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var Group $group */
        $group = Group::load(array_pop($group_ids));

        return !$account->isAnonymous()
            ? AccessResult::allowedIf(!$group->getMember($account))
            : AccessResult::forbidden();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
        $form = parent::blockForm($form, $form_state);

        $config = $this->getConfiguration();

        $form['access_reminder_block'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Reminder Text'),
            '#description' => $this->t('Text that users who don\'t yet have access to the content will see. Use <b>%s</b> to insert the user\'s profile link. ie. <a href="%s">My Profile</a>'),
            '#default_value' => isset($config[self::$_id]) ? $config[self::$_id] : self::$_default_text,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
        parent::blockSubmit($form, $form_state);
        $values = $form_state->getValues();
        $this->configuration[self::$_id] = $form_state->getValue(self::$_id);
    }
}
