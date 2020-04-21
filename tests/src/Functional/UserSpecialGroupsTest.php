<?php

namespace Drupal\Tests\DPC_User_Management\Functional;

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

        $web_assert->fieldExists('edit-special-group-value');
    }

    public function testSpecialGroupSavesValue()
    {
        $this->getSession()->getPage()->checkField('edit-special-group-value');
        $this->getSession()->getPage()->pressButton('edit-submit');

        $web_assert = $this->assertSession();

        $web_assert->fieldValueEquals('edit-special-group-value', true);
    }

}
