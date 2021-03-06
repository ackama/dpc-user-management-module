<?php
namespace Drupal\Tests\dpc_user_management\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group rules_ui
 */
class UserEditViewTest extends BrowserTestBase
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
        $this->user = $this->drupalCreateUser(['administer users', 'administer node fields']);
        $this->drupalLogin($this->user);
        $this->drupalGet('user/' . $this->user->id() . '/edit');
    }

    public function testDefaultEmailAddressFieldIsHidden()
    {
        $this->assertNoField('mail');
    }

    public function testMultipleEmailAddressFieldHasPrimaryEmail()
    {
        $this->assertFieldByXPath("//input[@name='field_email_addresses[0][value]']", $this->user->getEmail());
        $this->assertFieldByXPath("//input[@name='field_email_addresses[0][is_primary]']", 1);
    }

    public function testUserCanAddANewEmail()
    {
        $this->assertFieldByXPath("//input[@name='field_email_addresses[1][value]']", null);
        $random_string = $this->randomMachineName();
        $edit = [
            "field_email_addresses[1][value]" => "$random_string@example.com"
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');

        $this->assertFieldByXPath("//input[@name='field_email_addresses[1][value]']", "$random_string@example.com");
    }

    public function testUserCanVerifyEmail()
    {
        $user = $this->drupalCreateUser(['administer users', 'administer node fields']);
        $this->drupalLogin($user);
        $this->drupalGet('user/' . $user->id() . '/edit');

        // verification flag and button are not present
        $this->assertElementNotPresent('#field-email-addresses-values span.status-pending');
        $this->assertElementPresent('#field-email-addresses-values .dpc_resend_verification.verified');

        // add a new email address
        $random_string = $this->randomMachineName();
        $edit = [
            "field_email_addresses[1][value]" =>"$random_string@example.com"
        ];
        $this->drupalPostForm('user/' . $user->id() . '/edit', $edit, 'Save');

        // check the verification email
        $captured_emails = $this->drupalGetMails();
        $this->assert(count($captured_emails), 1);
        $this->assertMail('subject', 'Email verification');
        $this->assert(preg_match("/(http|https):\/\/[a-zA-z.]*\/verify-email\/[0-9]*\/\?token=.*/", $captured_emails[0]['body'],
            $verification_link), 1);

        // verification flag and resend button are present
        $this->assertElementPresent('#field-email-addresses-values span.status-pending');
        $this->assertElementPresent('#field-email-addresses-values .dpc_resend_verification.pending');

        // visit the verification link
        $this->drupalGet($verification_link[0]);
        $this->assertResponse(200);
        $this->assertText('Thank you for verifying your email address');
        $this->drupalGet('user/' . $user->id() . '/edit');

        // check that the email is verified
        $this->assertElementPresent('#field-email-addresses-values span.status-verified');
        $this->assertElementPresent('#field-email-addresses-values .dpc_resend_verification.verified');
    }

    public function testUserCanResendEmailVerification()
    {
        $user = $this->drupalCreateUser(['administer users', 'administer node fields']);
        $this->drupalLogin($user);
        $randomString = $this->randomMachineName();
        $edit = [
            "field_email_addresses[1][value]" => "$randomString@example.com"
        ];
        $this->drupalPostForm('user/' . $user->id() . '/edit', $edit, 'Save');
        // "click" the 'resend verification' button
        $this->click('.dpc_resend_verification');
        $captured_emails = $this
            ->drupalGetMails();
        $this->assert(count($captured_emails), 2);
    }

    public function testEmailCanBeRemoved()
    {
        $random_string = $this->randomMachineName();
        $edit = [
            "field_email_addresses[1][value]" => "$random_string@example.com",
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assertFieldByXPath("//input[@name='field_email_addresses[1][value]']", "$random_string@example.com");
        $edit = [
            "field_email_addresses[1][value]" => ''
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assertFieldByXPath("//input[@name='field_email_addresses[1][value]']", '');
    }

    public function testPrimaryEmailCanBeSet()
    {
        $random_string = $this->randomMachineName();
        $edit = [
            "field_email_addresses[1][value]" => "$random_string@example.com",
            "field_email_addresses[1][is_primary]" => 1,
        ];
        $this->drupalPostForm('user/' . $this->user->id() . '/edit', $edit, 'Save');
        $this->assert("$random_string@example.com", $this->user->getEmail());
    }
}
