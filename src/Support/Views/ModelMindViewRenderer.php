<?php

namespace Mbs\ModelMind\Support\Views;

class ModelMindViewRenderer
{
    public function modal(array|string|null $options = null): string
    {
        return $this->render('modal', $options);
    }

    public function styles(array|string|null $options = null): string
    {
        return $this->render('styles', $options);
    }

    public function scripts(array|string|null $options = null): string
    {
        return $this->render('scripts', $options);
    }

    public function all(array|string|null $options = null): string
    {
        if (is_string($options)) {
            $options = ['modal' => $options];
        }

        return $this->styles($this->optionsForPart('styles', $options))
            .$this->modal($this->optionsForPart('modal', $options))
            .$this->scripts($this->optionsForPart('scripts', $options));
    }

    private function render(string $part, array|string|null $options = null): string
    {
        [$view, $data] = $this->resolve($part, $options);

        return view($view, $data)->render();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolve(string $part, array|string|null $options): array
    {
        $view = (string) config("model-mind.views.{$part}", "model-mind::components.{$part}");
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
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function publicData(array $options): array
    {
        unset($options['modal'], $options['styles'], $options['scripts'], $options['data']);

        return $options;
    }
}
