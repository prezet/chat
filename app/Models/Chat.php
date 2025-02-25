<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    use HasUuids;

    protected $table = 'chats';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
