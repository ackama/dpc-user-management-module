<?php

namespace Drupal\DPC_User_Management;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\user\Entity\User;
use Drupal\dpc_user_management\Traits\SendsEmailVerificationEmail;

class UserEntity extends User
{
    use SendsEmailVerificationEmail;

    public function preSave(EntityStorageInterface $storage)
    {
        if ($this->isNew()) {
            return parent::preSave($storage);
        }
        $verification_sent = [];
        $user = User::load($this->id());
        // Check email addresses
        $addresses = $this->field_email_addresses->getValue();
        foreach ($addresses as $key => $address) {
            // If there is no status assume this is new, send a verification email
            if (empty($address['status'])) {
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
        }

        $this->field_email_addresses->setValue($addresses);
        if (!empty($verification_sent)) {
            \Drupal::messenger()->addMessage(t('A verification email was sent to ' . implode(',', $verification_sent)));
        }
        parent::preSave($storage);
    }

    protected function special_group() {

        // Adds JSE Access when Special Group flag is turned on
        $_original = $this->original->get('special_group')->getValue()[0]['value'];
        $_new = $this->get('special_group')->getValue()[0]['value'];

        if ($_original !== $_new) {
            // Set access flag to true only if
            if($_new === 1) {
                $this->set('jse_access', 1);
            }

            // Toggles user access to content group
            /** @var Group $group */
            $group =  \Drupal::entityQuery('group')
                ->condition('type', 'dpc_module_group_type')
                ->accessCheck(false)
                ->execute();

            if($this->get('jse_access')->getValue()[0]['value']) {
                $group->addMember($this);
            } else {
                $group->removeMember($this);
            }
        }
    }
}
