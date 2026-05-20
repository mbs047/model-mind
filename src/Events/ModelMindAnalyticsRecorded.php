<?php

namespace Mbs\ModelMind\Events;

use Mbs\ModelMind\Models\ModelMindEvent;

class ModelMindAnalyticsRecorded
{
    public function __construct(public readonly ModelMindEvent $event) {}
}
