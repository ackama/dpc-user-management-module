<?php

namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group rules_ui
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
        'node',
        'file',
        'field',
        'field_ui',
        'field_test',
        'views',
        'views_ui',
        'dpc_user_management'
    ];

    /**
     * A user with permission to administer site configuration.
     *
     * @var \Drupal\user\UserInterface
     */
    protected $user;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->user = $this->drupalCreateUser();
        $this->drupalLogin($this->user);
        $this->drupalGet('user/' . $this->user->id() . '/edit');
    }

    public function testSpecialGroupFieldExists()
    {
        $web_assert = $this->assertSession();

        $web_assert->fieldExists('special_group');
    }

    public function testSpecialGroupSavesValue()
    {
        $this->drupalGet('user' . $this->user->id() . '/edit');
        $field_new_value = $this->getSession()->getPage()->findField('special_group')->getValue();
        $this->getSession()->getPage()->fillField('special_group', $field_new_value);
        $this->getSession()->getPage()->pressButton('edit-submit');

        $web_assert = $this->assertSession();

        $field = $web_assert->fieldExists('special_group');
        $web_assert->fieldValueEquals('special_group', $field_new_value);
    }

}
