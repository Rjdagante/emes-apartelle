<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'room_type',
        'description',
        'price_per_night',
        'price_per_head',
        'extra_bed_price',
        'max_occupancy',
        'max_extra_guests',
        'min_occupancy',
        'status',
        'is_promo',
        'promo_price',
        'promo_price_per_head',
        'promo_discount_percent',
        'promo_title',
        'promo_description',
        'image',
        'bed_type',
        'room_size',
    ];

    protected $casts = [
        'is_promo' => 'boolean',
        'promo_price' => 'decimal:2',
        'promo_price_per_head' => 'decimal:2',
        'promo_discount_percent' => 'integer',
        'price_per_night' => 'decimal:2',
        'price_per_head' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
        'max_occupancy' => 'integer',
        'min_occupancy' => 'integer',
        'max_extra_guests' => 'integer',
    ];

    protected $appends = [
        'effective_price',
        'effective_price_per_head',
    ];

    public function getEffectivePriceAttribute()
    {
        if ($this->is_promo && $this->promo_price !== null && (float)$this->promo_price > 0) {
            return (float) $this->promo_price;
        }
        return (float) $this->price_per_night;
    }

    public function getEffectivePricePerHeadAttribute()
    {
        if ($this->is_promo && $this->promo_price_per_head !== null && (float)$this->promo_price_per_head > 0) {
            return (float) $this->promo_price_per_head;
        }
        return (float) $this->price_per_head;
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function blockedDates()
    {
        return $this->hasMany(BlockedDate::class);
    }

    public function packages()
    {
        return $this->hasMany(RoomPackage::class);
    }
}
