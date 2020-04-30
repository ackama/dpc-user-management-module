<?php
namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\Core\Test\AssertMailTrait;
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
        $this->group   = Group::create(['type' => 'email_domain_group']);
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
        $this->user->setEmail('test@randomdomain.com')->save();
        $this->drupalLogin($this->user);

        $this->assertFalse($this->group->getMember($this->user));

        // add a new email address
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => 'newemail@test.net'
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');

        // get the verification email
        $captured_emails = $this->drupalGetMails();
        preg_match("/(http|https):\/\/[a-zA-z.]*\/verify-email\/[0-9]*\/\?token=.*/", $captured_emails[0]['body'],
            $verification_link);
        $this->drupalGet($verification_link[0]);

        $this->assertNotFalse($this->group->getMember($this->user));
    }

    /**
     * A user is removed from the group when they remove their relevant email
     */
    public function testUserIsRemovedFromGroupAfterRemovingEmail()
    {
        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));

        // remove default email from user (@example.com)
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[0][value]" => '',
            "field_email_addresses[1][value]" => 'newemail@otherdomain.net'
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assertFalse($this->group->getMember($this->user));
    }

    /**
     * A user remains in the group after they remove an email if they have other
     * emails which allow then to stay in the group
     */
    public function testUserRemainsInGroupAfterRemovingEmail()
    {
        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));

        // add a new email
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => 'newemail@test.net'
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        // verify the new email
        $captured_emails = $this->drupalGetMails();
        preg_match("/(http|https):\/\/[a-zA-z.]*\/verify-email\/[0-9]*\/\?token=.*/", $captured_emails[0]['body'],
            $verification_link);
        $this->drupalGet($verification_link[0]);

        // remove default email from user (@example.com)
        $edit = [
            "field_email_addresses[0][value]" => null,
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
        $this->drupalLogin($this->user);
        $this->assertNotFalse($this->group->getMember($this->user));

        // add a new email
        $this->drupalGet('user/' . $this->user->id() . '/edit');
        $edit = [
            "field_email_addresses[1][value]" => 'newemail@test.net'
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');

        // remove default email from user (@example.com)
        $edit = [
            "field_email_addresses[0][value]" => null,
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assertFalse($this->group->getMember($this->user));

        // check that the user received a notification after being removed
        $site_name = \Drupal::config('system.site')->get('name');
        $this->assertMail('subject', "$site_name: You have been removed from a group");
    }
}