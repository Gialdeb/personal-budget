<?php

return [
    'users' => [
        'filters' => [
            'roles' => [
                'all' => 'All roles',
                'admin' => 'Admin',
                'staff' => 'Staff',
                'user' => 'User',
            ],
            'statuses' => [
                'all' => 'All statuses',
            ],
            'plans' => [
                'all' => 'All plans',
                'free' => 'Free',
            ],
        ],
        'flash' => [
            'banned' => 'User banned successfully.',
            'suspended' => 'User suspended successfully.',
            'reactivated' => 'User reactivated successfully.',
            'roles_updated' => 'User roles updated successfully.',
        ],
        'validation' => [
            'admin_target_forbidden' => 'This action cannot be performed on an admin user.',
            'roles_required' => 'Select at least one valid role.',
        ],
    ],
];
