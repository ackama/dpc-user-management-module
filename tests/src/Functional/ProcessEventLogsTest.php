<?php

namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\dpc_user_management\UserEntity as User;
use Drupal\dpc_user_management\GroupEntity as Group;
use Drupal\Tests\BrowserTestBase;

/**
 * @group dpc_user_management
 */
class ProcessEventLogs extends BrowserTestBase
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
     * @var Group $group
     */
    protected $groups;

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
        'group-1' => [
            'label' => 'Group 1',
            'domains' => ['test.net', 'domain.org']
        ],
        'group-2' => [
            'label' => 'Group 2',
            'domains' => ['domain.org', 'example.com']
        ],
        'group-3' => [
            'label' => 'Group 3',
            'domains' => ['example.com', 'test.net']
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
        $this->setUpUsers();

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
        $this->groups = array_map(function ($group) {
            return $this->createGroup($group);
        }, $this->group_defs);
    }

    /**
     * Create a New Group with the specified data
     *
     * @param $data
     * @return \Drupal\Core\Entity\EntityInterface
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function createGroup($data)
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
     * Creates user with passed configuration
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function createUser($data) {
        /** @var User $user */
        $user = $this->drupalCreateUser();

        return $user;
    }

    /**
     * @return array
     */
    public function fakeUsers() {
        return [
            'user1' => [
                'name' => 'User 1',
                'emails' => [
                    'user1@test.net',
                    'user1@example.com'
                ],
            ],
            'user2' => [
                'name' => 'User 2',
                'emails' => [
                    'user2@gmail.com',
                    'user2@domain.org'
                ],
            ]
        ];
    }

    /**
     * Sets Up Users to be used in tests
     */
    public function setUpUsers() {
        $this->users = array_map(function($user){
            return $this->createUser($user);
        }, $this->fakeUsers());
    }

    public function testLogsAreProcessed()
    {
        $this->drupalLogin($this->admin);

        /**
         * @ToDo User 1 Block 1
         * Add Email
         * Add Email2
         * Count logs = 3
         * Process Logs
         * Logs should be all empty
         */

        /**
         * @ToDo User 1 Block 2
         * Add Special Group
         * Remove Email 1
         * Remove Email 2
         * Add Email 3 Invalid
         * Count logs = 4
         * Process Logs
         * Email is Not Sent
         * Logs should be all empty
         */

        /**
         * @ToDo User 1 Block 3
         *
         * Remove Special Group
         * Count log = 2
         * Process Logs
         * Email is Sent
         * Logs should be all empty
         */

        /**
         * @ToDo User 2 Block 1
         *
         * Add Special Group
         * Remove Special Group
         * Add Special Group 2
         * Remove Special Group 2
         * Count log = 6
         * Process Logs
         * Email is not Sent
         * Logs Should empty
         */

        /**
         * @ToDo User 2 Block 2
         *
         * Add Special Group
         * Override Access
         * Remove Special Group
         * Count log = 4
         * Process Logs
         * Email is not Sent
         * Logs Should empty
         */

        /**
         * @ToDo User 2 Block 3
         *
         * Remove Override
         * Override Access
         * Remove Special Group
         * Count log = 4
         * Process Logs
         * Email is not Sent
         * Logs Should empty
         */

        /**
         * @ToDo General Test
         *
         * Add invalid email to Group3
         * Remove domains that user 1 have.
         * Count log ??
         * Process Logs
         * Email is Sent to user 1 not user 2
         * Logs should be empty
         */

    }
}
