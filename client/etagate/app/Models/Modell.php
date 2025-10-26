<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modell extends Model
{
    use HasFactory;

    protected $table = 'models';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'efficiency',
        'eta',
    ];

    protected $casts = [
        'efficiency' => 'decimal:2',
        'eta' => 'integer',
    ];
}
