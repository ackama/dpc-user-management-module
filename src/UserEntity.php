<?php

namespace Drupal\DPC_User_Management;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\Entity\User;
use Drupal\dpc_user_management\Traits\SendsEmailVerificationEmail;

class UserEntity extends User
{
    use SendsEmailVerificationEmail;

    public function preSave(EntityStorageInterface $storage)
    {
        if (!$this->id()) {
            return parent::preSave($storage);
        }

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
            }
            // If a primary email flag has been set then override the mail setting
            if ($address['is_primary']) {
                $this->setEmail($address['value']);
            }
        }

        $this->field_email_addresses->setValue($addresses);

        parent::preSave($storage);
    }
}