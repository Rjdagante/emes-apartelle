<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected static function booted()
    {
        $clearCaches = function ($reservation) {
            \Illuminate\Support\Facades\Cache::forget('rooms_public_list');
            \Illuminate\Support\Facades\Cache::forget('all_booked_dates');
            if ($reservation->room_id) {
                \Illuminate\Support\Facades\Cache::forget("room_detail_{$reservation->room_id}");
            }
            \Illuminate\Support\Facades\Cache::forget('admin_stats_admin');
            \Illuminate\Support\Facades\Cache::forget('admin_stats_staff');
            \Illuminate\Support\Facades\Cache::forget('admin_reports');
        };

        static::created($clearCaches);
        static::updated($clearCaches);
        static::deleted($clearCaches);
    }

    protected $fillable = [
        'user_id',
        'guest_name',
        'room_id',
        'package_id',
        'guests',
        'adults',
        'children',
        'children_ages',
        'extra_guests',
        'check_in',
        'check_out',
        'total_amount',
        'extra_bed_charge',
        'payment_option',
        'downpayment_amount',
        'status',
        'payment_status',
        'payment_screenshot',
        'cancellation_reason',
        'xendit_invoice_id',
    ];

    protected $appends = ['payment_screenshot_url'];

    public function getPaymentScreenshotUrlAttribute(): ?string
    {
        if (!$this->payment_screenshot) {
            return null;
        }
        if (str_starts_with($this->payment_screenshot, 'http') || str_starts_with($this->payment_screenshot, '/')) {
            return $this->payment_screenshot;
        }
        return '/storage/' . ltrim($this->payment_screenshot, '/');
    }

    protected $casts = [
        'children_ages' => 'array',
        'extra_bed_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'downpayment_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function package()
    {
        return $this->belongsTo(RoomPackage::class, 'package_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class);
    }
}
