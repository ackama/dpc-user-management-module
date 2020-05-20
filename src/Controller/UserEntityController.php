<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\dpc_user_management\Plugin\QueueWorker\GroupMembershipUpdateTask;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
use Drupal\dpc_user_management\Traits\SendsEmailVerificationEmail;
use Drupal\dpc_user_management\UserEntity as User;
use Drupal\group\Entity\Group;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserEntityController extends ControllerBase
{
    use SendsEmailVerificationEmail, HandlesEmailDomainGroupMembership;

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
                    // Add user to groups based on email domain
                    self::addUserToGroups($user, $address['value']);
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

    /**
     * @param $data
     * @throws \Exception
     */
    public function processGroupMemberships($data)
    {
        /** @var Group $group */
        $group_id = $data['group'];
        $group =  Group::load($group_id);

        // check if existing users can be added to the group
        $query = \Drupal::entityQuery('user');

        $domains = $group->field_email_domain->getValue();

        $field_email_orgroup = $query->orConditionGroup();
        $mail_orgroup = $query->orConditionGroup();
        $base_orgroup = $query->orConditionGroup();

        foreach ($domains as $domain) {
            $field_email_orgroup->condition('field_email_addresses', '%' . $domain['value'], 'like');
            $mail_orgroup->condition('mail', '%' . $domain['value'], 'like');
        }

        $base_orgroup->condition($field_email_orgroup);
        $base_orgroup->condition($mail_orgroup);
        $query->condition($base_orgroup);

        $uids = $query->execute();

        $users = \Drupal\user\Entity\User::loadMultiple($uids);
        $domains = array_column($domains, 'value');

        foreach ($users as $user) {
            if ($group->getMember($user)) {
                continue;
            }

            $addresses = $user->field_email_addresses->getValue();
            if (empty($addresses)) {
                self::addUserToGroup($user, $group);

                continue;
            }
            foreach ($addresses as $key => $email) {
                if (in_array(explode('@', $email['value'])[1], $domains) && $email['status'] === 'verified') {
                    self::addUserToGroup($user, $group);
                }
            }
        }
    }

    /**
     * Runs the group_membership_update_task queue
     */
    public function runGroupMembershipUpdateTask() {
        $_queue_name = 'group_membership_update_task';
        $_queue = \Drupal::queue($_queue_name, true);
        /** @var GroupMembershipUpdateTask $_queue_worker */
        $_queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($_queue_name);

        while($_queue->numberOfItems()){
            $item = $_queue->claimItem();
            try{
                $_queue_worker->processItem($item->data);
            } catch (\Exception $e) {
                $_queue->releaseItem($item);
            }
        }
    }
}
