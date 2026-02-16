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
        'language' => 'Language',
    ],

    'navigation' => [
        'home' => 'Go to Home',
        'events' => 'Go to Events',
        'login' => 'Go to Login',
        'try_again' => 'Try Again',
    ],

    // Languages
    'languages' => [
        'de' => 'Deutsch',
        'en' => 'English',
        'es' => 'Español',
    ],

    // Theme
    'theme' => [
        'dark' => 'Dark theme',
        'light' => 'Light theme',
    ],

    // Authentication
    'auth' => [
        'login' => 'Login',
        'welcome' => 'Welcome to Golf el Faro',
        'username' => 'Username',
        'password' => 'Password',
        'login_failed' => 'Login failed',
        'required_fields' => 'Username and password are required',
        'logout_success' => 'Successfully logged out',
        'login_required' => 'Login required',
        'register_user' => 'Register :username?',
        'register_user_waitlist' => 'Add :username to waitlist?',
        'not_registered' => 'Not registered',
    ],

    // Events
    'events' => [
        'title' => 'Events',
        'new' => 'New Event',
        'add' => 'Add Event',
        'name' => 'Name',
        'date' => 'Date',
        'capacity' => 'Capacity',
        'registrations' => 'Registrations',
        'registered' => 'Registered',
        'comment' => 'Comment',
        'register' => 'Register',
        'unregister' => 'Unregister',
        'register_confirm' => 'Register?',
        'unregister_confirm' => 'Unregister?',
        'unregister_user_confirm' => 'Unregister :name?',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_confirm' => 'Permanently delete event :name?',
        'delete_confirm_short' => 'Permanently delete event?',
        'lock' => 'Lock',
        'lock_confirm' => 'Lock event?',
        'unlock' => 'Unlock',
        'unlock_confirm' => 'Unlock event?',
        'locked' => 'Locked',
        'locked_message' => 'Registration closed, please call for last-minute changes or comments!',
        'update_success' => 'Event updated successfully',
        'delete_success' => 'Event deleted successfully',
        'registration_success' => 'Successfully registered',
        'unregistration_success' => 'Successfully unregistered',
        'not_found' => 'Event not found',
        'lock_success' => 'Event locked',
        'unlock_success' => 'Event unlocked',
        'comment_update_success' => 'Comment updated',
        'comment_update_confirm' => 'Update comment?',
        'confirmed' => 'Confirmed',
        'waitlist' => 'Waitlist',
        'waitlist_available' => 'Waitlist available',
        'on_waitlist' => 'On waitlist',
        'join_waitlist' => 'Join waitlist?',
        'spots_available' => ':count spots available',
        'spot_available' => ':count spot available',
        'max_participants' => 'Max. Participants',
        'user' => 'User',
        'save' => 'Save',
        'unknown_state' => 'Unknown status',
        'bulk_create' => 'Create Multiple Events',
        'bulk_start_date' => 'Start Date',
        'bulk_end_date' => 'End Date',
        'bulk_day_of_week' => 'Day of Week',
        'bulk_select_day' => 'Please select',
        'bulk_preview' => 'Show Preview',
        'bulk_preview_title' => 'Preview: :count events will be created',
        'bulk_create_all' => 'Create All',
        'bulk_no_events' => 'No Events Found',
        'bulk_no_events_message' => 'No events were found matching the specified criteria.',
        'bulk_create_success' => ':count events created successfully.',
        'bulk_session_expired' => 'Session expired. Please start over.',
    ],

    // Users
    'users' => [
        'title' => 'Users',
        'new' => 'New User',
        'add_user' => 'Add User',
        'create_user' => 'Create User',
        'username' => 'Username',
        'password' => 'Password',
        'new_password' => 'New Password',
        'set_new_password' => 'Set New Password',
        'set_new_password_confirm' => 'Set new password?',
        'admin' => 'Administrator',
        'create' => 'Create',
        'register' => 'Register',
        'update' => 'Update',
        'delete' => 'Delete',
        'delete_confirm' => 'Permanently delete :username?',
        'is_admin' => 'Is Administrator',
        'give_admin_rights' => 'Give admin rights?',
        'remove_admin_rights' => 'Remove admin rights?',
        'give_admin_rights_confirm' => 'Give admin rights to :username?',
        'remove_admin_rights_confirm' => 'Remove admin rights from :username?',
        'create_success' => 'User created successfully',
        'update_success' => 'User updated successfully',
        'delete_success' => 'User deleted successfully',
        'username_taken' => 'Username ":username" is already taken',
        'admin_update_success' => 'Administrator status updated',
        'password_update_success' => 'Password changed successfully',
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
        'title' => 'Error',
        'general' => 'An error occurred.',
        'not_found' => 'The requested page was not found.',
        'unauthorized' => 'You are not authorized to perform this action.',
        'forbidden' => 'Access denied.',
        'csrf' => 'Security token invalid. Please try again.',
        '404_title' => 'Page Not Found',
        '403_title' => 'Access Denied',
        '500_title' => 'Server Error',
        'server_error' => 'An unexpected error occurred. Please try again later.',
    ],

    // Success
    'success' => [
        'title' => 'Success',
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
        'previous_month' => 'Previous Month',
        'next_month' => 'Next Month',
        'confirmation' => 'Confirmation',
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

    // Time
    'time' => [
        'just_now' => 'Just now',
        'years' => ':count year|:count years',
        'months' => ':count month|:count months',
        'days' => ':count day|:count days',
        'hours' => ':count hr.',
        'minutes' => ':count min.',
    ],
];