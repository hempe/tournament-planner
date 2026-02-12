<?php

return [
    // Common
    'app' => [
        'name' => 'Golf El Faro',
        'welcome' => 'Willkommen bei Golf El Faro',
    ],

    // Navigation
    'nav' => [
        'home' => 'Startseite',
        'events' => 'Anlässe',
        'users' => 'Benutzer',
        'logout' => 'Abmelden',
        'admin' => 'Administration',
        'back' => 'Zurück',
    ],

    // Authentication
    'auth' => [
        'login' => 'Anmelden',
        'username' => 'Benutzername',
        'password' => 'Passwort',
        'login_failed' => 'Anmeldung fehlgeschlagen',
        'required_fields' => 'Benutzername und Passwort sind erforderlich',
        'logout_success' => 'Erfolgreich abgemeldet',
        'login_required' => 'Anmeldung erforderlich',
    ],

    // Events
    'events' => [
        'title' => 'Anlässe',
        'new' => 'Neuer Anlass',
        'name' => 'Name',
        'date' => 'Datum',
        'capacity' => 'Kapazität',
        'registrations' => 'Anmeldungen',
        'comment' => 'Kommentar',
        'register' => 'Anmelden',
        'unregister' => 'Abmelden',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen',
        'lock' => 'Sperren',
        'unlock' => 'Entsperren',
        'locked' => 'Gesperrt',
        'locked_message' => 'Anmeldung geschlossen, bitte bei kurzfristigen Änderungen oder Kommentaren anrufen!',
        'update_success' => 'Anlass erfolgreich aktualisiert',
        'delete_success' => 'Anlass erfolgreich gelöscht',
        'registration_success' => 'Erfolgreich angemeldet',
        'unregistration_success' => 'Erfolgreich abgemeldet',
        'not_found' => 'Anlass nicht gefunden',
        'lock_success' => 'Anlass gesperrt',
        'unlock_success' => 'Anlass entsperrt',
        'comment_update_success' => 'Kommentar aktualisiert',
        'confirmed' => 'Bestätigt',
        'waitlist' => 'Warteliste',
        'waitlist_available' => 'Warteliste verfügbar',
        'spots_available' => ':count Plätze frei',
        'spot_available' => ':count Platz frei',
        'unknown_state' => 'Unbekannter Status',
    ],

    // Users
    'users' => [
        'title' => 'Benutzer',
        'new' => 'Neuer Benutzer',
        'username' => 'Benutzername',
        'password' => 'Passwort',
        'admin' => 'Administrator',
        'create' => 'Erstellen',
        'update' => 'Aktualisieren',
        'delete' => 'Löschen',
        'is_admin' => 'Ist Administrator',
        'update_success' => 'Benutzer erfolgreich aktualisiert',
        'delete_success' => 'Benutzer erfolgreich gelöscht',
        'username_taken' => 'Benutzername ":username" ist bereits vergeben',
        'admin_update_success' => 'Administrator-Status aktualisiert',
        'password_update_success' => 'Passwort erfolgreich geändert',
    ],

    // Validation
    'validation' => [
        'required' => 'Das Feld :field ist erforderlich.',
        'string' => 'Das Feld :field muss eine Zeichenkette sein.',
        'integer' => 'Das Feld :field muss eine ganze Zahl sein.',
        'email' => 'Das Feld :field muss eine gültige E-Mail-Adresse sein.',
        'min' => 'Das Feld :field muss mindestens :min Zeichen haben.',
        'max' => 'Das Feld :field darf höchstens :max Zeichen haben.',
        'date' => 'Das Feld :field muss ein gültiges Datum sein.',
        'boolean' => 'Das Feld :field muss wahr oder falsch sein.',
        'in' => 'Das Feld :field muss einer der folgenden Werte sein: :values.',
    ],

    // Errors
    'errors' => [
        'title' => 'Fehler',
        'general' => 'Ein Fehler ist aufgetreten.',
        'not_found' => 'Die angeforderte Seite wurde nicht gefunden.',
        'unauthorized' => 'Sie sind nicht berechtigt, diese Aktion auszuführen.',
        'forbidden' => 'Zugriff verweigert.',
        'csrf' => 'Sicherheitstoken ungültig. Bitte versuchen Sie es erneut.',
    ],

    // Success
    'success' => [
        'title' => 'Erfolg',
    ],

    // Buttons and Actions
    'actions' => [
        'save' => 'Speichern',
        'cancel' => 'Abbrechen',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen',
        'create' => 'Erstellen',
        'update' => 'Aktualisieren',
        'confirm' => 'Bestätigen',
        'close' => 'Schliessen',
        'yes' => 'Ja',
        'no' => 'Nein',
    ],

    // Calendar
    'calendar' => [
        'previous_month' => 'Vorheriger Monat',
        'next_month' => 'Nächster Monat',
        'confirmation' => 'Bestätigung',
        'months' => [
            'january' => 'Januar',
            'february' => 'Februar',
            'march' => 'März',
            'april' => 'April',
            'may' => 'Mai',
            'june' => 'Juni',
            'july' => 'Juli',
            'august' => 'August',
            'september' => 'September',
            'october' => 'Oktober',
            'november' => 'November',
            'december' => 'Dezember',
        ],
        'weekdays' => [
            'monday' => 'Montag',
            'tuesday' => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag',
        ],
    ],

    // Time
    'time' => [
        'just_now' => 'Gerade eben',
        'years' => ':count Jahr|:count Jahre',
        'months' => ':count Monat|:count Monate',
        'days' => ':count Tag|:count Tage',
        'hours' => ':count Std.',
        'minutes' => ':count Min.',
    ],
];