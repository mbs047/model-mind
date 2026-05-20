<?php

namespace Mbs\ModelMind\Support\Views;

class ModelMindViewRenderer
{
    public function modal(array|string|null $options = null): string
    {
        [$view, $data] = $this->resolveModal($options);

        return view($view, $data)->render();
    }

    public function styles(array|string|null $options = null): string
    {
        return $this->tag('link', [
            'rel' => 'stylesheet',
            'href' => $this->assetUrl($this->assetPath($options, 'styles_path', 'vendor/model-mind/model-mind.css', 'href')),
        ]);
    }

    public function scripts(array|string|null $options = null): string
    {
        return $this->tag('script', [
            'src' => $this->assetUrl($this->assetPath($options, 'scripts_path', 'vendor/model-mind/model-mind.js', 'src')),
            'defer' => true,
        ], true);
    }

    public function all(array|string|null $options = null): string
    {
        if (is_string($options)) {
            $options = ['modal' => $options];
        }

        return $this->styles($this->assetOptionsForPart('styles', $options))
            .$this->modal($this->optionsForPart('modal', $options))
            .$this->scripts($this->assetOptionsForPart('scripts', $options));
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolveModal(array|string|null $options): array
    {
        $view = (string) config('model-mind.views.modal', 'model-mind::components.modal');
        $data = [];

        if (is_string($options)) {
            return [$options, $data];
        }

        if (! is_array($options)) {
            return [$view, $data];
        }

        if (isset($options['view']) && is_string($options['view'])) {
            $view = $options['view'];
        }

        if (isset($options['data']) && is_array($options['data'])) {
            $data = $options['data'];
        } elseif (! array_key_exists('view', $options)) {
            $data = $this->publicData($options);
        }

        return [$view, $data];
    }

    private function assetPath(array|string|null $options, string $configKey, string $fallback, string $urlKey): string
    {
        if (is_string($options) && filled($options)) {
            return $options;
        }

        if (is_array($options)) {
            foreach (['path', $urlKey] as $key) {
                if (is_string($options[$key] ?? null) && filled($options[$key])) {
                    return $options[$key];
                }
            }
        }

        $configured = config("model-mind.assets.{$configKey}", $fallback);

        return is_string($configured) && filled($configured) ? $configured : $fallback;
    }

    private function assetUrl(string $path): string
    {
        if (preg_match('~^(https?:)?//~i', $path) === 1) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tag(string $tag, array $attributes, bool $close = false): string
    {
        $html = collect($attributes)
            ->map(function (mixed $value, string $name): ?string {
                if ($value === false || $value === null) {
                    return null;
                }

                if ($value === true) {
                    return e($name);
                }

                return e($name).'="'.e((string) $value).'"';
            })
            ->filter()
            ->implode(' ');

        return $close
            ? "<{$tag} {$html}></{$tag}>"
            : "<{$tag} {$html}>";
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return array<string, mixed>|string|null
     */
    private function optionsForPart(string $part, ?array $options): array|string|null
    {
        if ($options === null) {
            return null;
        }

        $sharedData = isset($options['data']) && is_array($options['data'])
            ? $options['data']
            : [];
        $partOptions = $options[$part] ?? null;

        if (is_string($partOptions)) {
            return [
                'view' => $partOptions,
                'data' => $sharedData,
            ];
        }

        if (is_array($partOptions)) {
            return [
                ...$partOptions,
                'data' => [
                    ...$sharedData,
                    ...(isset($partOptions['data']) && is_array($partOptions['data']) ? $partOptions['data'] : []),
                ],
            ];
        }

        if ($sharedData !== []) {
            return ['data' => $sharedData];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return array<string, mixed>|string|null
     */
    private function assetOptionsForPart(string $part, ?array $options): array|string|null
    {
        if ($options === null) {
            return null;
        }

        $partOptions = $options[$part] ?? null;

        return is_array($partOptions) || is_string($partOptions) ? $partOptions : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function publicData(array $options): array
    {
        unset($options['modal'], $options['styles'], $options['scripts'], $options['data']);

        return $options;
    }
}
