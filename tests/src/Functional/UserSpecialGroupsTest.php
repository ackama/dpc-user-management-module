<?php

namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\DPC_User_Management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group dpc_user_management
 */
class UserSpecialGroupsTest extends BrowserTestBase
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
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->user = $this->drupalCreateUser(['administer group fields'], null, true);
        $this->drupalLogin($this->user);
        $this->drupalGet('user/' . $this->user->id() . '/edit');

        // Get Access Group
        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var Group $group */
        $this->group = Group::load(array_pop($group_ids));

        // Create 2 Special Groups
        $this->special_groups = array_map(function ($id) {
            $group = \Drupal\group\Entity\Group::create([
                     'label' => $id,
                     'type' => UserEntity::$group_special_type_id
                 ]);
            $group->save();

            return $group;
        }, ['group-1', 'group-2']);

    }

    public function testSpecialGroupFieldExists()
    {
        $web_assert = $this->assertSession();

        // Checks for checkbox for group[0] to exist
        $element_id = sprintf('edit-special-groups-%s', $this->special_groups[0]->id());

        $web_assert->fieldExists($element_id);
    }

    public function testSpecialGroupSavesValue()
    {
        // Sets checkbox ID (special_group[0]) to test with
        $element_id = sprintf('edit-special-groups-%s', $this->special_groups[0]->id());

        // Checks checkbox
        $this->getSession()->getPage()->checkField($element_id);
        $this->getSession()->getPage()->pressButton('edit-submit');

        $web_assert = $this->assertSession();

        // Checks that field is persisted
        $web_assert->fieldValueEquals($element_id, true);
    }

    public function testSpecialGroupControlsAccessGroup()
    {
        $element_id = sprintf('edit-special-groups-%s', $this->special_groups[1]->id());

        // Verify checkbox for special_group[1] is unchecked and save profile
        $this->getSession()->getPage()->uncheckField('edit-special-group-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // Check checkbox for special_group[1] and save profile
        $this->getSession()->getPage()->checkField('edit-special-group-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // User should be in Access Group now
        $this->assertTrue($this->group->getMember($this->user));
    }

    public function testJSEAccessValueControlsGroup()
    {
        // User should not be in Group upon creation
        $this->assertFalse($this->group->getMember($this->user));

        // We check the field in profile and Save
        $this->getSession()->getPage()->checkField('edit-jse-access-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // User should be in Group
        $this->assertTrue($this->group->getMember($this->user));

        // We uncheck the field in profile and Save
        $this->getSession()->getPage()->uncheckField('edit-jse-access-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // User should not be in Group
        $this->assertFalse($this->group->getMember($this->user));
    }
}
