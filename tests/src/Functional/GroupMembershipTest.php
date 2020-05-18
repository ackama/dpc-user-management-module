<?php
namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\dpc_user_management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group rules_ui
 */
class GroupMembershipTest extends BrowserTestBase
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
        'node',
        'file',
        'field',
        'field_ui',
        'field_test',
        'views',
        'views_ui',
        'group',
        'dpc_user_management'
    ];

    /**
     * @var \Drupal\user\Entity\User $user
     */
    protected $user;
    /**
     * @var Group $group
     */
    protected $group;

    /**
     * {@inheritdoc}
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function setUp()
    {
        parent::setUp();
        $group_domains = [
            ['value' => 'test.net'],
            ['value' => 'domain.org'],
            ['value' => 'example.com']
        ];
        $this->group   = Group::create(['type' =>  UserEntity::$group_type_email_domain_id, 'label' => 'email domain group']);
        $this->group->set('field_email_domain', $group_domains);
        $this->group->save();

        $this->user = $this->drupalCreateUser(['administer users', 'administer node fields']);
    }

    /**
     * New user with 'example.com' email should be added to the group
     * after they log in for the first time
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function testNewUserIsAddedToGroupAfterLogin()
    {
        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));
    }

    /**
     * A user is added to the group after verifying a relevant email
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function testUserIsAddedToGroupAfterVerifyingNewEmail()
    {
        $user = $this->drupalCreateUser(['administer users', 'administer node fields'], null, false, ['mail' => 'user1234@randomdomain.com']);

        $this->drupalLogin($user);

        $this->assertFalse($this->group->getMember($user));

        // add a new email address
        $this->drupalGet('user/' . $user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => 'newemail@test.net'
        ];
        $this->drupalPostForm('user/' . $user->id() . '/edit', $edit, 'Save');

        // get the verification email
        $captured_emails = $this->drupalGetMails();
        preg_match("/(http|https):\/\/[a-zA-z.]*\/verify-email\/[0-9]*\/\?token=.*/", $captured_emails[1]['body'],
            $verification_link);
        $this->drupalGet($verification_link[0]);

        $this->assertNotFalse($this->group->getMember($user));
    }

    /**
     * A user is removed from the group when they remove their relevant email
     */
    public function testUserIsRemovedFromGroupAfterRemovingEmail()
    {
        $random_string = $this->randomMachineName();
        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));

        // remove default email from user (@example.com)
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => "$random_string@otherdomain.net",
            "field_email_addresses[1][is_primary]" => true,
            "field_email_addresses[0][is_primary]" => false,
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');

        // verify the new email
        $captured_emails = $this->drupalGetMails();
        preg_match("/(http|https):\/\/[a-zA-z.]*\/verify-email\/[0-9]*\/\?token=.*/", $captured_emails[1]['body'],
            $verification_link);
        $this->drupalGet($verification_link[0]);

        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[0][value]" => ''
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');

        // check that the user was removed from the group
        $this->assertFalse($this->group->getMember($this->user));

        // check that the user received a notification after being removed
        $site_name = \Drupal::config('system.site')->get('name');
        $captured_emails = $this->drupalGetMails();

        $this->assertEqual("$site_name: You have been removed from a group", $captured_emails[2]['subject']);
    }

    /**
     * A user remains in the group after they remove an email if they have other
     * emails which allow then to stay in the group
     */
    public function testUserRemainsInGroupAfterRemovingEmail()
    {
        $random_string = $this->randomMachineName();
        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));

        // add a new email
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => "$random_string@test.net",
            "field_email_addresses[1][is_primary]" => true,
            "field_email_addresses[0][is_primary]" => false
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        // verify the new email
        $captured_emails = $this->drupalGetMails();
        preg_match("/(http|https):\/\/[a-zA-z.]*\/verify-email\/[0-9]*\/\?token=.*/", $captured_emails[1]['body'],
            $verification_link);
        $this->drupalGet($verification_link[0]);

        // remove default email from user (@example.com)
        $edit = [
            "field_email_addresses[0][value]" => '',
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assertNotFalse($this->group->getMember($this->user));
    }

    /**
     * A user is removed from a group even with other emails which allow then to
     * stay in the group, if those other emails are not verified
     */
    public function testUserIsRemovedFromGroupAfterRemovingEmailIfOtherEmailsAreNotVerified()
    {
        $random_string = $this->randomMachineName();

        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));

        // add a new email
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => "$random_string@test.net",
            "field_email_addresses[1][is_primary]" => true
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');

        // remove default email from user (@example.com)
        $edit = [
            "field_email_addresses[0][value]" => null,
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assertFalse($this->group->getMember($this->user));
    }

    public function testGroupMembershipActionsAreLogged()
    {
        $admin = $this->drupalCreateUser(['administer site configuration', 'access administration pages']);

        // user was added to a group
        $this->drupalLogin($this->user);

        $this->drupalLogin($admin);
        $this->drupalGet('admin/config/system/group-events-log');
        $group_name = $this->group->getName();
        $user_name = $this->user->getDisplayName();

        $this->assertContains("added $group_name $user_name pending", $this->getSession()->getPage()->getText());
    }
}
