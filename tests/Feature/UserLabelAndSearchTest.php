<?php

use Blackbox\FilamentBlackbox\FilamentBlackbox;
use Blackbox\FilamentBlackbox\Pages\Blackbox;
use Blackbox\FilamentBlackbox\Support\DefaultUserResolver;
use Blackbox\FilamentBlackbox\Support\UserLabel;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedComment;
use Blackbox\FilamentBlackbox\Tests\Support\AuditedPost;
use Blackbox\FilamentBlackbox\Tests\Support\NamelessUser;
use Blackbox\FilamentBlackbox\Tests\Support\StubUserResolver;
use Blackbox\FilamentBlackbox\Tests\Support\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('labels the audit user through getFilamentName when the user has no name column', function () {
    // `first_name`/`last_name` are two DB columns: "Zed Quill" exists only in
    // PHP, via getFilamentName() — no column-based fallback could produce it.
    $zed = NamelessUser::create(['first_name' => 'Zed', 'last_name' => 'Quill', 'email_address' => 'zed@example.test']);

    $this->actingAs($zed);
    $post = AuditedPost::create(['title' => 'Written by Zed']);

    $audit = $post->audits()->firstOrFail();

    expect(UserLabel::forAudit($audit))->toBe('Zed Quill');

    // The page (audit timeline) shows Filament's name.
    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('Zed Quill')
        ->assertSee('Written by Zed');

    // The slide-over timeline shows it too.
    Livewire::test('blackbox::blackbox-slideover', ['record' => $post])
        ->assertSuccessful()
        ->assertSee('Zed Quill');

    // And the user filter offers it as an option label — searched over REAL
    // DB columns (`first_name`/`last_name`), since "Zed Quill" is PHP-only.
    config()->set('auth.providers.users.model', NamelessUser::class);
    config()->set('blackbox.users.search_columns', ['first_name', 'last_name', 'email_address']);

    expect(app(FilamentBlackbox::class)->userResolver()->search('Quill'))
        ->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill']);
});

it('falls back to the plain name attribute through Filament itself, then to the id', function () {
    $alice = User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']);

    $bare = new AuditedComment;
    $bare->id = 7;

    expect(UserLabel::forUser($alice))->toBe('Alice Author')
        ->and(UserLabel::forUser($bare))->toBe('#7');
});

it('shows System only when the audit has no user', function () {
    $post = AuditedPost::create(['title' => 'No user']);
    $audit = $post->audits()->firstOrFail();

    expect(UserLabel::forAudit($audit))->toBe('System');

    // A user that can no longer be loaded keeps an identifiable label.
    $audit->forceFill(['user_type' => User::class, 'user_id' => 9999]);

    expect(UserLabel::forAudit($audit))->toBe('#9999');
});

it('renders the avatar url of a HasAvatar user, never an external fallback of our own', function () {
    $zed = NamelessUser::create([
        'first_name' => 'Zed',
        'last_name' => 'Quill',
        'email_address' => 'zed@example.test',
        'avatar_url' => 'https://cdn.example.test/zed.png',
    ]);

    $this->actingAs($zed);
    $post = AuditedPost::create(['title' => 'Avatar check']);

    // The page timeline renders Filament's own avatar resolution
    // (HasAvatar::getFilamentAvatarUrl()).
    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('https://cdn.example.test/zed.png')
        // The hardcoded external fallback URL is gone.
        ->assertDontSee('ui-avatars.com');

    // The slide-over embeds the same audit-item component: the avatar shows
    // there too, and still never through an external package-made URL.
    Livewire::test('blackbox::blackbox-slideover', ['record' => $post])
        ->assertSuccessful()
        ->assertSee('https://cdn.example.test/zed.png')
        ->assertDontSee('ui-avatars.com');
});

it('shows no avatar for a system audit without a user', function () {
    $post = AuditedPost::create(['title' => 'System audit']);

    Livewire::test(Blackbox::class)
        ->assertSuccessful()
        ->assertSee('System')
        // No user means no avatar, and never a package-made external URL.
        ->assertDontSee('ui-avatars.com');
});

it('searches users server-side without a name column', function () {
    config()->set('auth.providers.users.model', NamelessUser::class);
    // Columns the table lacks (name, email) are skipped.
    config()->set('blackbox.users.search_columns', ['name', 'email', 'first_name', 'last_name', 'email_address']);

    $zed = NamelessUser::create(['first_name' => 'Zed', 'last_name' => 'Quill', 'email_address' => 'zed@example.test']);
    $amy = NamelessUser::create(['first_name' => 'Amy', 'last_name' => 'Fox', 'email_address' => 'amy@example.test']);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    // Option values are the audit's own user morph: <user_type>:<user_id>.
    expect($resolver)->toBeInstanceOf(DefaultUserResolver::class)
        ->and($resolver->search('zed'))->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill'])
        ->and($resolver->search('Fox'))->toBe([DefaultUserResolver::filterValue($amy) => 'Amy Fox'])
        ->and($resolver->search('example.test'))->toHaveCount(2)
        ->and($resolver->labels([DefaultUserResolver::filterValue($zed)]))->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill'])
        // Users that no longer exist keep a readable label, typed or bare.
        ->and($resolver->labels(['App\\Ghost:99', '999']))
        ->toBe(['App\\Ghost:99' => '#99', '999' => '#999']);
});

