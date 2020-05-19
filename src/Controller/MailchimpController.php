<?php
namespace Drupal\DPC_User_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\dpc_user_management\Traits\HandlesMailchimpSubscriptions;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use DrewM\MailChimp\MailChimp;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MailchimpController extends ControllerBase
{
    use HandlesMailchimpSubscriptions {
        HandlesMailchimpSubscriptions::__construct as _mc_handler_construct;
    }

    /**
     * @var MailChimp|null
     */
    protected $mailchimp = null;
    /**
     * @var array|mixed|null
     */
    protected $audience_id = null;
    /**
     * @var \Drupal\Core\Config\ImmutableConfig
     */
    protected $config;
    /**
     * @var \DrewM\MailChimp\Batch
     */
    protected $batch;
    /**
     * @var string|null
     */
    protected $context;
    /**
     * @var array
     */
    public $operations_list = [];

    public function __construct($context = null)
    {
        $this->context     = $context;
        try {
            $this->_mc_handler_construct();
        } catch (\Exception $e) {
        }
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws HttpException
     */
    public function syncAudience(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $this->context !== 'cron') {
            throw new HttpException(400, 'Invalid request');
        }

        if (!$this->mailchimpConnected()) {
            return new JsonResponse('Unable to Sync users: Mailchimp configuration is missing', 400);
        }

        // TODO: logging
        $this->updateMembersInList();
        $this->addMissingUsersToList();

        if ($this->batch->get_operations()) {
            $this->batch->execute();

            $this->operations_list += array_map(function($item) {
                $operation = json_decode($item['body']);

                return "$operation->email_address will be $operation->status";
            }, $this->batch->get_operations());
        }

        if (!empty($this->operations_list)) {

            return new JsonResponse($this->operations_list);
        }

        return new JsonResponse('There are no updates to make.');
    }

    /**
     * Remove list members who are no longer eligible in drupal
     */
    private function updateMembersInList()
    {
        $member_list = $this->mailchimp->get('lists/' . $this->audience_id . '/members');

        foreach ($member_list['members'] as $member) {
            $email = $member['email_address'];
            $query = \Drupal::entityQuery('user');
            $query->Condition('field_email_addresses', $email, '=');
            $user_id = $query->execute();
            /** @var EntityInterface $user */
            if (empty($user_id)) {
                continue;
            }

            $user = User::load(array_shift($user_id));

            // check if user is unsubscribed
            if ($member['status'] === 'unsubscribed') {
                if ($user) {
                    // set user setting
                    // TODO: update user profile setting
                }

                continue;
            }

            // unsubscribe the member is the user is not found
            if (!$user) {
                $this->batchUnsubscribe($user, $email);

                continue;
            }

            // check if email is the primary email
            $user_emails      = $user->field_email_addresses->getValue();
            $subscribed_email = $user_emails[array_search($email, array_column($user_emails, 'value'))];
            if ($subscribed_email['is_primary']) {
                continue;
            }

            // if the email is not the primary, update the member
            $primary = $user_emails[array_search(true, array_column($user_emails, 'is_primary'))];
            // unless it is not yet verfied
            if ($primary['status'] !== 'verified') {
                continue;
            }

            $this->batchUnsubscribe($user, $email);
        };
    }

    private function addMissingUsersToList()
    {
        $query = \Drupal::entityQuery('user');
        $query->Condition('status', 1, '=');
        $user_ids = $query->execute();
        foreach ($user_ids as $id) {
            $mc_address = \Drupal::service('user.data')->get('dpc_user_management', $id, 'mc_subscribed_email');
            if (!$mc_address) {
                $user = User::load($id);
                if ($user->hasAccess()) {
                    $this->batchSubscribe($user);
                }
            }
        }
    }
}