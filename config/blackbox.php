<?php

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
    | @source "../../config/blackbox.php";
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
    */
    'resources' => [

        // \App\Models\User::class => [
        //     'label' => 'User',
        //     'badge_class' => 'fi-color-primary', // Appends to base badge classes
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
