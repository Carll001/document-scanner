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
        'president_name',
        'original_name',
        'path',
        'status',
        'missing_fields',
        'filled_fields',
        'raw_data',
    ];

    protected $casts = [
        'missing_fields' => 'array',
        'filled_fields' => 'array',
        'raw_data'       => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(User::class , 'client_id');
    }
}
