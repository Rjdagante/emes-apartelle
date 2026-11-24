<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private function formatQrUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }
        return '/storage/' . ltrim($path, '/');
    }

    /**
     * Get all settings
     */
    public function index()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        
        // Ensure defaults if not set
        $defaults = [
            'store_name' => "EME's Apartelle",
            'email' => "admin@emesapartelle.com",
            'phone' => "+63 912 345 6789",
            'gcash_number' => "0912 345 6789",
            'gcash_account_name' => "EME's Apartelle",
            'gcash_qr_image' => null,
            'online_booking' => true,
            'hotel_address' => "General Luna, Surigao del Norte, Siargao Island, Philippines",
            'hotel_latitude' => "9.7811325",
            'hotel_longitude' => "126.1512256",
            'maintenance_mode' => false,
            'email_notifications' => true
        ];

        // Merge defaults with DB values (DB values take precedence)
        $result = array_merge($defaults, $settings->toArray());
        $result['gcash_qr_url'] = $this->formatQrUrl($result['gcash_qr_image'] ?? null);

        // Cast booleans
        $booleanKeys = ['online_booking', 'maintenance_mode', 'email_notifications'];
        foreach ($booleanKeys as $key) {
            if (isset($result[$key])) {
                $result[$key] = filter_var($result[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return response()->json($result);
    }

    /**
     * Save multiple settings
     */
    public function store(Request $request)
    {
        $data = $request->except(['gcash_qr_url', 'gcash_qr_image']);

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    /**
     * Upload or replace GCash QR Code image
     */
    public function uploadQr(Request $request)
    {
        $request->validate([
            'qr_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $oldPath = SystemSetting::get('gcash_qr_image');
        if ($oldPath && !str_starts_with($oldPath, 'http') && !str_starts_with($oldPath, '/')) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('qr_image')->store('settings/qr', 'public');
        SystemSetting::set('gcash_qr_image', $path);

        return response()->json([
            'message' => 'GCash QR code uploaded successfully',
            'gcash_qr_image' => $path,
            'gcash_qr_url' => $this->formatQrUrl($path),
        ]);
    }

    /**
     * Remove GCash QR Code image
     */
    public function removeQr()
    {
        $oldPath = SystemSetting::get('gcash_qr_image');
        if ($oldPath && !str_starts_with($oldPath, 'http') && !str_starts_with($oldPath, '/')) {
            Storage::disk('public')->delete($oldPath);
        }

        SystemSetting::set('gcash_qr_image', null);

        return response()->json([
            'message' => 'GCash QR code removed successfully',
            'gcash_qr_image' => null,
            'gcash_qr_url' => null,
        ]);
    }

    /**
     * Get only public settings
     */
    public function public()
    {
        $publicKeys = ['store_name', 'email', 'phone', 'gcash_number', 'gcash_account_name', 'gcash_qr_image', 'online_booking', 'maintenance_mode', 'hotel_address', 'hotel_latitude', 'hotel_longitude'];
        $settings = SystemSetting::whereIn('key', $publicKeys)->pluck('value', 'key');
        
        $defaults = [
            'store_name' => "EME's Apartelle",
            'email' => "info@emesapartelle.com",
            'phone' => "+63 912 345 6789",
            'gcash_number' => "0912 345 6789",
            'gcash_account_name' => "EME's Apartelle",
            'gcash_qr_image' => null,
            'online_booking' => true,
            'hotel_address' => "General Luna, Surigao del Norte, Siargao Island, Philippines",
            'hotel_latitude' => "9.7811325",
            'hotel_longitude' => "126.1512256",
            'maintenance_mode' => false
        ];

        $result = array_merge($defaults, $settings->toArray());
        $result['gcash_qr_url'] = $this->formatQrUrl($result['gcash_qr_image'] ?? null);

        // Cast booleans
        foreach (['online_booking', 'maintenance_mode'] as $key) {
            if (isset($result[$key])) {
                $result[$key] = filter_var($result[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return response()->json($result);
    }
}
