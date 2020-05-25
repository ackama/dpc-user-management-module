<?php

namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\dpc_user_management\Controller\EventsLogController;
use Drupal\dpc_user_management\UserEntity as User;
use Drupal\dpc_user_management\GroupEntity as Group;
use Drupal\Tests\BrowserTestBase;

/**
 * @group dpc_user_management
 */
class ProcessEventLogsTest extends BrowserTestBase
{
    use AssertMailTrait {
        getMails as drupalGetMails;
    }

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
        'dpc_user_management'
    ];

    /**
     * @var User $admin
     */
    protected $admin;

    /**
     * @var User[] $users
     */
    protected $users;

    /**
     * @var Group[] $mail_groups
     */
    protected $mail_groups;

    /**
     * @var Group[] $special_groups
     */
    protected $special_groups;

    /**
     * @var EventsLogController
     */
    protected $EventsLog;

    /**
     * @var array
     */
    protected $valid_domains = [
        'test.net',
        'domain.org',
        'example.com'
    ];

    /**
     * @var array
     */
    protected $invalid_domains = [
        'gmail.com'
    ];

    /**
     * @var array
     */
    protected $group_defs = [
        'mail' => [
            'group_1' => [
                'label' => 'Group 1',
                'domains' => ['test.net', 'domain.org']
            ],
            'group_2' => [
                'label' => 'Group 2',
                'domains' => ['domain.org', 'dummy.net']
            ],
            'group_3' => [
                'label' => 'Group 3',
                'domains' => ['dummy.net', 'test.net']
            ]
        ],
        'special' => [
            'special_group_1' => [
                'label' => 'Special Group 1'
            ],
            'special_group_2' => [
                'label' => 'Special Group 2'
            ]
        ]
    ];

    /**
     * @var array
     */
    protected $user_defs = [
        'user1' => [
            'name' => 'lorenzo.llamas',
            'emails' => [
                'lorenzo.llamas@test.net',
                'lorenzo.llamas@dummy.net'
            ],
            'invalid_emails' => [
                'lorenzo.llamas@gmail.com',
                'lorenzo.llamas@yahoo.com',
            ]
        ],
        'user2' => [
            'name' => 'dalai.llama',
            'emails' => [
                'dalai.llama@dummy.net',
                'dalai.llama@domain.org'
            ],
            'invalid_emails' => [
                'dalai.llams@gmail.com',
                'dalai.llam@yahoo.com',
            ]
        ]
    ];

    /**
     * {@inheritdoc}
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function setUp()
    {
        parent::setUp();

        $this->setUpGroups();
        $this->setupTestUsers();

        $this->EventsLog = new EventsLogController();

        // Setup Admin User
        $this->admin = $this->drupalCreateUser([
            'administer users',
            'administer node fields',
            'administer site configuration',
            'access administration pages'
        ]);
    }

    /**
     * Sets up all groups for test
     */
    protected function setUpGroups()
    {
        $this->mail_groups = array_map(function ($group) {
            return $this->createMailTestGroup($group);
        }, $this->group_defs['mail']);

        $this->special_groups = array_map(function ($group) {
            return $this->createSpecialTestGroup($group);
        }, $this->group_defs['special']);
    }

    /**
     * Create a New Mail Group with the specified data
     *
     * @param $data
     * @return \Drupal\Core\Entity\EntityInterface
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function createMailTestGroup($data)
    {
        // Create Group
        $group = Group::create(['type' => User::$group_type_email_domain_id, 'label' => $data['label']]);

        // Set Valid Domains for group
        $group->set('field_email_domain', array_map(function ($domain) {
            return ['value' => $domain];
        }, $data['domains']));

        $group->save();

        return $group;
    }

    /**
     * Create a New Special Group with the specified data
     *
     * @param $data
     * @return \Drupal\Core\Entity\EntityInterface
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function createSpecialTestGroup($data)
    {
        // Create Group
        $group = Group::create(['type' => User::$group_special_type_id, 'label' => $data['label']]);

        $group->save();

        return $group;
    }

    /**
     * Creates user with passed configuration
     * @param $data
     * @return User
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function createTestUser($data) {
        /** @var User $user */
        $user = $this->drupalCreateUser([], $data['name'], false);

        $user->addEmailAndVerify($user->getEmail());

        $user->makeEmailPrimary($user->getEmail());
        $user->save();

        $this->drupalLogin($user);

        return $user;
    }

    /**
     * @return array
     */
    public function fakeTestUsersSeed() {
        return $this->user_defs;
    }

    /**
     * Sets Up Users to be used in tests
     */
    public function setupTestUsers() {
        $this->users = array_map(function($user){
            return $this->createTestUser($user);
        }, $this->fakeTestUsersSeed());
    }

    /**
     * @throws \Exception
     */
    public function testLogsAreProcessed()
    {
        $this->drupalLogin($this->admin);

        /**
         * @ToDo User 1 Block 1
         * Add Email[0] (Group 1) (Master Group)
         * Add Email[1] (Group 2) (Group 3)
         * Count logs = 4
         * Process Logs
         *  total 4
         *  users 1
         *  queued 0
         * Count logs = 0
         */

        $this->users['user1']->addEmailAndVerify($this->fakeTestUsersSeed()['user1']['emails'][0]);
        $this->users['user1']->addEmailAndVerify($this->fakeTestUsersSeed()['user1']['emails'][1]);

        $this->assertCount(4, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 4,
            'users' => 1,
            'queued' => 0
        ], $this->EventsLog->processUnprocessedRecords());

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());

        /**
         * @ToDo User 1 Block 2
         * Add Special Group
         * Remove Email[0] (- Group 1)
         * Remove Email[1] (- Group 2) (- Group 3)
         * Add Email Invalid[0] Invalid (No Group)
         * Count logs 4
         * Process Logs
         *  total 4
         *  users 1
         *  queued 0
         * Count logs 0
         */

        $this->users['user1']->set('special_groups', [['target_id' => $this->special_groups['special_group_1']->id()]]);
        $this->users['user1']->save();
        $this->users['user1']->removeEmailAddress($this->fakeTestUsersSeed()['user1']['emails'][0]);
        $this->users['user1']->save();
        $this->users['user1']->removeEmailAddress($this->fakeTestUsersSeed()['user1']['emails'][1]);
        $this->users['user1']->save();
        $this->users['user1']->addEmailAndVerify($this->fakeTestUsersSeed()['user1']['invalid_emails'][0]);
        $this->users['user1']->save();

        $this->assertCount(4, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 4,
            'users' => 1,
            'queued' => 0
        ], $this->EventsLog->processUnprocessedRecords());

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());

        /**
         * @ToDo User 1 Block 3
         *
         * Remove Special Group
         * Count log = 2
         * Process Logs
         *  total 2
         *  user 1
         *  queued 1
         * Count Log = 0
         */

        // We set this to empty because we know the user only has one group from before
        $this->users['user1']->set('special_groups', []);
        $this->users['user1']->save();

        $this->assertCount(2, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 2,
            'users' => 1,
            'queued' => 1
        ], $this->EventsLog->processUnprocessedRecords());

        // Delete Items from Queue for the lack of a better option
        $this->EventsLog->queue()->deleteQueue();

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());

        /**
         * @ToDo User 2 Block 1
         *
         * Add Special Group 1
         * Remove Special Group 1
         * Add Special Group 2
         * Remove Special Group 2
         * Count log = 8
         * Process Logs
         *  total 8
         *  users 1
         *  queued 0
         * Count log 0
         */

        $this->users['user2']->set('special_groups', [['target_id' => $this->special_groups['special_group_1']->id()]]);
        $this->users['user2']->save();
        // We set this to empty because we know the user only has one group
        $this->users['user2']->set('special_groups', []);
        $this->users['user2']->save();

        $this->users['user2']->set('special_groups', [['target_id' => $this->special_groups['special_group_2']->id()]]);
        $this->users['user2']->save();
        // We set this to empty because we know the user only has one group
        $this->users['user2']->set('special_groups', []);
        $this->users['user2']->save();

        $this->assertCount(8, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 8,
            'users' => 1,
            'queued' => 0
        ], $this->EventsLog->processUnprocessedRecords());

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());

        /**
         * @ToDo User 2 Block 2
         *
         * Add Special Group (Group + Master Group)
         * Override Access (+ Secures Master Group)
         * Remove Special Group (- Group)
         * Count log = 3
         * Process Logs
         *  total 3
         *  users 1
         *  queued 0
         * Count log 0
         */

        $this->users['user2']->set('special_groups', [['target_id' => $this->special_groups['special_group_1']->id()]]);
        $this->users['user2']->save();

        $this->users['user2']->set('jse_access', [['value' => 1]]);
        $this->users['user2']->save();

        // We set this to empty because we know the user only has one group
        $this->users['user2']->set('special_groups', []);
        $this->users['user2']->save();

        $this->assertCount(3, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 3,
            'users' => 1,
            'queued' => 0
        ], $this->EventsLog->processUnprocessedRecords());

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());

    }

    /**
     * @throws \Drupal\Core\Entity\EntityStorageException
     * @throws \Exception
     */
    public function testMultipleUsers() {

        /**
         * @ToDo All Users Block 1
         *
         * Add All Emails[0] (Group 1) (Group 2) (Group 3) (Group Master) x 2
         * Add invalid domain email to User 2
         * Count logs = 8
         * Process Logs
         *  total 8
         *  users 2
         *  queued 0
         * Count logs = 0
         */

        $this->users['user1']->addEmailAndVerify($this->fakeTestUsersSeed()['user1']['emails'][0]);
        $this->users['user1']->addEmailAndVerify($this->fakeTestUsersSeed()['user1']['emails'][1]);
        $this->users['user2']->addEmailAndVerify($this->fakeTestUsersSeed()['user2']['emails'][0]);
        $this->users['user2']->addEmailAndVerify($this->fakeTestUsersSeed()['user2']['emails'][1]);
        $this->users['user2']->addEmailAndVerify($this->fakeTestUsersSeed()['user2']['invalid_emails'][0]);

        $this->assertCount(8, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 8,
            'users' => 2,
            'queued' => 0
        ], $this->EventsLog->processUnprocessedRecords());

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());

        /**
         * @ToDo All Users Block 2
         *
         * Add invalid domain to Group3 (User 2 maintains)
         * Remove all domains from groups (User1 = -3 Groups, User2 = -2 Groups, User 1= -1 Master Group)
         * Count log = 6
         * Process Logs
         *  total 6
         *  users 2
         *  queued 1
         * Count log 0
         */

        $this->mail_groups['group_1']->set('field_email_domain', []);
        $this->mail_groups['group_1']->save();
        $this->mail_groups['group_2']->set('field_email_domain', []);
        $this->mail_groups['group_2']->save();
        $this->mail_groups['group_3']->set('field_email_domain', [['value' => 'gmail.com']]);
        $this->mail_groups['group_3']->save();

        $this->assertCount(6, $this->EventsLog->getUnprocessedRecords());

        $this->assertEqual([
            'total' => 6,
            'users' => 2,
            'queued' => 1
        ], $this->EventsLog->processUnprocessedRecords());

        $this->assertCount(0, $this->EventsLog->getUnprocessedRecords());
    }
}
