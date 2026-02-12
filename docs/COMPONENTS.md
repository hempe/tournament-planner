# Component System Documentation

This directory contains comprehensive documentation for the component system used in the Tournament Planner application.

## Overview

The application uses a component-based architecture where all UI elements extend from the base `Component` class. Components are composable, reusable PHP objects that render HTML.

## Core Concepts

### Base Component

All components extend `TP\Components\Component` which provides:
- `captureOutput()` - Captures content from closures, strings, or other components
- `template()` - Abstract method that renders the component's HTML
- `__toString()` - Automatically called to render the component

### Content Parameter Pattern

Most components accept content that can be:
- **String**: Direct HTML/text
- **Component**: Another component instance
- **Closure**: A function that returns content (useful for generators)
- **Array**: Multiple content items

### Generator Functions

When using a closure that yields multiple items, use `yield` instead of `echo`:

```php
new Page(function () {
    yield new Card('Title', 'Content');  // ✅ Correct
    echo '<div>...</div>';                // ❌ Won't work in generators
});
```

## Component Reference

### Layout Components
- **[Page](components/Page.md)** - Full page with navigation and layout
- **[Card](components/Card.md)** - Content card with title
- **[Div](components/Div.md)** - Generic div container

### Form Components
- **[Form](components/Form.md)** - Form wrapper with CSRF protection
- **[Table](components/Table.md)** - Data table with projection pattern
- **[Input](components/Input.md)** - Form input field
- **[IconButton](components/IconButton.md)** - Button with icon
- **[IconActionButton](components/IconActionButton.md)** - Form-submitting button with confirmation
- **[InputAction](components/InputAction.md)** - Input field with submit button

### Display Components
- **[Icon](components/Icon.md)** - FontAwesome icon
- **[Link](components/Link.md)** - Styled link element
- **[Color](components/Color.md)** - Enum for component colors

### Domain Components
- **[Calendar](components/Calendar.md)** - Monthly calendar view
- **[CalendarEvent](components/CalendarEvent.md)** - Event in calendar cell
- **[EventRegistrations](components/EventRegistrations.md)** - Event registration table

## Common Patterns

### Simple Page with Card

```php
<?= new Page(new Card(
    'Page Title',
    'Card content goes here'
)) ?>
```

### Form with Table

```php
<?= new Page(
    new Form(
        action: "/submit",
        content: new Card(
            'Form Title',
            new Table(
                columns: ['Label', 'Input'],
                items: [0], // Use distinct values, not null
                projection: fn($item) => [
                    'Username',
                    '<input type="text" name="username" required>'
                ]
            )
        )
    )
) ?>
```

### Multiple Cards on One Page

```php
<?= new Page(function () {
    yield new Card('First Card', 'Content 1');
    yield new Card('Second Card', 'Content 2');
}) ?>
```

## Common Mistakes

### ❌ Using echo in Generators

```php
new Page(function () {
    echo '<div>Test</div>';  // Won't render!
});
```

**Fix:** Use `yield` instead:

```php
new Page(function () {
    yield '<div>Test</div>';  // ✅ Works
});
```

### ❌ Wrong Table Projection Signature

```php
new Table(
    columns: ['Col1', 'Col2'],
    items: [null, null],
    projection: fn($item, $index) => [...]  // ❌ Takes 2 params
)
```

**Fix:** Projection only receives the item:

```php
new Table(
    columns: ['Col1', 'Col2'],
    items: [0, 1],  // Use distinct values
    projection: fn($item) => match($item) {
        0 => ['Row 1 Col 1', 'Row 1 Col 2'],
        1 => ['Row 2 Col 1', 'Row 2 Col 2'],
    }
)
```

### ❌ Invalid Named Parameters

```php
new Table(
    columns: ['Col1'],
    items: [1, 2, 3],
    projection: fn($item) => [$item],
    footer: 'Footer content'  // ❌ No footer parameter!
)
```

**Fix:** Check component documentation for valid parameters.

## Translation System

All user-facing text should use the `__()` function:

```php
new Card(
    __('events.title'),  // ✅ Translated
    'Content'
)
```

Never hardcode German text:

```php
new Card('Anlässe', 'Content')  // ❌ Hardcoded
```

## See Also

- [CLAUDE.md](../CLAUDE.md) - Development setup and architecture overview
- Individual component documentation in [docs/components/](components/)
