# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Windsurf MVC is a PHP 8+ web application built with:
- **Slim Framework v4** for routing and HTTP handling
- **Eloquent ORM** (Illuminate Database) for database operations
- **Twig** for templating
- **PSR-4 autoloading** with `App\` namespace mapped to `app/` directory

## Development Setup

### Install Dependencies
```bash
composer install
```

### Environment Configuration
Copy `.env.example` to `.env` (already exists in this project) and configure:
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for database connection
- `APP_DEBUG=true` for development, `false` for production
- `APP_ENV=development` or `production`

### Run the Application

**Option 1: PHP Built-in Server (Quick Testing)**
```bash
php -S localhost:8080 -t public
```
Access at `http://localhost:8080/`

**Option 2: Laragon/Apache (Recommended)**
- Requires `mod_rewrite` enabled
- Requires `AllowOverride All` in VirtualHost configuration
- `.htaccess` files handle URL rewriting

## Architecture

### Application Bootstrap Flow

1. **Entry Point**: `public/index.php`
   - Loads Composer autoloader
   - Imports application configuration from `config/app.php`
   - Runs the Slim app

2. **Application Configuration**: `config/app.php`
   - Creates Slim app instance
   - Configures error middleware (debug mode controlled by `APP_DEBUG`)
   - Initializes Eloquent ORM with database credentials from `.env`
   - Sets up Twig templating with views in `resources/views/`
   - Adds middleware in this order:
     1. TwigMiddleware
     2. Body parsing middleware
     3. **CentinelaMiddleware** (request/response logging - see below)
     4. **CsrfMiddleware** (CSRF protection for forms - see below)
     5. Routing middleware
     6. **CorsMiddleware** (secure CORS with whitelist - see below)
   - Includes routes from `routes/web.php`
   - Returns configured `$app` instance

3. **Routes**: `routes/web.php`
   - Defines application routes
   - Uses controller class methods (e.g., `HomeController::class . ':index'`)
   - Catch-all route for 404 handling

### Directory Structure

```
app/
├── Controllers/        # HTTP controllers
│   ├── BaseController.php   # Abstract base with validation + CSRF helpers
│   ├── HomeController.php
│   ├── UserController.php   # CRUD example with validation
│   └── FormController.php   # Form examples with CSRF protection
├── Middleware/         # Custom middleware
│   ├── CentinelaMiddleware.php
│   ├── CorsMiddleware.php
│   └── CsrfMiddleware.php
├── Models/             # Eloquent models
│   ├── BaseModel.php   # Abstract base model
│   ├── User.php
│   └── Post.php
├── Services/
│   ├── Centinela/      # Centinela service classes
│   │   ├── CentinelaConfig.php
│   │   ├── CentinelaPayloadBuilder.php
│   │   ├── CentinelaFileLogger.php
│   │   └── CentinelaDatabaseLogger.php
│   ├── Csrf/           # CSRF protection
│   │   ├── CsrfProtection.php
│   │   └── CsrfTwigExtension.php
│   └── Session/
│       └── SessionManager.php
└── Validation/         # Input validation
    ├── Validator.php       # Validation wrapper with helpers
    ├── ValidationException.php
    └── README.md          # Validation documentation

config/
└── app.php            # Main application bootstrap

database/
├── migrations/         # Database migrations
│   ├── 2024_01_01_000001_create_users_table.php
│   └── 2024_01_01_000002_create_posts_table.php
├── seeders/           # Database seeders (future)
├── MigrationRunner.php # Migration system
└── README.md          # Migration documentation

routes/
├── web.php            # Web routes definition
└── api.php            # API routes (prefixed with /api)

resources/views/       # Twig templates
├── layouts/           # Layout templates
├── forms/             # Form examples
│   ├── contact.twig   # Contact form with CSRF
│   └── register.twig  # Registration form with CSRF
├── errors/            # Error pages
│   ├── 404.twig       # Custom 404 page
│   └── 500.twig       # Custom 500 page
├── home.twig
└── welcome.twig

public/
├── index.php          # Front controller
└── .htaccess          # URL rewriting rules

centinela/             # Request/response logs (auto-created)

migrate                # CLI tool for migrations
```

### Controllers

All controllers should extend `App\Controllers\BaseController` which provides:

**Response Helpers**:
- `render(Request $request, Response $response, string $template, array $data = []): Response`
  - Renders Twig templates
