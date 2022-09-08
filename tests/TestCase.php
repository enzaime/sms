<?php

declare(strict_types=1);

namespace Enzaime\Sms\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use WithFaker;

    /** @var \Virtunus\Tips\Models\User */
    protected $user;

    /** @var string */

    /** @var string */
    protected $userPassword = 'password';

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->artisan('migrate')->run();

        // $user = User::factory()->create();
        // $this->user = $user;
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $guards = $app['config']->get('auth.guards');

        $guards['api'] = [
            'driver' => 'token',
            'provider' => 'users',
            'hash' => false,
        ];

        $app['config']->set('auth.guards', $guards);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * Get package providers.  At a minimum this is the package being tested, but also
     * would include packages upon which our package depends, e.g. Cartalyst/Sentry
     * In a normal app environment these would be added to the 'providers' array in
     * the config/app.php file.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            // \Enzaime\DynamicLink\ServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.  In a normal app environment these would be added to
     * the 'aliases' array in the config/app.php file.  If your package exposes an
     * aliased facade, you should add the alias here, along with aliases for
     * facades upon which your package depends, e.g. Cartalyst/Sentry.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            //'YourPackage' => 'YourProject\YourPackage\Facades\YourPackage',
        ];
    }
}
