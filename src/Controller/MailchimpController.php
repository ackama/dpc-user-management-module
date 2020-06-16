<?php
namespace Drupal\dpc_user_management\Controller;

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
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var array
     */
    public $operations_list = [];

    public function __construct($context = null, $mailchimp = null, $audience_id = null)
    {
        $this->context = $context;
        $this->logger  = \Drupal::logger('dpc_user_management');
        try {
            $this->_mc_handler_construct($mailchimp, $audience_id);
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

        $this->logger->info('Start MailChimp audience sync');
        $this->updateMembersInList();
        $this->addMissingUsersToList();

        if ($this->batch->get_operations()) {
            $this->batch->execute();

            $this->operations_list += array_map(function ($item) {
                $operation = json_decode($item['body']);

                if (!isset($operation->email_address) || !isset($operation->status)) {
                    return null;
                }

                return "$operation->email_address will be $operation->status" . (isset($operation->reason) ? " " . $operation->reason : "");

            }, $this->batch->get_operations());
        }

        if (!empty($this->operations_list)) {
            foreach ($this->operations_list as $operation) {
                $this->logger->info($operation, ['@label' => 'MailChimp Sync']);
            }

            $this->logger->info('End MailChimp audience sync');
            return new JsonResponse($this->operations_list);
        }

        $this->logger->info('End MailChimp audience sync');
        return new JsonResponse('There are no updates to make.');
    }

    /**
     * Remove list members who are no longer eligible in drupal
     */
    public function updateMembersInList()
    {
        $member_list = $this->mailchimp->get('lists/' . $this->audience_id . '/members');

        foreach ($member_list['members'] as $member) {
            $email = $member['email_address'];
            $query = \Drupal::entityQuery('user');
            $query->Condition('field_email_addresses', $email, '=');

            $user_id = $query->execute();
            $user_id = array_shift($user_id);
            /** @var EntityInterface $user */
            $user = User::load($user_id);

            // unsubscribe the member is the user is not found
            if (!$user_id) {
                $user = user_load_by_mail($email);
                if (!$user) {
                    $this->batchUnsubscribe($email, 'the user was not found in Drupal.');

                    continue;
                }
            }

            // check if user is unsubscribed
            if ($member['status'] === 'unsubscribed') {
                if ($user) {
                    // update the user field
                    if ( $user->field_mailchimp_audience_status->getValue() && $user->field_mailchimp_audience_status->getValue()[0]['value'] !== 'unsubscribed') {
                        $user->field_mailchimp_audience_status->setValue('unsubscribed');
                        $this->operations_list[] = $user->getDisplayName() . ' has unsubscribed.';
                        $user->save();
                    }
                }

                continue;
            }

            // check if email is the primary email
            $user_emails      = $user->field_email_addresses->getValue();
            $subscribed_email = $user_emails[array_search($email, array_column($user_emails, 'value'))];
            if (!$subscribed_email['is_primary']) {
                // if the email is not the primary, update the member
                $primary = $user_emails[array_search(true, array_column($user_emails, 'is_primary'))];
                // unless it is not yet verfied
                if ($primary['status'] !== 'verified') {

                    continue;
                }
                $this->operations_list[] = $user->getDisplayName() . ' has updated their primary email, subscribed email will be updated.';
                $this->updateEmail($user, $subscribed_email['value'], $primary['value']);

                continue;
            }
        };
    }

    /**
     * Add missing users to the list
     */
    public function addMissingUsersToList()
    {
        $query = \Drupal::entityQuery('user');
        $query->Condition('status', 1, '=');
        $user_ids = $query->execute();
        foreach ($user_ids as $id) {
            $mc_address = \Drupal::service('user.data')->get('dpc_user_management', $id, 'mc_subscribed_email');
            if (!$mc_address) {
                $user      = User::load($id);
                if ($user->hasGroupContentAccess() && $user->field_mailchimp_audience_status->getValue() &&
                    $user->field_mailchimp_audience_status->getValue()[0]['value'] !== 'unsubscribed') {
                    $this->batchSubscribe($user);
                }
            }
        }
    }
}