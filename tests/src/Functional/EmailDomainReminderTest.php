<?php
namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\dpc_user_management\Plugin\Block\EmailDomainReminderBlock;
use Drupal\DPC_User_Management\UserEntity;
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
     * @var
     */
    protected $block;

    /**
     * @var string
     */
    protected $selector;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Creates test group
        $this->group   = Group::create(['type' =>  UserEntity::$group_type_email_domain_id, 'label' => 'email domain group']);

        // Sets up ID Selector for Banner
        $this->selector = '#' . EmailDomainReminderBlock::$_div_id;
    }

    /**
     * Tests if message is hidden for Anonymous Users
     *
     * @throws \Behat\Mink\Exception\ResponseTextException
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function testAnonymousUser()
    {
        $web_assert = $this->assertSession();

        // Check home URL with an anonymous
        $this->drupalGet('');

        // Check banner is not visible
        $web_assert->elementNotExists('css', $this->selector);
    }

    /**
     * @throws \Behat\Mink\Exception\ExpectationException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function testKnownValidUser()
    {
        // Create a valid group that will match the domain of the user
        $this->group->set('field_email_domain', [['value' => 'example.com']]);
        $this->group->save();

        // Create User
        $this->user = $this->drupalCreateUser();
        $this->drupalLogin($this->user);

        $web_assert = $this->assertSession();

        // Check home URL with an anonymous
        $this->drupalGet('');

        // Banner should not be visible
        $web_assert->elementNotExists('css', $this->selector);
    }

    /**
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function testKnownInvalidUser()
    {
        // Sets mail domains that will be invalid
        $this->group->set('field_email_domain', [['value' => 'invalidusers.com']]);
        $this->group->save();

        // Create User
        $this->user = $this->drupalCreateUser();
        $this->drupalLogin($this->user);

        $web_assert = $this->assertSession();

        // Check home URL with an anonymous
        $this->drupalGet('');

        // Banner should be visible
        $web_assert->elementExists('css', $this->selector);
    }

}