- `json(Response $response, mixed $data, int $status = 200): Response`
  - Returns JSON responses with proper headers
- `notFound(Response $response, ?string $message = null, array $data = []): Response`
  - Returns 404 JSON response
- `error(Response $response, string $message, int $status = 500, array $data = []): Response`
  - Returns error JSON response with appropriate status code

**Validation Helpers**:
- `validate($data, $rules)` - Validate and throw exception on failure
- `check($data, $rules)` - Validate and return errors array
- `getBody($request)` - Get parsed request body
- `input($request, $key, $default)` - Get input from body or query
- `validationError($response, $e)` - Return formatted validation error (422)

**CSRF Helpers**:
- `getCsrf($request)` - Get CSRF protection instance
- `getCsrfTokenName($request)` - Get CSRF token field name
- `getCsrfTokenValue($request)` - Get CSRF token value
- `getCsrfInput($request)` - Get CSRF hidden input HTML

### Middleware: CentinelaMiddleware (Refactored Architecture)

**Purpose**: Comprehensive request/response logging middleware for debugging and monitoring.

**Architecture**: The middleware follows the Single Responsibility Principle and is divided into specialized classes:

```
app/
├── Middleware/
│   └── CentinelaMiddleware.php      # Main coordinator (76 lines)
└── Services/
    └── Centinela/
        ├── CentinelaConfig.php       # Configuration management
        ├── CentinelaPayloadBuilder.php # Builds log payloads
        ├── CentinelaFileLogger.php    # File-based logging
        └── CentinelaDatabaseLogger.php # Database logging
```

**Key Classes**:
- `CentinelaConfig`: Manages all configuration settings, validates and normalizes values
- `CentinelaPayloadBuilder`: Extracts and formats request/response data into structured payloads
- `CentinelaFileLogger`: Handles JSON file creation in the `centinela/` directory
- `CentinelaDatabaseLogger`: Persists logs to database with auto-migration support
- `CentinelaMiddleware`: Coordinates the logging process, delegates to specialized loggers

**Configuration** (via `.env`):
- `CENTINELA_ENABLED=true|false` - Enable/disable logging
- `CENTINELA_DIR=/path/to/logs` - Log directory (default: `./centinela/`)
- `CENTINELA_MAX_BODY_BYTES=16384` - Max request body size to log
- `CENTINELA_OUTPUT=file|db|both|all` - Output destination
- `CENTINELA_REDACT_HEADERS=authorization,cookie,set-cookie` - Headers to redact in request logs
- `CENTINELA_REDACT_RESPONSE_HEADERS=set-cookie` - Headers to redact in response logs
- `CENTINELA_DB_ENABLED=true|false` - Enable database logging
- `CENTINELA_DB_TABLE=centinela_logs` - Database table name
- `CENTINELA_DB_AUTO_MIGRATE=true|false` - Auto-create table if missing

**Logged Data**:
- Request ID, timestamp, duration
- HTTP method, URI, headers (with redaction)
- Request body (raw + decoded)
- Response status, headers (with redaction)
- Matched route pattern

**Output**: JSON files in `centinela/` directory (format: `YYYYMMDD_HHMMSS_microseconds_hash.json`)

### Middleware: CorsMiddleware

**Purpose**: Secure Cross-Origin Resource Sharing (CORS) middleware with origin whitelist validation.

**Features**:
- Origin whitelist validation (no more `Access-Control-Allow-Origin: *`)
- Supports wildcard subdomains (`*.example.com`)
- Handles preflight OPTIONS requests automatically
- Configurable headers, methods, and max-age
- Optional credentials support (requires specific origin, not wildcard)

**Configuration** (via `.env`):
```bash
# Comma-separated list of allowed origins (no spaces after commas, or use quotes)
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
# Or use quotes if you want spaces:
CORS_ALLOWED_METHODS="GET, POST, PUT, DELETE, PATCH, OPTIONS"
CORS_ALLOWED_HEADERS="X-Requested-With, Content-Type, Accept, Origin, Authorization"
CORS_MAX_AGE=86400
CORS_ALLOW_CREDENTIALS=false
```

**Security Features**:
- Only returns the requesting origin if it's in the whitelist
- If origin not in whitelist, returns first allowed origin (browser will block)
- Supports exact match and wildcard subdomain matching
- Credentials only allowed with specific origins (not `*`)

