# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

**Start development server:**
```bash
php -S localhost:5000 -c php.ini
```

**Quick run script:**
```bash
./run.sh
```

**Database initialization:**
```bash
mysql < init.sql
```

## Architecture Overview

This is a PHP web application for golf event management with a custom MVC-like architecture:

### Core Architecture

- **Entry Point**: `index.php` - Sets up session, loads core components, and routes requests
- **Routing System**: Custom router in `src/core/Router.php` with declarative route definitions
- **Component System**: Base component class in `src/core/Component.php` for reusable UI elements
- **Database Layer**: Repository pattern with `src/core/DB.php` as main database facade

### Key Patterns

**Routing**: Routes are organized by feature modules (`events`, `users`, `home`) in `src/pages/*/routes.php`. Each route can handle both GET (views) and POST (actions) with the same path pattern. Routes support URL parameters like `/{id}` and automatic parameter injection.

**Authentication**: Session-based authentication with `User` class providing static methods:
- `User::loggedIn()` - Check if user is authenticated
- `User::admin()` - Check admin privileges  
- `User::canEdit($userId)` - Check if user can edit specific user data

**Components**: All UI components extend `Component` base class and implement `template()` method. Components support nested rendering and automatic output buffering.

**Database**: Repository pattern with static instances accessible via `DB::$events` and `DB::$users`. Database connection configured in `private/credential.dev.php`.

### Directory Structure

- `src/core/` - Core framework classes (Router, Component, DB, repositories)
- `src/pages/` - Feature modules with routes and views
- `src/components/` - Reusable UI components
- `src/layout/` - Header and footer templates
- `styles/` - CSS files
- `src/scripts/` - JavaScript files

### Database Setup

Requires MySQL with extensions `pdo_mysql` and `mysqli` enabled. Database schema defined in `init.sql` with tables for users, events, and event registrations.