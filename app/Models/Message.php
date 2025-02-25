<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Support\Collection;
use Tpetry\PostgresqlEnhanced\Eloquent\Concerns\AutomaticDateFormatWithMilliseconds;

class Message extends Model
{
    use HasUuids, HasFactory; //, AutomaticDateFormatWithMilliseconds;

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