**Wildcard Subdomain Examples**:
- `*.example.com` matches: `https://app.example.com`, `https://api.example.com`
- Does NOT match: `https://example.com` (use explicit entry for apex domain)

### Database (Eloquent ORM)

Eloquent is configured globally in `config/app.php` using Capsule:
- Connection details from `.env` (`DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, etc.)
- Models are in `app/Models/`
- All models extend `App\Models\BaseModel`
- Migration system included with CLI tool

#### Models

**BaseModel** (`app/Models/BaseModel.php`):
- Abstract base class for all models
- Provides common methods: `getAll()`, `findOrFail404()`, `exists()`
- Enforces consistent timestamp and date formatting
- All custom models should extend this class

**Example Models**:
- `User` - User model with email verification
- `Post` - Blog post model with status management and user relationship

**Model Pattern**:
```php
<?php
namespace App\Models;

class YourModel extends BaseModel
{
    protected $table = 'your_table';

    protected $fillable = ['field1', 'field2'];

    protected $hidden = ['password'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
```

#### Migrations

**CLI Tool**: `php migrate [command]`

Commands:
- `php migrate up` - Run pending migrations
- `php migrate status` - Show migration status
- `php migrate rollback` - Rollback last batch
- `php migrate reset` - Rollback all migrations (with confirmation)
- `php migrate fresh` - Reset + migrate (with confirmation)

**Migration Files**: Located in `database/migrations/`
- Format: `YYYY_MM_DD_HHMMSS_description.php`
- Returns a closure that accepts `$direction` parameter (`'up'` or `'down'`)

**Creating Migrations**:
See `database/README.md` for detailed migration documentation and examples.

### Views (Twig)

- Templates located in `resources/views/`
- Cache enabled in production (`APP_ENV=production`), disabled in development
- Debug extension enabled when `APP_DEBUG=true`
- Access via `Twig::fromRequest($request)->render($response, 'template.twig', $data)`
- Or use `BaseController::render()` helper

### Input Validation

**Library**: Respect\Validation (integrated)

**Location**: `app/Validation/`

**Components**:
- `Validator` - Wrapper with helper methods for common validations
- `ValidationException` - Custom exception with error details
- `BaseController` - Includes `validate()` and `check()` helpers

**Basic Usage**:
```php
use App\Validation\ValidationException;
use App\Validation\Validator;

use Respect\Validation\Validator as V;

try {
    $this->validate($data, [
        'name' => Validator::string(3, 255),
        'email' => V::allOf(
            Validator::email(),
            Validator::unique('users', 'email')
        ),
        'password' => Validator::password(8),
        'age' => Validator::integer(true, 18, 120),
    ]);

    // Validation passed

} catch (ValidationException $e) {
    return $this->validationError($response, $e);
}
```

**Common Validators**:
- `Validator::string($min, $max, $required)` - String with length constraints
- `Validator::email($required)` - Email validation
- `Validator::integer($required, $min, $max)` - Integer with range
- `Validator::password($minLength)` - Strong password (uppercase, lowercase, number)
- `Validator::unique($table, $column, $excludeId)` - Database uniqueness check
- `Validator::exists($table, $column)` - Database existence check (for foreign keys)
- `Validator::in($values, $required)` - Enum validation
- `Validator::date($format, $required)` - Date validation
- `Validator::url($required)` - URL validation

**Helper Methods in BaseController**:
- `validate($data, $rules)` - Validate and throw exception on failure
- `check($data, $rules)` - Validate and return errors array
- `getBody($request)` - Get parsed request body
- `input($request, $key, $default)` - Get input from body or query
- `validationError($response, $e)` - Return formatted validation error (422)

**Example**: See `app/Controllers/UserController.php` for complete CRUD examples with validation

**Documentation**: See `app/Validation/README.md` for comprehensive guide

### CSRF Protection

**Purpose**: Protect forms from Cross-Site Request Forgery (CSRF) attacks using token-based validation.

**Components**:
- `SessionManager` - Manages PHP sessions with secure settings
- `CsrfProtection` - Generates and validates CSRF tokens
- `CsrfMiddleware` - Automatically validates POST/PUT/DELETE/PATCH requests
- `CsrfTwigExtension` - Provides Twig functions for token rendering
- `BaseController` - Includes CSRF helper methods

**Configuration** (via `.env`):
```bash
# Enable/disable CSRF protection
CSRF_ENABLED=true

# Paths to exclude from CSRF validation (comma-separated, supports wildcards)
CSRF_EXCLUDED_PATHS=/api/*

# Session configuration
SESSION_NAME=windsurf_session
SESSION_SECRET=your_secret_key_here
SESSION_SECURE=false  # Set to true in production with HTTPS
```

**How It Works**:
1. `CsrfProtection` generates unique tokens stored in session
2. Tokens expire after 1 hour, up to 10 recent tokens maintained
3. `CsrfMiddleware` validates tokens on state-changing requests (POST/PUT/DELETE/PATCH)
4. API routes can be excluded using wildcards (e.g., `/api/*`)
5. Failed validation returns 403 JSON response

**Twig Integration**:

The `CsrfTwigExtension` provides these functions:

```twig
{# Hidden input field (most common) #}
<form method="POST" action="/contact">
    {{ csrf_input()|raw }}
    {# Renders: <input type="hidden" name="csrf_token" value="..."> #}

    <!-- Your form fields -->
</form>

{# Meta tags for AJAX requests #}
<head>
    {{ csrf_meta()|raw }}
    {# Renders:
       <meta name="csrf-token-name" content="csrf_token">
       <meta name="csrf-token-value" content="abc123...">
    #}
</head>

{# Individual token components #}
{{ csrf_token_name() }}   {# Returns: "csrf_token" #}
{{ csrf_token_value() }}  {# Returns: "abc123..." #}

{# Global variables (also available) #}
{{ csrf_token_name }}   {# Same as csrf_token_name() #}
{{ csrf_token_value }}  {# Same as csrf_token_value() #}
```

**Controller Usage**:

```php
use App\Controllers\BaseController;
use App\Validation\Validator;

class FormController extends BaseController
{
    public function submitForm(Request $request, Response $response): Response
    {
        // CSRF validation happens automatically in middleware
        // Just handle your form logic

        $data = $this->getBody($request);

        $this->validate($data, [
            'name' => Validator::string(3, 100),
            'email' => Validator::email(),
        ]);

        // Process form...

        return $this->render($request, $response, 'forms/success.twig');
    }
}
```

**AJAX Requests**:

```javascript
// Read CSRF token from meta tags
const tokenName = document.querySelector('meta[name="csrf-token-name"]').content;
const tokenValue = document.querySelector('meta[name="csrf-token-value"]').content;

// Include in fetch requests
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        [tokenName]: tokenValue,
        // ...your data
    })
});
```

**Excluding Routes from CSRF**:

In `.env`, use wildcards to exclude API routes:
```bash
# Single path
CSRF_EXCLUDED_PATHS=/webhook

# Multiple paths (comma-separated)
CSRF_EXCLUDED_PATHS=/api/*,/webhook,/public-form

# All API routes (recommended)
CSRF_EXCLUDED_PATHS=/api/*
```

**Security Features**:
- Tokens expire after 1 hour
- Session token storage (not cookies)
- Up to 10 recent tokens maintained for multi-tab support
- Automatic validation on state-changing methods
- Secure session settings (httponly, samesite=Lax)

**Example Forms**:
- Contact form: `http://localhost:8080/contact`
- Registration form: `http://localhost:8080/register`

See `app/Controllers/FormController.php` and `resources/views/forms/` for complete examples.

## Routing Patterns

Routes use Slim v4 syntax in `routes/web.php` and `routes/api.php`:

```php
// Controller method
$app->get('/', HomeController::class . ':index');

// Closure with route parameters
$app->get('/welcome/{name}', function (Request $request, Response $response, array $args) {
    // ...
});

// Route groups (for API)
$app->group('/api/users', function ($group) {
    $group->get('', UserController::class . ':index');
    $group->get('/{id}', UserController::class . ':show');
});
```

**Error Handling (404 & 500)**:

The application has custom error handlers for both 404 and 500 errors:

**404 Not Found** (catch-all in `config/app.php`):
- **API routes** (`/api/*`) or **JSON requests** (`Accept: application/json`): Returns JSON error
- **Web routes**: Renders `errors/404.twig` template

```json
// API 404 response
{
    "error": "Not Found",
    "message": "The requested API endpoint does not exist",
    "path": "/api/nonexistent",
    "method": "GET"
}
```

**500 Internal Server Error** (custom error handler):
- **Development** (`APP_DEBUG=true`): Shows error details (message, file, line, trace)
- **Production** (`APP_DEBUG=false`): Hides sensitive details
- **API requests**: Returns JSON with error information
- **Web requests**: Renders `errors/500.twig` template

```json
// API 500 response (debug mode)
{
    "error": "Internal Server Error",
    "message": "Division by zero",
    "file": "/path/to/file.php",
    "line": 42,
    "trace": ["..."]
}
```

**Test routes**:
- `GET /test-error` - Triggers 500 error (web)
- `GET /api/test-error` - Triggers 500 error (API)

## Security Considerations

- `.htaccess` in root blocks direct access to `vendor/` and sensitive files
- `public/` is the web root, keeping application code outside document root
- **CSRF Protection**: All forms protected with token-based CSRF validation (can be disabled per-route)
- **CORS whitelist**: Only specified origins in `CORS_ALLOWED_ORIGINS` are allowed (configured via `.env`)
- **Input Validation**: All user input validated using Respect\Validation library
- **Password Hashing**: Passwords hashed with `PASSWORD_DEFAULT` (bcrypt)
- **Session Security**: Sessions configured with httponly, samesite=Lax settings
- Sensitive headers (authorization, cookies) are redacted in Centinela logs
- Set `APP_DEBUG=false` in production to disable error details
- Update `CORS_ALLOWED_ORIGINS` in production to include only trusted domains
- Set `SESSION_SECURE=true` in production when using HTTPS
- Never commit `.env` file with real credentials to version control

## Common Tasks

### Add a New Route
1. Define route in `routes/web.php`
2. Create controller method or closure handler
3. Return response using `render()` for HTML or `json()` for API responses

### Create a New Controller
1. Create class in `app/Controllers/`
2. Extend `App\Controllers\BaseController`
3. Define public methods with signature: `(Request $request, Response $response, array $args): Response`

### Create a New Model
1. Create class in `app/Models/`
2. Extend `App\Models\BaseModel`
3. Define `$table`, `$fillable`, `$hidden`, `$casts` properties
4. Add custom methods as needed (see `User` and `Post` models for examples)
5. Create corresponding migration (see below)

### Create a Migration
1. Create file in `database/migrations/` with format: `YYYY_MM_DD_HHMMSS_description.php`
2. Use the template from `database/README.md`
3. Implement `up` (create/modify) and `down` (rollback) logic
4. Run `php migrate up` to execute
5. Verify with `php migrate status`

**Example workflow**:
```bash
# Create migration file: database/migrations/2024_01_15_100000_create_products_table.php
# Edit the migration file with table schema
php migrate status    # Check it appears as pending
php migrate up        # Run the migration
php migrate status    # Verify it ran successfully
```

### Create a Form with CSRF Protection
1. Create controller method in `app/Controllers/` (extend `BaseController`)
2. Create Twig template in `resources/views/`
3. Add `{{ csrf_input()|raw }}` inside `<form>` tag
4. Add routes (GET to show form, POST to handle submission)
5. Use `$this->validate()` in controller to validate input

**Example**:
```twig
{# resources/views/forms/contact.twig #}
<form method="POST" action="/contact">
    {{ csrf_input()|raw }}

    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <textarea name="message" required></textarea>

    <button type="submit">Send</button>
</form>
```

```php
// app/Controllers/FormController.php
public function submitContactForm(Request $request, Response $response): Response
{
    $data = $this->getBody($request);

    try {
        $this->validate($data, [
            'name' => Validator::string(3, 100),
            'email' => Validator::email(),
            'message' => Validator::string(10, 1000),
        ]);

        // Process form (send email, save to DB, etc.)

        return $this->render($request, $response, 'forms/contact.twig', [
            'success' => 'Message sent successfully!',
        ]);
    } catch (ValidationException $e) {
        return $this->render($request, $response, 'forms/contact.twig', [
            'errors' => $e->getErrors(),
            'old' => $data,
        ]);
    }
}
```

```php
// routes/web.php
$app->get('/contact', FormController::class . ':showContactForm');
$app->post('/contact', FormController::class . ':submitContactForm');
```

### Disable Request Logging
Set `CENTINELA_ENABLED=false` in `.env` to disable CentinelaMiddleware logging.

### Disable CSRF Protection
Set `CSRF_ENABLED=false` in `.env` to disable CSRF validation globally (not recommended in production).
