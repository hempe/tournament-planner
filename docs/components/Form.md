# Form Component

The `Form` component wraps content in an HTML form with automatic CSRF protection and hidden input support.

## Constructor Signature

```php
public function __construct(
    string $action,                               // Form submission URL
    \Closure|string|Component|array $content,     // Form content
    string $method = 'post',                      // HTTP method
    array $hiddenInputs = [],                     // Hidden field values
)
```

## Parameters

### `$action` (required)
The URL where the form will be submitted.

```php
action: "/events/new"
```

### `$content` (required)
The form's visible content. Can be any Component, string, or closure.

```php
content: new Card(
    'Form Title',
    new Table([...])
)
```

### `$method` (optional, default: 'post')
HTTP method for form submission. Usually `'post'` or `'get'`.

```php
method: 'post'
```

### `$hiddenInputs` (optional)
Associative array of hidden input name/value pairs.

```php
hiddenInputs: ['date' => '2025-10-15', 'userId' => 42]
```

## Features

### Automatic CSRF Protection

All POST forms automatically include a CSRF token:

```php
new Form(
    action: "/submit",
    content: new Card(...)
)
// Automatically adds: <input type="hidden" name="_token" value="...">
```

### Hidden Inputs

Hidden inputs are automatically rendered at the end of the form:

```php
new Form(
    action: "/events/new",
    content: new Card(...),
    hiddenInputs: ['date' => $date]
)
// Renders: <input type="hidden" name="date" value="...">
```

## Examples

### Simple Form

```php
new Form(
    action: "/login",
    content: new Card(
        'Login',
        new Table(
            columns: ['', ''],
            items: [0, 1],
            projection: fn($i) => match($i) {
                0 => [
                    'Username',
                    '<input type="text" name="username" required>'
                ],
                1 => [
                    'Password',
                    '<input type="password" name="password" required>'
                ],
            }
        )
    )
)
```

### Form with Hidden Inputs

```php
new Form(
    action: "/events/new",
    hiddenInputs: ['date' => $_GET['date']],
    content: new Card(
        'New Event',
        new Table(
            columns: ['Label', 'Input', ''],
            items: [0],
            projection: fn() => [
                'Name',
                '<input type="text" name="name" required>',
                new IconButton(
                    title: 'Save',
                    type: 'submit',
                    icon: 'fa-save',
                    color: Color::Primary
                )
            ]
        )
    )
)
```

### Multi-Step Form (Preview Pattern)

Step 1 - Input form:
```php
new Form(
    action: "/events/bulk/preview",
    content: new Card(
        'Create Bulk Events',
        new Table([...])
    )
)
```

Step 2 - Preview (reads from session):
```php
$events = $_SESSION['bulk_events'];

new Form(
    action: "/events/bulk/store",
    content: new Card(
        "Preview: " . count($events) . " events",
        new Table(
            columns: ['Date', 'Name', 'Capacity'],
            items: $events,
            projection: fn($event) => [
                $event['date'],
                $event['name'],
                $event['capacity']
            ]
        )
    )
)
```

### Form with Multiple Submit Buttons

```php
new Form(
    action: "/events/{$id}",
    content: new Card(
        'Edit Event',
        new Table(
            columns: ['', '', '', ''],
            items: [0],
            projection: fn() => [
                '<input name="name" value="' . $event->name . '">',
                '<input name="capacity" value="' . $event->capacity . '">',
                new IconButton(
                    title: 'Save',
                    type: 'submit',
                    icon: 'fa-save',
                    color: Color::Primary
                ),
                new IconActionButton(
                    actionUrl: "/events/{$id}/delete",
                    title: 'Delete',
                    icon: 'fa-trash',
                    color: Color::Accent
                )
            ]
        )
    )
)
```

## Form Submission Flow

1. User submits form
2. CSRF token automatically validated (for POST requests)
3. Controller receives request
4. Validate using `$request->validate([...])`
5. Process data or redirect with errors

Example controller:

```php
public function store(Request $request): Response
{
    $validation = $request->validate([
        new ValidationRule('name', ['required', 'string', 'max' => 255]),
        new ValidationRule('date', ['required', 'date']),
    ]);

    if (!$validation->isValid) {
        flash('error', $validation->getErrorMessages());
        return Response::redirect('/events/new');
    }

    $data = $request->getValidatedData();
    // Process $data...

    return Response::redirect('/events');
}
```

## Common Mistakes

### ❌ Forgetting CSRF Token for POST

The Form component handles this automatically - don't add it manually:

```php
// ❌ Don't do this - it's automatic
hiddenInputs: ['_token' => csrf_token()]
```

### ❌ Wrong Method Name

```php
method: 'POST'  // ❌ Use lowercase
```

**Fix:**
```php
method: 'post'  // ✅ Lowercase
```

### ❌ Missing Action URL

```php
new Form(
    content: new Card(...)  // ❌ Missing action parameter
)
```

**Fix:**
```php
new Form(
    action: "/submit",  // ✅ Required
    content: new Card(...)
)
```

## See Also

- [Card](Card.md)
- [Table](Table.md)
- [IconButton](IconButton.md)
- [IconActionButton](IconActionButton.md)
- [Validation](../VALIDATION.md)
