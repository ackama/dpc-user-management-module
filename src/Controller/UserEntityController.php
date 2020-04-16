<?php
namespace Drupal\DPC_User_Management\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\dpc_user_management\Traits\SendsEmailVerificationEmail;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserEntityController extends ControllerBase
{
    use SendsEmailVerificationEmail;

    /**
     * @param AccountInterface $user
     * @param Request          $request
     *
     * @return array
     * @throws EntityStorageException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     * @throws EntityStorageException
     */
    public function verifyEmail(AccountInterface $user, Request $request)
    {
        $token   = $request->get('token');
        $message = 'Your verification token is invalid.';

        /** @var User $user */
        $user = User::load($user->id());

        /** @var \Drupal\Core\Field\FieldItemList $addresses */
        $addresses = $user->field_email_addresses->getValue();

        if (!empty($addresses)) {
            foreach ($addresses as $key => $address) {
                if (hash_equals($token, Crypt::hashBase64($address['verification_token'] . $user->id() . $address['value']))) {
                    $addresses[$key]['status']             = 'verified';
                    $addresses[$key]['verification_token'] = null;
                    $message                               = 'Thank you for verifying your email address';
                }
            }

            $user->field_email_addresses->setValue($addresses);
            $user->save();
        }

        return [
            '#type'   => 'markup',
            '#markup' => $this->t($message),
        ];
    }

    /**
     * @param AccountInterface $user
     * @param Request          $request
     *
     * @return JsonResponse
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     * @throws EntityStorageException
     */
    public function sendVerification(AccountInterface $user, Request $request)
    {
        $email       = $request->get('email');
        $emailExists = false;

        /** @var User $user */
        $user = User::load($user->id());

        /** @var \Drupal\Core\Field\FieldItemList $addresses */
        $addresses = $user->field_email_addresses->getValue();
        if (!empty($addresses)) {
            foreach ($addresses as $key => $address) {
                if ($address['value'] == $email) {
                    $emailExists                           = true;
                    $token                                 = Crypt::randomBytesBase64(55);
                    $addresses[$key]['verification_token'] = 'pending';
                    $addresses[$key]['verification_token'] = $token;
                }
            }

            if (!$emailExists) {
                return new JsonResponse('Email and user do not match', 404);
            }

            $user->field_email_addresses->setValue($addresses);
            $user->save();
        }

        $this->sendVerificationNotification($email, $token, $user);

        return new JsonResponse('Verification sent!', 200);
    }
}