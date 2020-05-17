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
 *   admin_label = @Translation("Access Reminder Block"),
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
     * @var string
     */
    public $field_id_content = 'content';

    /**
     * {@inheritdoc}
     */
    public function build() {

        $config = $this->getConfiguration();

        $content = !empty($config[$this->field_id_content]) ? $config[$this->field_id_content] : self::$_default_text;

        // Formats text to include user edit link
        $text = $this->t(sprintf($content, '/user/' . \Drupal::currentUser()->id() . '/edit'));

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

        $form[$this->field_id_content] = [
            '#type' => 'textfield',
            '#title' => $this->t('Text for Access Reminder'),
            '#description' => $this->t('Text that users who don\'t yet have access to the content will see. Use <b>%s</b> to insert the current user\'s profile link. ie. &lt;a href="%s"&gt;Link to Profile&lt;/a&gt;.'),
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
}
