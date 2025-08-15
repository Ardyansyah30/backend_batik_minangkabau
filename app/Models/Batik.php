<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    /**
     * Tambahkan ini untuk otomatis menyertakan `image_url` saat model diubah ke JSON
     */
    protected $appends = ['image_url'];

    /**
     * Define the accessor for the image_url attribute.
     * This will automatically generate the full URL for the batik image.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null, array $attributes) {
                if (isset($attributes['path']) && $attributes['path']) {
                    return Storage::url($attributes['path']);
                }
                return null;
            },
        );
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}