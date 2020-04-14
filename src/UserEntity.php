<?php

namespace Drupal\dpc_user_management;

use Drupal;
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
        }

        $this->field_email_addresses->setValue($addresses);

        parent::preSave($storage);
    }
}