# UI, Themes, and Positioning

The default design is Tailwind-friendly and supports standard light and dark variants.

```env
MODEL_MIND_BRAND_MARK=MBS
MODEL_MIND_NAME=ModelMind
MODEL_MIND_SUBTITLE="AI assistant powered by your application data"
MODEL_MIND_LAUNCHER_LABEL="Ask ModelMind"
MODEL_MIND_PLACEHOLDER="Ask about the enabled application data"
MODEL_MIND_THEME=auto
MODEL_MIND_POSITION=bottom-right
MODEL_MIND_WIDTH=25rem
MODEL_MIND_OFFSET=1.25rem
MODEL_MIND_Z_INDEX=9999
MODEL_MIND_USE_PUBLIC_ASSETS=false
```

## Themes

Supported theme values:

- `auto`: follow the host Tailwind dark-mode strategy and the visitor system preference.
- `light`: render the widget with the light theme contract.
- `dark`: add a `dark` class to the widget root and render the dark Tailwind variant.

## Positions

Supported widget positions:

- `bottom-right`
- `bottom-left`
- `bottom-center`
- `top-right`
- `top-left`
- `top-center`
- `center`
- `center-left`
- `center-right`

Short aliases are also accepted:

- `top` maps to `top-center`.
- `bottom` maps to `bottom-center`.
- `left` maps to `center-left`.
- `right` maps to `center-right`.

## Tailwind Source Scanning

If your app compiles Tailwind and the default package classes are missing, include the package views in your Tailwind source scan.

Tailwind CSS v4:

```css
@source "../../vendor/mbs047/model-mind/resources/views/**/*.blade.php";
```

Tailwind CSS v3:

```js
export default {
    content: [
        './resources/views/**/*.blade.php',
        './vendor/mbs047/model-mind/resources/views/**/*.blade.php',
    ],
};
```

For full design replacement, read [Customizing the Chat Modal](customizing-the-modal.md).
