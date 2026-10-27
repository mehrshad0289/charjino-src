<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'phone',
        'email',
        'name',
        'blog_slug',
        'password',
        'role',
        'avatar_url',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->id) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function blogPosts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BlogPost::class, 'user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toAuthUser(): array
    {
        if ($this->isAdmin()) {
            return [
                'id' => $this->id,
                'email' => $this->email,
                'role' => 'admin',
            ];
        }

        return [
            'id' => $this->id,
            'phoneNumber' => $this->phone,
            'name' => $this->name,
            'avatarUrl' => $this->avatar_url,
            'role' => 'user',
        ];
    }
}
