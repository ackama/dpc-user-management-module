<?php
namespace Drupal\DPC_User_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use DrewM\MailChimp\MailChimp;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MailchimpController extends ControllerBase
{
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

    public function __construct($context = null)
    {
        $this->config      = \Drupal::config('dpc_mailchimp.settings');
        $this->audience_id = $this->config->get('audience_id');
        $this->context     = $context;
        try {
            $this->mailchimp = new MailChimp($this->config->get('api_key'));
            $this->batch     = $this->mailchimp->new_batch();
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

        if (!$this->mailchimp || !$this->audience_id) {
            return new JsonResponse('Unable to Sync users: Mailchimp configuration is missing', 400);
        }

        // TODO: logging
        $this->updateMembersInList();

        if ($this->batch->get_operations()) {
            $this->batch->execute();
        }

        return new JsonResponse('Syncing has been processed', 200);
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
                $this->batch->put('unsub' . $member['id'], "lists/$this->audience_id/members/" . $member['id'], [
                    'status' => 'unsubscribed',
                ]);

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
            $this->batch->put('unsub' . $member['id'], "lists/$this->audience_id/members/" . $member['id'], [
                'email_address' => $primary['value'],
            ]);
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
            }
        }
    }
}