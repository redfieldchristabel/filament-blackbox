<?php

// config for Blackbox/FilamentBlackbox
return [


    /*
    |--------------------------------------------------------------------------
    | Resource Mapping
    |--------------------------------------------------------------------------
    | This maps your Auditable models to their UI badges and routes.
    */
    'resources' => [
        
        // \App\Models\User::class => [
        //     'label' => 'User',
        //     'color' => 'fi-badge-color-primary', // Tailwind/Filament badge class
        //     'url' => 'filament.admin.resources.users.edit', // Please use filament get route helper instead
        // ],


        // Default fallback settings
        'default' => [
            'color' => 'fi-badge-color-gray',
        ],
    ],


];
