# Card Component

The `Card` component renders a styled card with a title and content area.

## Constructor Signature

```php
public function __construct(
    \Closure|string|Component|array $title,    // Card title
    \Closure|string|Component|array $content,  // Card content
    \Closure|string|Component|array $class = '',   // Additional CSS classes
    \Closure|string|Component|array $style = '',   // Additional inline styles
)
```

## Parameters

### `$title` (required)
The card's title. Can be:
- **String**: Plain text or HTML
- **Component**: Another component (e.g., Icon, Button)
- **Array**: Multiple components/strings

```php
title: 'Simple Title'
// or
title: 'Events (' . $count . ')'
// or
title: ['Title', new IconButton(...)]
```

### `$content` (required)
The card's main content. Usually a Table or other components.

```php
content: new Table(...)
// or
content: '<p>Some HTML content</p>'
// or
content: [new Table(...), new Div(...)]
```

### `$class` (optional)
Additional CSS classes to add to the card.

```php
class: 'custom-card'
```

### `$style` (optional)
Additional inline styles.

```php
style: 'width: 500px; margin: 2rem auto;'
```

## Examples

### Simple Card

```php
new Card(
    'Card Title',
    'This is the card content.'
)
```

### Card with Table

```php
new Card(
    'Users',
    new Table(
        columns: ['Name', 'Email'],
        items: $users,
        projection: fn($user) => [$user->name, $user->email]
    )
)
```

### Card with Button in Title

```php
new Card(
    'Users' . new IconButton(
        title: 'Add User',
        type: 'button',
        icon: 'fa-user-plus',
        color: Color::None,
        onClick: "window.location.href='/users/new'"
    ),
    new Table([...])
)
```

### Card with Custom Styling

```php
new Card(
    title: 'Centered Card',
    content: 'Content here',
    style: 'width: min(90vw, 400px); margin: 0 auto;'
)
```

### Card with Multiple Content Items

```php
new Card(
    'Dashboard',
    [
        '<p>Welcome back!</p>',
        new Table([...]),
        '<div class="stats">...</div>'
    ]
)
```

### Conditional Card Content

```php
new Card(
    'Event: ' . $event->name,
    function () use ($event, $registrations) {
        yield new Table([...]); // Event details

        if (!empty($registrations)) {
            yield new Table([...]); // Registrations
        }

        if (User::admin()) {
            yield new Table([...]); // Admin actions
        }
    }
)
```

## Layout

Cards are rendered with the following structure:

```html
<div class="card">
    <div class="card-title">Title</div>
    <div class="card-content" style="display: flex; flex-direction: column;">
        Content
    </div>
</div>
```

The `card-content` uses flexbox column layout, so child elements stack vertically.

## Styling

Cards support several style variations via CSS:

- `.card` - Default card style
- `.card.primary` - Primary colored card
- `.card.accent` - Accent colored card

Example with colored card:

```php
new Card(
    title: 'Error',
    content: $errorMessage,
    class: 'accent'
)
```

## Common Patterns

### Form in Card

```php
new Form(
    action: "/submit",
    content: new Card(
        'Form Title',
        new Table([...])
    )
)
```

### Multiple Cards in Page

```php
new Page(function () {
    yield new Card('Card 1', 'Content 1');
    yield new Card('Card 2', 'Content 2');
})
```

### Card with Translation

```php
new Card(
    __('events.title'),  // Translated title
    new Table([...])
)
```

## Common Mistakes

### ❌ Forgetting Content Parameter

```php
new Card('Title')  // ❌ Missing content parameter
```

**Fix:**
```php
new Card('Title', 'Content')  // ✅ Both parameters
```

### ❌ Using Hardcoded Text

```php
new Card('Anlässe', ...)  // ❌ Hardcoded German
```

**Fix:**
```php
new Card(__('events.title'), ...)  // ✅ Translated
```

## See Also

- [Page](Page.md)
- [Table](Table.md)
- [Form](Form.md)
- [COMPONENTS.md](../COMPONENTS.md)
