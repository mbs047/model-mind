# Blade Rendering

Add the default widget to a Blade layout:

```blade
@modelMindStyles
@modelMindModal
@modelMindScripts
```

Or render all three parts at once:

```blade
@modelMind
```

The split directives are useful when your layout needs styles in the head and scripts before the closing body tag:

```blade
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @modelMindStyles
</head>
<body>
    {{ $slot }}

    @modelMindModal
    @modelMindScripts
</body>
```

## Anonymous Component

The default modal can also be rendered as an anonymous component:

```blade
<x-model-mind::modal />
```

The package directives are preferred for complete installations because they keep modal, styles, and scripts together.

## Custom Views

For custom Blade files, see [Customizing the Chat Modal](customizing-the-modal.md).
