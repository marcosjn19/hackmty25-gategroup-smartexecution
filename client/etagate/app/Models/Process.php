<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Process extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name','status','payload',
        'run_at','started_at','finished_at','error_message',
        'default_threshold','models',
        'total_images','positives','negatives',
        'avg_latency_ms','p95_latency_ms','max_latency_ms','avg_confidence',
        'stats','last_request',
    ];

    protected $casts = [
        'payload'           => 'array',
        'run_at'            => 'datetime',
        'started_at'        => 'datetime',
        'finished_at'       => 'datetime',
        'default_threshold' => 'float',
        'models'            => 'array',
        'stats'             => 'array',
        'last_request'      => 'array',
        'avg_confidence'    => 'float',
    ];

    // Scopes útiles
    public function scopePending($q){ return $q->where('status','pending'); }
    public function scopeDue($q){ return $q->whereNull('run_at')->orWhere('run_at','<=', now()); }
}
