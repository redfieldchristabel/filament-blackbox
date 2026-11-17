<?php

namespace Blackbox\FilamentBlackbox\Tests;

use Blackbox\FilamentBlackbox\FilamentBlackboxServiceProvider;
use Blackbox\FilamentBlackbox\Tests\Support\TestPanelProvider;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use OwenIt\Auditing\AuditingServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Blackbox\\FilamentBlackbox\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentBlackboxServiceProvider::class,
            AuditingServiceProvider::class,
            TestPanelProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
        // laravel-auditing skips console requests by default, and tests run in the console.
        $app['config']->set('audit.console', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // A dedicated table: Testbench's skeleton ships its own `users` migration.
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('audited_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('nameless_users', function (Blueprint $table) {
            $table->id();
            // No `name` column: the label comes from getFilamentName(), which
            // composes two DB columns that no fallback could guess.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email_address')->unique();
            $table->string('avatar_url')->nullable();
            $table->timestamps();
        });

        Schema::create('secure_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('api_token')->nullable();
            $table->timestamps();
        });

        Schema::create('audited_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audited_post_id');
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('sealed_diaries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('secret_note')->nullable();
            $table->timestamps();
        });

        (require __DIR__ . '/../vendor/owen-it/laravel-auditing/database/migrations/audits.stub')->up();

        // Custom timestamp columns for the ScheduledAudit fixture: an audit
        // model whose CREATED_AT/UPDATED_AT are not created_at/updated_at.
        Schema::table('audits', function (Blueprint $table) {
            $table->timestamp('performed_at')->nullable();
            $table->timestamp('performed_until')->nullable();
        });
    }
}
