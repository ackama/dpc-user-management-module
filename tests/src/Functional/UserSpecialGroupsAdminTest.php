<?php

namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\dpc_user_management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group dpc_user_management
 */
class UserSpecialGroupsAdminTest extends BrowserTestBase
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
     * A normal user
     *
     * @var \Drupal\user\UserInterface
     */
    protected $regular_user;

    /**
     * @var Group $group
     */
    protected $group;

    /**
     * @var $special_groups Group[]
     */
    protected $special_groups = [];


    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Setup Admin User
        $this->user = $this->drupalCreateUser(['administer group fields', 'access content'], null, true);

        // Setup Normal User
        $this->regular_user = $this->drupalCreateUser();

        // Log in as admin
        $this->drupalLogin($this->user);

        // Get Access Group
        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var Group $group */
        $this->group = Group::load(array_pop($group_ids));
    }

    public function testJSEAccessValueControlsGroup()
    {
        // User should not be in Group upon creation
        $this->assertFalse($this->group->getMember($this->regular_user));

        // We check the removal field in profile and Save
        $this->drupalGet('user/' . $this->regular_user->id() . '/edit');
        $this->getSession()->getPage()->checkField('edit-jse-access-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // User should not be in Group
        $this->assertTrue($this->group->getMember($this->regular_user));
    }
}