it('resolves the user model from the guard of audit.user.guards, not a hardcoded provider', function () {
    // A provider that is not named `users`.
    config()->set('auth.providers.members.model', NamelessUser::class);
    config()->set('auth.guards.web.provider', 'members');
    config()->set('blackbox.users.search_columns', ['first_name', 'last_name', 'email_address']);

    $zed = NamelessUser::create(['first_name' => 'Zed', 'last_name' => 'Quill', 'email_address' => 'zed@example.test']);
    $amy = NamelessUser::create(['first_name' => 'Amy', 'last_name' => 'Fox', 'email_address' => 'amy@example.test']);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    expect(DefaultUserResolver::userModelClass())->toBe(NamelessUser::class)
        ->and($resolver->search('zed'))->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill'])
        ->and($resolver->search('Fox'))->toBe([DefaultUserResolver::filterValue($amy) => 'Amy Fox'])
        ->and($resolver->labels([DefaultUserResolver::filterValue($amy)]))->toBe([DefaultUserResolver::filterValue($amy) => 'Amy Fox']);
});

it('covers every configured guard and separates users with overlapping ids', function () {
    // Two guards on different models: web -> users (User), staff -> staff (NamelessUser).
    config()->set('auth.providers.staff.model', NamelessUser::class);
    config()->set('auth.guards.staff.provider', 'staff');
    config()->set('audit.user.guards', ['web', 'staff']);
    config()->set('blackbox.users.search_columns', ['first_name', 'last_name', 'email_address']);

    // Same id on both models: the morph type in the option value keeps them apart.
    $alice = User::create(['name' => 'Alice Author', 'email' => 'alice@example.test', 'password' => 'secret']);
    $zed = NamelessUser::create(['first_name' => 'Zed', 'last_name' => 'Quill', 'email_address' => 'zed@example.test']);

    expect($alice->id)->toBe(1)
        ->and($zed->id)->toBe(1)
        ->and(DefaultUserResolver::userModelClasses())->toBe([User::class, NamelessUser::class])
        ->and(DefaultUserResolver::userModelClass())->toBe(User::class);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    // A search covers BOTH provider models, in guard order.
    expect($resolver->search(''))->toBe([
        DefaultUserResolver::filterValue($alice) => 'Alice Author',
        DefaultUserResolver::filterValue($zed) => 'Zed Quill',
    ])
        // Labels resolve per morph type: id 1 is a different person per key.
        ->and($resolver->labels([DefaultUserResolver::filterValue($alice), DefaultUserResolver::filterValue($zed)]))
        ->toBe([
            DefaultUserResolver::filterValue($alice) => 'Alice Author',
            DefaultUserResolver::filterValue($zed) => 'Zed Quill',
        ]);

    // Audits by both users carry the same user_id: filtering stays type-aware.
    $this->actingAs($alice);
    $post = AuditedPost::create(['title' => 'Written by Alice']);

    $this->actingAs($zed);
    AuditedComment::create(['audited_post_id' => $post->id, 'body' => 'Written by Zed']);

    $blackbox = app(FilamentBlackbox::class);

    $aliceAudits = $blackbox->getAudits(filters: ['users' => [DefaultUserResolver::filterValue($alice)]]);
    $zedAudits = $blackbox->getAudits(filters: ['users' => [DefaultUserResolver::filterValue($zed)]]);

    expect($aliceAudits->items())->toHaveCount(1)
        ->and($aliceAudits->items()[0]->getAttribute('user_type'))->toBe(User::class)
        ->and($zedAudits->items())->toHaveCount(1)
        ->and($zedAudits->items()[0]->getAttribute('user_type'))->toBe(NamelessUser::class);
});

it('uses the configured user resolver', function () {
    config()->set('blackbox.users.resolver', StubUserResolver::class);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    expect($resolver)->toBeInstanceOf(StubUserResolver::class)
        ->and($resolver->search('x'))->toBe([7 => 'Stub x'])
        ->and($resolver->labels([7]))->toBe([7 => 'Stub #7']);
});

it('rejects a user resolver that does not implement the contract', function () {
    config()->set('blackbox.users.resolver', stdClass::class);

    app(FilamentBlackbox::class)->userResolver();
})->throws(InvalidArgumentException::class);

// --- Round 5: escaped LIKE wildcards, key ordering, one column listing --------

