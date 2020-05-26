<?php

namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\dpc_user_management\Controller\DeletedGroupController;
use Drupal\dpc_user_management\UserEntity as User;
use Drupal\dpc_user_management\GroupEntity as Group;
use Drupal\Tests\BrowserTestBase;

/**
 * @group dpc_user_management
 */
class DeletedGroupTest extends BrowserTestBase
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
     * @var Group[] $groups
     */
    protected $groups;

    /**
     * @var DeletedGroupController
     */
    protected $DeletedGroupController;

    /**
     * @var array
     */
    protected $group_defs = [
            'group_1' => [
                'label' => 'Group 1'
            ],
            'group_2' => [
                'label' => 'Group 2'
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

        $this->DeletedGroupController = new DeletedGroupController();

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
            // Create Group
            $group = Group::create(['type' => User::$group_special_type_id, 'label' => $group['label']]);

            $group->save();

            return $group;
        }, $this->group_defs);
    }

    /**
     * @throws \Drupal\Core\Entity\EntityStorageException
     * @throws \Exception
     */
    public function testGroupIsSavedAsDeleted() {
        // Cache data from group that will be deleted
        $group_id = $this->groups['group_1']->id();
        $group_label = $this->groups['group_1']->label();

        // Delete Group
        $this->groups['group_1']->delete();

        // Get record from Deleted Groups Table with cached group id
        $deleted_group = $this->DeletedGroupController->getRecord($group_id);

        // Assertions
        $this->assertEqual($group_id, $deleted_group->id);
        $this->assertEqual($group_label, $deleted_group->label);
    }
}
