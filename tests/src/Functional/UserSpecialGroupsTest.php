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
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->user = $this->drupalCreateUser(['administer group fields'], null, true);
        $this->drupalLogin($this->user);
        $this->drupalGet('user/' . $this->user->id() . '/edit');

        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var Group $group */
        $this->group = Group::load(array_pop($group_ids));
    }

    public function testSpecialGroupFieldExists()
    {
        $web_assert = $this->assertSession();

        $web_assert->fieldExists('edit-special-group-value');
    }

    public function testSpecialGroupSavesValue()
    {
        $this->getSession()->getPage()->checkField('edit-special-group-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        $web_assert = $this->assertSession();

        $web_assert->fieldValueEquals('edit-special-group-value', true);
    }

    public function testSpecialGroupValueControlsGroup()
    {
        // Verify checkbox is unchecked and save profile
        $this->getSession()->getPage()->uncheckField('edit-special-group-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // User checks special group field and saves
        $this->getSession()->getPage()->checkField('edit-special-group-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        // User should be in Group
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
