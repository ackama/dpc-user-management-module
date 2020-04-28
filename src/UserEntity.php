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
        }

        $this->field_email_addresses->setValue($addresses);
        if (!empty($verification_sent)) {
            \Drupal::messenger()->addMessage(t('A verification email was sent to ' . implode(',', $verification_sent)));
        }
        parent::preSave($storage);
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
        foreach($new_addresses as $key => $address) {
            if (array_search($address['value'], array_column($addresses, 'value')) === false) {
                $new_addresses[$key]['status'] = 'new';
            };
        }

        return $new_addresses;
    }
}