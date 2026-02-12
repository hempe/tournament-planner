# Page Component

The `Page` component is the top-level layout wrapper that includes navigation, theme toggle, and user authentication UI.

## Constructor Signature

```php
public function __construct(
    callable|string|Component $content
)
```

## Parameters

### `$content` (required)
The main page content. Can be:
- **String**: Direct HTML
- **Component**: Single component (usually a Card)
- **Callable**: Generator function for multiple components

## Features

The Page component automatically provides:
- Navigation bar with back button
- Admin-only navigation (Events, Users)
- Theme toggle (dark/light mode)
- Logout button (if authenticated)
- Responsive layout

## Examples

### Single Card Page

```php
<?= new Page(new Card(
    'Page Title',
    'Page content goes here'
)) ?>
```

### Multiple Cards Page

```php
<?= new Page(function () {
    yield new Card('First Card', 'Content 1');
    yield new Card('Second Card', 'Content 2');
    yield new Card('Third Card', 'Content 3');
}) ?>
```

### Complex Page with Conditional Content

```php
<?= new Page(function () use ($event) {
    // Main event card
    yield new Card(
        $event->name,
        new Table([...])
    );

    // Admin-only card
    if (User::admin()) {
        yield new Card(
            'Admin Actions',
            new Table([...])
        );
    }

    // Registrations card
    if (!empty($registrations)) {
        yield new Card(
            'Registrations',
            new Table([...])
        );
    }
}) ?>
```

### Page with Button Above Card

```php
<?= new Page(function () {
    // Button outside card
    if (User::admin()) {
        yield '<div style="margin-bottom: 1rem;">
            <a href="/events/new" class="button button--primary">
                <i class="fa fa-plus"></i> Add Event
            </a>
        </div>';
    }

    // Main content card
    yield new Card(
        'Events',
        new Table([...])
    );
}) ?>
```

## Common Mistakes

### ❌ Using Echo in Generator

```php
new Page(function () {
    echo new Card('Title', 'Content');  // ❌ Won't render!
})
```

**Fix:** Use `yield` instead:
```php
new Page(function () {
    yield new Card('Title', 'Content');  // ✅ Works
})
```

### ❌ Forgetting to Echo Component

```php
// In a view file
new Page(new Card(...));  // ❌ Not rendered
```

**Fix:** Use `<?= ?>` to echo:
```php
<?= new Page(new Card(...)) ?>  // ✅ Rendered
```

### ❌ Mixing Echo and Yield

```php
new Page(function () {
    echo '<div>Test</div>';       // ❌ Won't render
    yield new Card('Card', '');   // ✅ Will render
})
```

**Fix:** Use only `yield`:
```php
new Page(function () {
    yield '<div>Test</div>';      // ✅ Will render
    yield new Card('Card', '');   // ✅ Will render
})
```

## Navigation Behavior

The Page component provides different navigation based on context:

- **Home page**: Shows logo instead of back button
- **Authenticated users**: Can access all pages
- **Admin users**: See additional "Events" and "Users" nav buttons
- **Non-authenticated**: Only see login page

## Theme Support

The Page component includes automatic dark/light theme toggle:
- Theme preference stored in `localStorage`
- Falls back to system preference
- Theme persists across sessions

## See Also

- [Card](Card.md)
- [COMPONENTS.md](../COMPONENTS.md)
