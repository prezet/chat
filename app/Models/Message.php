<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tpetry\PostgresqlEnhanced\Eloquent\Concerns\AutomaticDateFormatWithMilliseconds;

class Message extends Model
{
    use HasFactory, HasUuids; // , AutomaticDateFormatWithMilliseconds;

    protected $fillable = [
        'id',
        'chat_id',
        'role',
        'content',
        'parts',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'parts' => 'array',
        'metadata' => 'array',
    ];
}
