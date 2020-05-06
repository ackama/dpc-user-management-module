<?php
namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\group\Entity\Group;
use Drupal\Tests\BrowserTestBase;

/**
 * @group dpc_user_management
 */
class EmailDomainReminderTest extends BrowserTestBase
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
     * @var string
     */
    protected $testString = "Add a VPS e-mail address to";


    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Creates test group
        $this->group   = Group::create(['type' => 'email_domain_group', 'label' => 'email domain group']);

        // @TODO Add Block to header
    }

    /**
     * Tests if message is hidden for Anonymous Users
     *
     * @throws \Behat\Mink\Exception\ResponseTextException
     */
    public function testAnonymousUser()
    {
        $web_assert = $this->assertSession();

        // Check home URL with an anonymous
        $this->drupalGet('/');

        // Check banner is not visible
        $web_assert->pageTextNotContains($this->testString);
    }

    public function testKnownValidUser()
    {
        // Create a valid group that will match the domain of the user
        $this->group->set('field_email_domain', [['value' => '@example.com']]);
        $this->group->save();

        // Create User
        $this->user = $this->drupalCreateUser();
        $this->drupalLogin($this->user);

        $web_assert = $this->assertSession();

        // Check home URL with an anonymous
        $this->drupalGet('/');

        // Banner should not be visible
        $web_assert->pageTextNotContains($this->testString);
    }

    public function testKnownInvalidUser()
    {
        // Sets mail domains that will be invalid
        $this->group->set('field_email_domain', [['value' => '@invalidusers.com']]);
        $this->group->save();

        // Create User
        $this->user = $this->drupalCreateUser();
        $this->drupalLogin($this->user);

        $web_assert = $this->assertSession();

        // Check home URL with an anonymous
        $this->drupalGet('/');

        // Banner should be visible
        $web_assert->pageTextNotContains($this->testString);
    }

}