it('escapes like wildcards in the search term', function () {
    $pure = User::create(['name' => '100% Pure', 'email' => 'pure@example.test', 'password' => 'secret']);
    $proof = User::create(['name' => '100 Proof', 'email' => 'proof@example.test', 'password' => 'secret']);
    $snake = User::create(['name' => 'under_score', 'email' => 'snake@example.test', 'password' => 'secret']);
    $plain = User::create(['name' => 'underXscore', 'email' => 'plain@example.test', 'password' => 'secret']);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    // A literal `%` in the term matches only the percent name: unescaped, it
    // would match every `100*` user.
    expect($resolver->search('100%'))->toBe([DefaultUserResolver::filterValue($pure) => '100% Pure'])
        // A literal `_` matches only the underscore name: unescaped, it is a
        // single character wildcard and would match `underXscore` too.
        ->and($resolver->search('under_score'))->toBe([DefaultUserResolver::filterValue($snake) => 'under_score'])
        ->and($resolver->search('100 Proof'))->toBe([DefaultUserResolver::filterValue($proof) => '100 Proof']);
});

it('orders the search results by key, not by insertion', function () {
    // Inserted out of key order: without `orderBy(key)`, sqlite returns them
    // in rowid (insertion) order.
    DB::table('test_users')->insert([
        ['id' => 3, 'name' => 'Moe Fox', 'email' => 'moe@example.test', 'password' => 'secret'],
        ['id' => 1, 'name' => 'Amy Fox', 'email' => 'amy@example.test', 'password' => 'secret'],
        ['id' => 2, 'name' => 'Zed Quill', 'email' => 'zed@example.test', 'password' => 'secret'],
    ]);

    $keys = array_keys(app(FilamentBlackbox::class)->userResolver()->search(''));

    expect($keys)->toBe([
        'Blackbox\FilamentBlackbox\Tests\Support\User:1',
        'Blackbox\FilamentBlackbox\Tests\Support\User:2',
        'Blackbox\FilamentBlackbox\Tests\Support\User:3',
    ]);
});

it('lists the user table columns once per model when searching', function () {
    User::create(['name' => 'Zed Quill', 'email' => 'zed@example.test', 'password' => 'secret']);

    DB::enableQueryLog();

    app(FilamentBlackbox::class)->userResolver()->search('zed');

    $schemaQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => preg_match('/table_info|information_schema|pragma/i', $sql) === 1);

    // One column listing per model — previously one `hasColumn` probe per
    // configured search column.
    expect($schemaQueries)->toHaveCount(1);
});

// --- Round 6: case-insensitive search, key-compatible lookups, grouped labels --

it('searches case-insensitively on every driver, not just sqlite', function () {
    $zed = User::create(['name' => 'Zed Quill', 'email' => 'zed@example.test', 'password' => 'secret']);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    // The term differs in case from the stored value. sqlite's `like` happens
    // to be case-insensitive for ASCII, PostgreSQL's is NOT — so the assertion
    // below checks the generated SQL, which must be portable, and the result,
    // which must be found.
    expect($resolver->search('ZED QUILL'))->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill'])
        ->and($resolver->search('ZED@example.TEST'))->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill']);

    DB::enableQueryLog();
    $resolver->search('zed QUILL');

    $searchSql = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'test_users'))
        ->implode(' ');

    // lower() on BOTH sides of the comparison — on PostgreSQL a plain
    // `like ?` against the raw column would be case-sensitive.
    expect($searchSql)->toContain('lower(')
        ->and($searchSql)->toContain('lower(?)');
});

it('only looks up by key when no search column exists and the term is key-compatible', function () {
    // No configured column exists on the table: nothing is searchable.
    config()->set('blackbox.users.search_columns', ['nonexistent_column']);

    $zed = User::create(['name' => 'Zed Quill', 'email' => 'zed@example.test', 'password' => 'secret']);

    $resolver = app(FilamentBlackbox::class)->userResolver();

    expect($resolver->search((string) $zed->getKey()))->toBe([DefaultUserResolver::filterValue($zed) => 'Zed Quill'])
        // A non-numeric term against an integer key would make PostgreSQL
        // throw (`invalid input syntax for type bigint`): it finds nothing
        // instead of erroring.
        ->and($resolver->search('zed'))->toBe([]);
});

it('resolves selected users with one query per model, not one per id', function () {
    $zed = User::create(['name' => 'Zed Quill', 'email' => 'zed@example.test', 'password' => 'secret']);
    $amy = User::create(['name' => 'Amy Fox', 'email' => 'amy@example.test', 'password' => 'secret']);

    DB::enableQueryLog();

    $labels = app(FilamentBlackbox::class)->userResolver()->labels([
        DefaultUserResolver::filterValue($zed),
        DefaultUserResolver::filterValue($amy),
    ]);

    $selects = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'test_users'));

    expect($labels)->toBe([
        DefaultUserResolver::filterValue($zed) => 'Zed Quill',
        DefaultUserResolver::filterValue($amy) => 'Amy Fox',
    ])
        // Two selected users, ONE query — previously one per id.
        ->and($selects)->toHaveCount(1);
});
