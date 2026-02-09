# Golf El Faro - Zero Dependency Version

A modern, secure, and production-ready golf event management application built with **pure PHP 8.1+** - no external dependencies required.

## ✨ Features

### Core Functionality
- **Event Management**: Create, edit, and manage golf events
- **User Registration**: Secure user authentication and registration  
- **Event Registration**: Users can register/unregister for events with comments
- **Admin Panel**: Administrative interface for event and user management
- **Calendar View**: Monthly calendar with event overview

### Production-Ready Features (Zero Dependencies)
- **Strong Typing**: Full PHP 8.1+ type declarations throughout
- **Security**: CSRF protection, XSS prevention, password hashing, rate limiting
- **Internationalization**: Multi-language support (German, English)
- **Logging**: Comprehensive error and access logging
- **Configuration Management**: Environment-based configuration
- **Input Validation**: Robust input validation and sanitization
- **Response Types**: Strongly-typed HTTP responses
- **Middleware System**: Flexible request/response processing
- **PSR Standards**: PSR-4 autoloading (built-in, no Composer needed)

## 🚀 Quick Start

### Requirements
- **PHP 8.1+** (that's it!)
- **MySQL 8.0+** or MariaDB 10.6+
- **Web server** (Apache/Nginx)

### Installation

1. **Clone the repository**:
   ```bash
   git clone <repository> golf-el-faro
   cd golf-el-faro
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your database settings
   ```

3. **Set up database**:
   ```bash
   mysql -u root -p < init.sql
   ```

4. **Set permissions**:
   ```bash
   chmod -R 755 src/ styles/ resources/
   chmod -R 777 logs/ cache/
   chmod 600 .env
   ```

5. **Start the application**:
   ```bash
   php -S localhost:5000 -c php.ini
   ```

That's it! No package managers, no dependency downloads, no complex build processes.

## 📁 Simple Structure

```
golf-el-faro/
├── src/                          # Application source (pure PHP)
│   ├── Core/                     # Framework core classes
│   ├── Components/               # UI components  
│   ├── Models/                   # Data models
│   └── helpers.php               # Global functions
├── resources/lang/               # Translation files
├── config/                       # Environment configurations
├── styles/                       # CSS files
├── docs/                         # Documentation
├── bootstrap.php                 # Simple autoloader
├── index.php                     # Application entry point
└── .env.example                  # Configuration template
```

## 🛡️ Security (Built-in)

All security features are implemented in pure PHP:

- **CSRF Protection**: `src/Core/Security.php`
- **XSS Prevention**: Automatic output escaping
- **Input Validation**: `src/Core/Validator.php`
- **Rate Limiting**: Session-based rate limiting
- **Password Security**: Argon2ID hashing
- **Session Security**: Secure session configuration

Example usage:
```php
// Automatic CSRF protection
$token = csrf_token();

// Automatic XSS protection  
echo e($userInput);

// Input validation
$validation = $request->validate([
    new ValidationRule('email', ['required', 'email']),
]);
```

## 🌍 Internationalization (Built-in)

Multi-language support without external libraries:

```php
// In your views
echo __('events.title');           // "Events" or "Anlässe"
echo __('auth.welcome', ['name' => $user]); // Parameter substitution
```

Add new languages by creating files in `resources/lang/`:
- `resources/lang/de_CH.php` (German)
- `resources/lang/en_US.php` (English)
- `resources/lang/fr_FR.php` (French) - just add this file!

## 🏗️ Architecture

### Autoloading (No Composer)
The app includes a built-in PSR-4 autoloader:

```php
// bootstrap.php automatically loads classes
new GolfElFaro\Core\Router();     // Loads src/Core/Router.php
new GolfElFaro\Models\User();     // Loads src/Models/User.php
```

### Routing System
```php
// Define routes in index.php
$router->get('/events', [EventController::class, 'index']);
$router->post('/events', [EventController::class, 'store'], [new AuthMiddleware()]);
```

### Component System
```php
// Reusable UI components
echo new Card(
    title: __('events.title'),
    content: new Table($headers, $rows)
);
```

## 🔧 Development

### Local Development
```bash
# Start development server
php -S localhost:5000 -c php.ini

# Watch logs
tail -f logs/app.log

# Check syntax
find src/ -name "*.php" -exec php -l {} \;
```

### Code Quality (Built-in Tools)
```bash
# PHP has built-in linting
find src/ -name "*.php" -exec php -l {} \;

# Manual code review using IDE or editor
# All code follows PSR-12 standards
```

## 📖 Documentation

- **[Components](docs/components.md)**: Component system guide
- **[Routing](docs/routing.md)**: Routing and middleware
- **[Deployment](docs/deployment.md)**: Production deployment  
- **[Migration](MIGRATION.md)**: Upgrade from legacy version

## 🚀 Production Deployment

### Production Checklist
- [ ] Environment configuration set to production
- [ ] SSL certificate installed and configured
- [ ] Database secured with strong credentials
- [ ] File permissions set correctly (`755` for code, `777` for logs/cache)
- [ ] Error reporting configured (logs only, no display)
- [ ] Security headers configured in web server
- [ ] Backup strategy implemented
- [ ] Monitoring and health checks active

### Production Setup
1. **Copy files to server**:
   ```bash
   rsync -av --exclude='logs/*' --exclude='cache/*' golf-el-faro/ user@server:/var/www/golf-el-faro/
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   # Set APP_ENV=production
   # Use strong database credentials
   # Configure secure session settings
   ```

3. **Set up SSL certificate**:
   ```bash
   # Example with Let's Encrypt
   certbot --nginx -d yourdomain.com
   ```

4. **Configure web server** (see `docs/deployment.md` for full configuration):
   ```nginx
   # Nginx example
   server {
       listen 443 ssl http2;
       server_name yourdomain.com;
       root /var/www/golf-el-faro;
       
       # Security headers
       add_header X-Content-Type-Options nosniff;
       add_header X-Frame-Options DENY;
       add_header X-XSS-Protection "1; mode=block";
   }
   ```

5. **Set permissions**:
   ```bash
   chmod -R 755 src/ styles/ resources/
   chmod -R 777 logs/ cache/
   chmod 600 .env
   ```

6. **Health check**:
   ```bash
   curl https://yourdomain.com/health
   ```

### Production vs Development

| Feature | Development | Production |
|---------|-------------|------------|
| Error Display | On screen | Logs only |
| Debug Mode | Enabled | Disabled |
| Session Lifetime | 2 hours | 1 hour |
| Log Level | DEBUG | WARNING |
| SSL Required | Optional | Required |

### No Build Process Needed
- No package installation
- No asset compilation  
- No complex deployment scripts
- Just upload and run!

## ⚡ Performance

### Optimizations (Built-in)
- **OPcache**: PHP's built-in bytecode cache
- **Session optimization**: Efficient session handling
- **Lazy loading**: Classes load only when needed
- **Minimal footprint**: No external dependencies to load

### Production PHP Settings
```ini
# php.ini optimizations
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
session.gc_maxlifetime=3600
```

## 🤔 Why Zero Dependencies?

### Benefits
✅ **Security**: No third-party vulnerabilities  
✅ **Simplicity**: Easy to understand and maintain
✅ **Performance**: No bloat from unused features
✅ **Stability**: No breaking changes from updates
✅ **Deployment**: Simple file copy deployment
✅ **Longevity**: Works with any PHP 8.1+ installation

### What You Get Anyway
- Modern PHP 8.1+ features (enums, match expressions, typed properties)
- Strong typing throughout
- Professional architecture patterns
- Comprehensive security measures
- International language support
- Production-ready logging and monitoring

## 🛠️ Extending the App

### Adding New Features
All core systems are extensible:

```php
// Add new validation rule
class CustomValidator extends Validator {
    // Add your custom validation logic
}

// Add new middleware
class CustomMiddleware implements MiddlewareInterface {
    // Add your middleware logic
}

// Add new component
class CustomComponent extends Component {
    // Add your UI component
}
```

### Adding New Languages
```php
// Create resources/lang/es_ES.php
return [
    'events' => [
        'title' => 'Eventos',
        // ... more translations
    ],
];
```

## 📊 What's Different From Composer-Based Apps

| Composer Apps | This App |
|---|---|
| `composer install` | Just upload files |
| vendor/ directory (100+ MB) | Pure source code (~2 MB) |
| Package updates & conflicts | Stable, no external changes |
| Complex dependency tree | Simple, predictable structure |
| Security patches for packages | Only PHP core security updates |

## 🤝 Contributing

1. Write clean, typed PHP code
2. Follow PSR-12 coding standards  
3. Update documentation
4. Test thoroughly
5. No external dependencies!

## 📄 License

MIT License - Use freely in commercial and personal projects.

---

**Simple. Secure. Dependency-Free.** 🎯