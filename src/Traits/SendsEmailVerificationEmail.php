<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal;
use Drupal\Component\Utility\Crypt;
use Drupal\dpc_user_management\UserEntity as User;

trait SendsEmailVerificationEmail {
    /**
     * @param string $to
     * @param string $token
     * @param User   $user
     */
    function sendVerificationNotification($to, $token, $user)
    {
        $token_hash = Crypt::hashBase64($token . $user->id() . $to);

        $mailManager = Drupal::service('plugin.manager.mail');
        $id = $user->id();
        $message = "Please click the follow this link to verify this email address: ";
        $message .= Drupal::request()->getSchemeAndHttpHost() . "/verify-email/$id/?token=$token_hash";
        $params['context']['subject'] = "Email verification";
        $params['context']['message'] = $message;
        $langcode = $user->getPreferredLangcode();

        $mailManager->mail('system', 'mail', $to, $langcode, $params);
    }
}
