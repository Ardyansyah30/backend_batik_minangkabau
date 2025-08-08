<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batik extends Model
{
    use HasFactory;

    // Tentukan nama tabel yang benar di sini
    protected $table = 'batiks';

    protected $fillable = [
        'filename',
        'path',
        'original_name',
        'is_minangkabau_batik',
        'batik_name',
        'description',
        'origin',
    ];
}