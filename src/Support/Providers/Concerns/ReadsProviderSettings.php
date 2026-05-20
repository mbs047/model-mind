<?php

namespace Mbs\ModelMind\Support\Providers\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait ReadsProviderSettings
{
    protected function providerSetting(string $driver, string $key, mixed $default = null): mixed
    {
        $settings = config("model-mind.provider.drivers.{$driver}", []);

        if ($driver !== 'openai' && is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return config("model-mind.provider.{$key}", $default);
    }

    protected function providerRequest(string $driver): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout((int) $this->providerSetting($driver, 'connect_timeout', 4))
            ->timeout((int) $this->providerSetting($driver, 'timeout', 20));
    }

    protected function providerBaseUrl(string $driver, string $default): string
    {
        $baseUrl = $this->providerSetting($driver, 'base_url', $default);

        return rtrim(is_string($baseUrl) && filled($baseUrl) ? $baseUrl : $default, '/');
    }
}
