<?php

namespace Drupal\DPC_User_Management;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\user\Entity\User;
use Drupal\dpc_user_management\Traits\SendsEmailVerificationEmail;

class UserEntity extends User
{
    use SendsEmailVerificationEmail, HandlesEmailDomainGroupMembership;

    /**
     * Defines the Group Id
     *
     * @var string
     */
    public static $group_id = 'dpc_group_grant_access';

    /**
     * Defines the Group Label
     *
     * @var string
     */
    public static $group_label = 'DPC Managed - Grant Access Group';

    /**
     * Defines the Group Type ID
     *
     * @var string
     */
    public static $group_type_id = 'dpc_managed_group_type';

    /**
     * Defines the Group Type Label
     *
     * @var string
     */
    public static $group_type_label = 'DPC Managed Group Type';

    /**
     * Special Group Type ID.
     * Initialised in ./config/install/group.type.dpc_gtype_special.yml
     *
     * @var string
     */
    public static $group_special_type_id = 'dpc_gtype_special';

    /**
     * Special Group Type Label.
     * Initialised in ./config/install/group.type.dpc_gtype_special.yml
     *
     * @var string
     */
    public static $group_special_type_label = 'DPC Managed - Special Groups';

    /**
     * @param EntityStorageInterface $storage
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     * @throws \Exception
     */
    public function preSave(EntityStorageInterface $storage)
    {
        if ($this->isNew()) {
            return parent::preSave($storage);
        }

        $this->verify_email_addresses();
        $this->toggle_special_groups();

        parent::preSave($storage);
    }

    /**
     * Verifies Email Addresses
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     */
    protected function verify_email_addresses()
    {
        $verification_sent = [];
        $user = User::load($this->id());
        // Check email addresses
        $addresses = $this->getDirtyAddresses();
        foreach ($addresses as $key => $address) {
            // If there is no status assume this is new, send a verification email
            if (empty($address['status']) || $address['status'] === 'new') {
                $token      = Crypt::randomBytesBase64(55);
                $email      = $address['value'];
                $this->sendVerificationNotification($email, $token, $user);
                $addresses[$key]['status']             = 'pending';
                $addresses[$key]['verification_token'] = $token;
                $verification_sent[] = $email;
            }
            // If a primary email flag has been set then override the mail setting
            if ($address['is_primary']) {
                $this->setEmail($address['value']);
            }

            // If a users email is unverified maybe remove the user from groups
            if ($address['status'] == 'unverified') {
                self::removeUsersFromGroups($this, [$address['value']]);
            }
        }

        $this->field_email_addresses->setValue($addresses);
        if (!empty($verification_sent)) {
            \Drupal::messenger()->addMessage(t('A verification email was sent to ' . implode(',', $verification_sent)));
        }
    }

    /**
     * Because drupal can't handle adding default values to old records (ie existing users)
     * when creating these fields, we need to check for unset values first
     * We use this helper function to keep things DRY
     *
     * @param string $field_name
     * @param bool $original
     * @return bool
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function _get_clean_boolean($field_name, $original = false)
    {
        $value = !$original ? $this->get($field_name)->getValue() : $this->original->get($field_name)->getValue();

        return empty($value) ? false : (bool) $value[0]['value'];
    }

    /**
     * Returns arrays with id's from multiple checkboxes field type in drupal forms
     *
     * @param $field_name
     * @param bool $original
     * @return array
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function _get_target_ids($field_name, $original = false)
    {
        $values = !$original ? $this->get($field_name)->getValue() : $this->original->get($field_name)->getValue();

        return array_map(function ($v) {
            return $v['target_id'];
        }, $values);
    }

    /**
     * Adds or Removes membership of users into access group bases on profile checkboxes
     *
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function toggle_special_groups()
    {
        // Adds JSE Access when Special Groups selections are changed and a group is selected
        $_new = $this->_get_target_ids('special_groups');
        $_original = $this->_get_target_ids('special_groups', true);

        if (!empty(array_diff($_original, $_new))) {
            // Set access flag to true only if setting has changed
            if(!empty($_new)) {
                $this->set('jse_access', true);
            }
        }

        $_access_new = $this->_get_clean_boolean('jse_access');
        $_access_original = $this->_get_clean_boolean('jse_access', true);

        if($_access_original !== $_access_new) {
            // Toggles user access to content group
            $group_ids =  \Drupal::entityQuery('group')
                ->condition('label', UserEntity::$group_label)
                ->accessCheck(false)
                ->execute();

            /** @var Group $group */
            $group = Group::load(array_pop($group_ids));

            if( $this->_get_clean_boolean('jse_access') ) {
                $group->addMember($this);

                return;
            }

            $group->removeMember($this);

            return;
        }
    }

    /**
     * @return mixed
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function getDirtyAddresses()
    {
        $user = \Drupal::entityManager()
            ->getStorage('user')
            ->loadUnchanged($this->id());
        $addresses = $user->field_email_addresses->getValue();
        $new_addresses = $this->field_email_addresses->getValue();
        foreach ($new_addresses as $key => $address) {
            if (array_search($address['value'], array_column($addresses, 'value')) === false) {
                $new_addresses[$key]['status'] = 'new';
            };
        }

        return $new_addresses;
    }
}
