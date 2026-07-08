<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['name', 'text', 'image_upload', 'rating', 'active', 'orden'];

    protected $casts = [
        'active' => 'boolean',
        'rating' => 'integer',
    ];

    public function imageUrl(): ?string
    {
        return $this->image_upload ? '/storage/' . ltrim($this->image_upload, '/') : null;
    }
}
