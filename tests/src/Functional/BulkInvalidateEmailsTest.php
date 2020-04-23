<?php
namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\WebAssert;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group rules_ui
 */
class BulkInvalidateEmailsTest extends BrowserTestBase
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
    protected $admin;
    protected $user1;
    protected $user2;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->admin = $this->drupalCreateUser(['administer site configuration', 'access administration pages']);
        $this->user1 = $this->drupalCreateUser(['administer account settings', 'administer node fields']);
        $this->user2 = $this->drupalCreateUser(['administer account settings', 'administer node fields']);

        $user1_addresses = [
            [
                'value'      => 'user1_one@email.com',
                'status'     => 'verified',
                'is_primary' => false
            ],
            [
                'value'      => 'user1_two@email.com',
                'status'     => 'verified',
                'is_primary' => false
            ]
        ];
        $user2_addresses = [
            [
                'value'      => 'user2_one@email.com',
                'status'     => 'verified',
                'is_primary' => false
            ],
            [
                'value'      => 'user2_two@email.com',
                'status'     => 'verified',
                'is_primary' => false
            ]
        ];
        $this->user1->field_email_addresses->setValue($user1_addresses);
        $this->user1->save();
        $this->user2->field_email_addresses->setValue($user2_addresses);
        $this->user2->save();
    }

    public function testAdminCanSeeBulkInvalidateEmailsSetting()
    {
        $this->drupalLogin($this->admin);
        $this->drupalGet('admin/config');

        $this->assertLink('Bulk invalidate user emails');
        $this->clickLink('Bulk invalidate user emails');

        $this->assertText('List the emails you want to invalidate (one per line)');
    }

    public function testAdminCanBulkInvalidateEmails()
    {
        $this->drupalLogin($this->admin);
        $form_data = [
            "bulk_invalidate_emails" => "user1_one@email.com\n user2_one@email.com"
        ];

        $this->drupalPostForm('admin/config/system/bulk-invalidate-emails', $form_data, 'Submit');
        $this->assertSession()->responseContains('The following users were updated:');
        $this->assertSession()->responseContains($this->user1->getDisplayName());
        $this->assertSession()->responseContains($this->user2->getDisplayName());

    }

    public function testAdminGetsInformedWhenEmailsAreNotFound()
    {
        $this->drupalLogin($this->admin);
        $form_data = [
            "bulk_invalidate_emails" => "random_mail1@email.com\n random_mail2@email.com"
        ];

        $this->drupalPostForm('admin/config/system/bulk-invalidate-emails', $form_data, 'Submit');
        $this->assertSession()->responseContains('No users were found associated with the following addresses:');
        $this->assertSession()->responseContains('random_mail1@email.com');
        $this->assertSession()->responseContains('random_mail2@email.com');
    }

    public function testUserGetsNoticeAboutUnverifiedEmailAfterLogin()
    {
        $this->drupalLogin($this->admin);
        $form_data = [
            "bulk_invalidate_emails" => "user1_one@email.com"
        ];
        $this->drupalPostForm('admin/config/system/bulk-invalidate-emails', $form_data, 'Submit');

        $this->drupalLogin($this->user1);
        $this->assertText('You have an unverified email address, this may affect your ability to view some content. Go to your account settings page to re-verify or remove the email address.');
    }
}
