<?php

return [
    // Common
    'app' => [
        'name' => 'Golf El Faro',
        'welcome' => 'Welcome to Golf El Faro',
    ],
    
    // Navigation
    'nav' => [
        'home' => 'Home',
        'events' => 'Events',
        'users' => 'Users',
        'logout' => 'Logout',
        'admin' => 'Administration',
        'back' => 'Back',
    ],
    
    // Authentication
    'auth' => [
        'login' => 'Login',
        'username' => 'Username',
        'password' => 'Password',
        'login_failed' => 'Login failed',
        'required_fields' => 'Username and password are required',
        'logout_success' => 'Successfully logged out',
    ],
    
    // Events
    'events' => [
        'title' => 'Events',
        'new' => 'New Event',
        'name' => 'Name',
        'date' => 'Date',
        'capacity' => 'Capacity',
        'registrations' => 'Registrations',
        'comment' => 'Comment',
        'register' => 'Register',
        'unregister' => 'Unregister',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'lock' => 'Lock',
        'unlock' => 'Unlock',
        'locked_message' => 'Registration closed, please call for last-minute changes or comments!',
        'create_success' => 'Event created successfully',
        'update_success' => 'Event updated successfully',
        'delete_success' => 'Event deleted successfully',
        'registration_success' => 'Successfully registered',
        'unregistration_success' => 'Successfully unregistered',
    ],
    
    // Users
    'users' => [
        'title' => 'Users',
        'new' => 'New User',
        'username' => 'Username',
        'password' => 'Password',
        'admin' => 'Administrator',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'is_admin' => 'Is Administrator',
        'create_success' => 'User created successfully',
        'update_success' => 'User updated successfully',
        'delete_success' => 'User deleted successfully',
    ],
    
    // Validation
    'validation' => [
        'required' => 'The :field field is required.',
        'string' => 'The :field field must be a string.',
        'integer' => 'The :field field must be an integer.',
        'email' => 'The :field field must be a valid email address.',
        'min' => 'The :field field must be at least :min characters.',
        'max' => 'The :field field must not exceed :max characters.',
        'date' => 'The :field field must be a valid date.',
        'boolean' => 'The :field field must be true or false.',
        'in' => 'The :field field must be one of: :values.',
    ],
    
    // Errors
    'errors' => [
        'general' => 'An error occurred.',
        'not_found' => 'The requested page was not found.',
        'unauthorized' => 'You are not authorized to perform this action.',
        'forbidden' => 'Access denied.',
        'csrf' => 'Security token invalid. Please try again.',
    ],
    
    // Buttons and Actions
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'create' => 'Create',
        'update' => 'Update',
        'confirm' => 'Confirm',
        'close' => 'Close',
        'yes' => 'Yes',
        'no' => 'No',
    ],
    
    // Calendar
    'calendar' => [
        'months' => [
            'january' => 'January',
            'february' => 'February',
            'march' => 'March',
            'april' => 'April',
            'may' => 'May',
            'june' => 'June',
            'july' => 'July',
            'august' => 'August',
            'september' => 'September',
            'october' => 'October',
            'november' => 'November',
            'december' => 'December',
        ],
        'weekdays' => [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ],
    ],
];