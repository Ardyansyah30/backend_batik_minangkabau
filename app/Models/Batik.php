<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // 👈 Tambahkan baris ini

class Batik extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'path',
        'original_name',
        'is_minangkabau_batik',
        'batik_name',
        'description',
        'origin',
    ];

    public function comments(): HasMany // Ini sekarang akan dikenali
    {
        return $this->hasMany(Comment::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}