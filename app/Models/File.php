<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{

    // use HasUuids;

    protected $fillable = [
        'client_id',
        'company_name',
        'original_name',
        'path',
        'status',
        'missing_fields',
        'filled_fields',
    ];

    protected $casts = [
        'missing_fields' => 'array',
        'filled_fields' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(User::class , 'client_id');
    }
}
