<?php

namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\dpc_user_management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group dpc_user_management
 */
class UserSpecialGroupsAnonymousTest extends BrowserTestBase
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
        'dpc_user_management'
    ];

    /**
     * A user with permission to administer site configuration.
     *
     * @var \Drupal\user\UserInterface
     */
    protected $user;

    /**
     * @var Group $group
     */
    protected $group;

    /**
     * @var $special_groups Group[]
     */
    protected $special_groups = [];

    /**
     * @var GroupRole[]
     */
    protected $group_roles;

    /**
     * The group role synchronizer service.
     *
     * @var \Drupal\group\GroupRoleSynchronizer
     */
    protected $roleSynchronizer;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Get Access Group
        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var Group $group */
        $this->group = Group::load(array_pop($group_ids));

        // Create 2 Special Groups
        /** @var Group[] $special_groups */
        $this->special_groups = array_map(function ($id) {
            $group = \Drupal\group\Entity\Group::create([
                'label' => $id,
                'type' => UserEntity::$group_special_type_id
            ]);
            $group->save();

            return $group;
        }, ['group-1', 'group-2']);

        // Gets Role Synchroniser
        $this->roleSynchronizer = $this->container->get('group_role.synchronizer');

        // Get Role Ids that need permissions reassigning
        $role_ids = array_merge(
            $this->roleSynchronizer->getGroupRoleIdsByGroupType('dpc_gtype_special'),
            ['dpc_gtype_special-anonymous', 'dpc_gtype_special-outsider']
        );

        // Grant Necessary Permissions to Group Roles using ids from $role_ids
        $this->group_roles = array_filter(
            array_map(function ($id) {
                /** @var GroupRole $group_role */
                $group_role = GroupRole::load($id);

                if (empty($group_role)) {
                    return null;
                }

                $group_role->grantPermissions(['view group', 'join group']);
                $group_role->save();

                return $group_role;
            }, $role_ids),
            function($value) { return $value; });

        // Goes to Registration Page
        $this->drupalGet('user/register');

    }

    public function testSpecialGroupFieldExists()
    {
        $web_assert = $this->assertSession();

        // Checks for checkbox for group[0] to exist
        $element_id = sprintf('edit-special-groups-%s', $this->special_groups[0]->id());

        $web_assert->fieldExists($element_id);
    }
}
