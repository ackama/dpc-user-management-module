<?php

namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\dpc_user_management\Controller\MailchimpController;
use Drupal\dpc_user_management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\Tests\BrowserTestBase;

/**
 * @group dpc_mailchimp
 */
class MailchimpControllerTest extends BrowserTestBase
{
    const TESTING_DEFAULT_THEME = 'classy';
    const CORE_TESTING_DEFAULT_THEME = 'stark';

    /**
     * {@inheritdoc}
     */
    protected $profile = 'standard';

    /**
     * {@inheritdoc}
     */
    protected $defaultTheme = 'stark';

    /**
     * Modules to enable.
     *
     * @var array
     */
    public static $modules = [
        'system',
        'user',
        'node',
        'file',
        'field',
        'field_ui',
        'field_test',
        'views',
        'views_ui',
        'group',
        'dpc_user_management'
    ];

    /**
     * @var \Drupal\user\Entity\User $user1
     * @var \Drupal\user\Entity\User $user2
     * @var \Drupal\user\Entity\User $user3
     */
    protected $user1, $user2, $user3;
    /**
     * @var Group $group
     */
    protected $group;
    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $mailChimpApi;
    /**
     * @var MailchimpController
     */
    protected $controller;

    protected $audeince_id = '12345';
    protected $api_key = 'abc123';

    protected function setUp()
    {
        parent::setUp();
        $group_domains = [
            ['value' => 'test.net'],
        ];

        $this->group = Group::create(['type' => UserEntity::$group_type_email_domain_id, 'label' => 'email domain group']);
        $this->group->set('field_email_domain', $group_domains);
        $this->group->save();

        /**
         * User1 is part of the access group, has a verified email
         * and will be subscribed to the mc audience
         */
        $this->user1 = $this->drupalCreateUser(['administer users', 'administer node fields'], 'user1', false, ['mail' => 'user1@test.net']);
        $this->user1->addToGroup($this->group);
        $this->user1->field_email_addresses->setValue([
            [
                'value'=> 'user1@test.net',
                'status' => 'verified',
                'is_primary' => true
            ]
        ]);
        $this->user1->save();
        /**
         * User2 is part of the audience and has updated their
         * primary email. Email should get updated in mc
         */
        $this->user2 = $this->drupalCreateUser(['administer users', 'administer node fields'], 'user3', false, ['mail' => 'user2@newemail.com']);
        $this->user2->addToGroup($this->group);
        $this->user2->field_email_addresses->setValue([
            [
                'value'=> 'user2@newemail.com',
                'status' => 'verified',
                'is_primary' => true
            ],
            [
                'value'=> 'user2@subscribedmail.com',
                'status' => 'verified',
                'is_primary' => false
            ],
        ]);
        $this->user2->save();

        $this->mailChimpApi = new FakeMailchimp($this->api_key);
        $this->controller   = new MailchimpController(null, $this->mailChimpApi, $this->audeince_id);

    }

    public function testUpdateMembersInList()
    {
        $this->controller->updateMembersInList();
        $action_log = $this->mailChimpApi->getLog();

        // assert user2 email is updated
        $this->assertArrayHasKey('update', $action_log);
        $this->assertEquals($this->user2->getEmail(), $action_log['update']['email_address']);

        // assert mc list member is unsubscribed after not being found in drupal
        $this->assertArrayHasKey('batch_unsubscribed', $action_log);
        $this->assertEquals("lists/$this->audeince_id/members/nonexistentuser@test.com", $action_log['batch_unsubscribed']);
    }

    public function testAddMissingUsersToList()
    {
        $this->controller->addMissingUsersToList();
        $action_log = $this->mailChimpApi->getLog();

        $this->assertArrayHasKey('batch_subscribed', $action_log);
        $this->assertEquals($this->user1->getEmail(), $action_log['batch_subscribed'][0]);
    }
}


class FakeMailchimp
{
    public $log = [];
    public $batch;
    public function __construct($api_key)
    {
        return $this;
    }

    public function get($url = '')
    {
        return [
            'members' => [
                [
                    'email_address' => 'nonexistentuser@test.com',
                    'status'        => 'subscribed'
                ],
                [
                    'email_address' => 'user2@subscribedmail.com',
                    'status'        => 'subscribed'
                ],
            ],
            'total_items' => 2
        ];
    }

    public function new_batch() {
        $this->batch = new FakeMailchimpBatch($this->log);
        return $this->batch;
    }

    public function put($method, $args) {
        $this->log['update'] = $args;
    }

    public function patch($method, $args) {
        $this->log['update'] = $args;
    }
    public function getLog()
    {
        return $this->log + $this->batch->log;
    }

    public static function subscriberHash($email)
    {
        return $email;
    }
}

class FakeMailchimpBatch
{
    public $log;
    public function __construct($log)
    {
        $this->log = $log;
    }

    public function put($batch_id, $method, $args)
    {
        $this->log['batch_' . $batch_id] = $method;
    }

    public function post($batch_id, $method, $args)
    {
        $this->log['batch_' . $batch_id][] = $args['email_address'];
    }
}
