<?php
namespace Drupal\Tests\DPC_User_Management\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group rules_ui
 */
class UserEditViewTest extends BrowserTestBase {
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
    protected function setUp() {
        parent::setUp();
        $this->user = $this->drupalCreateUser(['administer users',  'administer node fields']);
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
        // $this->assertFieldByXPath("//input[@name='field_email_addresses[0][label]']", $this->user->getEmail());
    }
}