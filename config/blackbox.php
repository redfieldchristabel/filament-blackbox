<?php

use Blackbox\FilamentBlackbox\Support\DefaultUserResolver;

/**
 * Filament Blackbox Configuration
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Resource Mapping
    |--------------------------------------------------------------------------
    |
    | This maps your Auditable models to their UI badges, routes, and relationships.
    |
    | Model Mapping:
    | The key should be the fully qualified class name of your model.
    |
    | 'badge_class':
    | These classes are APPENDED to the base Filament badge classes:
    | "fi-badge fi-color-custom fi-size-sm fi-badge-color-..."
    | You can use Filament colors (e.g., 'fi-color-primary') or standard Tailwind.
    |
    | IMPORTANT FOR TAILWIND:
    | If you use custom Tailwind classes here, you MUST add the following to
    | your theme's CSS file so Tailwind's JIT compiler scans this config:
    | @source '../../../../config/blackbox.php';
    |
    | 'resource':
    | The fully qualified name of the Filament Resource class. Used to generate
    | 'edit' or 'view' URLs for the auditable record.
    |
    | 'relations':
    | Defines which related models should have their audits pulled into the
    | main record's timeline (e.g., seeing "Address" edits while viewing a "User").
    |
    | KEY: The relationship name defined on your Model (e.g., 'profile').
    | VALUE: A comma-separated string of nested relationships to eager load
    |        (e.g., 'user,avatar'). If no sub-relations are needed, leave EMPTY ('').
    |
    | Indexed entries are accepted too: ['profile', 'posts'] is the same as
    | ['profile' => '', 'posts' => '']. When the 'relations' key is absent, the
    | model's own auditRelations() method (same array shape) is used if it exists.
    |
    | Which attributes are shown, reverted or recovered is NOT configured here:
    | it comes from your owen-it model config (auditExclude, auditInclude,
    | auditStrict, auditTimestamps, hidden / visible).
    |
    */
    'resources' => [

        // \App\Models\User::class => [
        //     'label' => 'User',
        //     'badge_class' => 'fi-badge fi-color-primary', // Appends to base badge classes
        //     'resource' => \App\Filament\Resources\UserResource::class,
        //     'relations' => [
        //         'profile' => '', // Loads profile audits; no sub-relations
        //         'posts'   => 'comments,author', // Loads posts and eager-loads comments/author
        //     ]
        // ],

        /*
        |--------------------------------------------------------------------------
        | Default Fallback Settings
        |--------------------------------------------------------------------------
        */
        'default' => [
            'badge_class' => 'fi-color-gray',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Users (filter and labels)
    |--------------------------------------------------------------------------
    |
    | The audit user is resolved the owen-it way: the audit's `user` morph, which
    | follows `audit.user.morph_prefix` in your config/audit.php. Nothing here
    | assumes a `users` table or a `name` column.
    |
    | Labels use Filament's own `Filament::getUserName()` (the `HasName`
    | contract — `getFilamentName()`, else the `name` attribute), then `#id`
    | only when that yields nothing. 'System' is shown only when an audit has
    | no user.
    |
    | 'resolver':
    | Class-string of a class implementing
    | \Blackbox\FilamentBlackbox\Contracts\UserResolver (search(string): array and
    | labels(array $ids): array, both returning [id => label]). It powers the
    | server-side searchable user filter. Class-strings only, so the config stays
    | cacheable (config:cache). The default offers the user model of EVERY guard
    | in audit.user.guards, and keys each option by the audit's own user morph
    | (<user_type>:<user_id> as owen-it stores it), so users of different user
    | models with the same id stay distinguishable.
    |
    | 'search_columns':
    | Columns the default resolver searches. Columns the user table does not have
    | are skipped.
    |
    */
    'users' => [
        'resolver' => DefaultUserResolver::class,
        'search_columns' => ['name', 'email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Without a Policy
    |--------------------------------------------------------------------------
    |
    | Blackbox checks the model's policy before showing a record's audits or
    | letting someone Revert / Recover / Restore. When a model has NO policy
    | registered, this decides the outcome: true (default, the behaviour of
    | v0.0.x) allows it, false denies it. Models that have a policy always go
    | through the policy.
    |
    */
    'authorize_without_policy' => true,

    /*
    |--------------------------------------------------------------------------
    | Asset Registration
    |--------------------------------------------------------------------------
    |
    | If true, the package's CSS assets will be automatically registered with Filament.
    |
    | Recommended: Set to false and include the following in your theme's CSS file:
    |
    */
    // @source '../../../../vendor/redfieldchristabel/filament-blackbox/**/*';

    'register_assets' => false,
];
