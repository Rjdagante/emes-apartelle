<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RoomPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'name',
        'tagline',
        'duration',
        'pax',
        'price',
        'room_type_label',
        'tour_type',
        'room_inclusions',
        'tour_destinations',
        'tour_inclusions',
        'description',
        'location',
        'contact_number',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'room_inclusions' => 'array',
        'tour_destinations' => 'array',
        'tour_inclusions' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://') || str_starts_with($this->image, '/')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
}

