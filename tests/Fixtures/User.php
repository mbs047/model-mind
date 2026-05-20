<?php

namespace Mbs\ModelMind\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    protected $table = 'model_mind_users';

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return collect(explode(',', (string) $this->role_names))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->values();
    }
}
