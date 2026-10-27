<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FazerCardsAudit extends Model
{
    protected $table = 'fazercards_audits';

    protected $fillable = [
        'user_id',
        'action',
        'method',
        'path',
        'ok',
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'action' => $this->action,
            'method' => $this->method,
            'path' => $this->path,
            'ok' => (bool) $this->ok,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
