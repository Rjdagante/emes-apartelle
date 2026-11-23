<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    /**
     * Get booked date ranges for a specific room.
     */
    public function roomBookedDates(Request $request, $id)
    {
        $excludeId = $request->query('exclude_reservation_id');
        $query = Reservation::where('room_id', $id)
            ->whereIn('status', ['pending', 'confirmed', 'checked-in'])
            ->where('check_out', '>=', now());

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $dates = $query->get(['check_in', 'check_out'])
            ->map(function ($res) {
                return [
                    'check_in' => $res->check_in,
                    'check_out' => $res->check_out,
                    'type' => 'booked',
                ];
            })
            ->toArray();

        // Add blocked dates (either specific to this room or global)
        $blocked = \App\Models\BlockedDate::where(function ($q) use ($id) {
                $q->where('room_id', $id)
                  ->orWhereNull('room_id');
            })
            ->where('end_date', '>=', now())
            ->get(['start_date as check_in', 'end_date as check_out', 'reason'])
            ->map(function ($block) {
                return [
                    'check_in' => $block->check_in,
                    'check_out' => $block->check_out,
                    'type' => 'blocked',
                    'reason' => $block->reason ?: null,
                ];
            })
            ->toArray();

        return response()->json(array_merge($dates, $blocked));
    }

    /**
     * Get dates where no rooms are available.
     * Cached for 2 minutes to reduce query load.
     */
    public function allBookedDates()
    {
        $unavailableRanges = Cache::remember('all_booked_dates', 120, function () {
            $rooms = Room::where('status', '!=', 'unavailable')->pluck('id')->toArray();
            $totalRooms = count($rooms);
            if ($totalRooms === 0) {
                return [];
            }

            $result = [];

            // 1. All Global Blocked Dates (room_id is null) directly block the entire property
            $globalBlocks = \App\Models\BlockedDate::whereNull('room_id')
                ->where('end_date', '>=', now())
                ->get();

            foreach ($globalBlocks as $gb) {
                $result[] = [
                    'check_in' => $gb->start_date,
                    'check_out' => $gb->end_date,
                    'type' => 'blocked',
                    'reason' => $gb->reason ?: 'Blocked by administration',
                ];
            }

            // 2. Individual reservations and room-specific blocks
            $activeReservations = Reservation::whereIn('status', ['pending', 'confirmed', 'checked-in'])
                ->where('check_out', '>=', now())
                ->whereIn('room_id', $rooms)
                ->get(['room_id', 'check_in', 'check_out']);

            $roomBlocks = \App\Models\BlockedDate::whereNotNull('room_id')
                ->where('end_date', '>=', now())
                ->whereIn('room_id', $rooms)
                ->get(['room_id', 'start_date', 'end_date', 'reason']);

            $dateOccupiedRooms = [];

            foreach ($activeReservations as $res) {
                $start = \Carbon\Carbon::parse($res->check_in)->startOfDay();
                $end = \Carbon\Carbon::parse($res->check_out)->startOfDay();
                for ($d = $start->copy(); $d->lt($end); $d->addDay()) {
                    $dStr = $d->toDateString();
                    $dateOccupiedRooms[$dStr][$res->room_id] = 'booked';
                }
            }

            foreach ($roomBlocks as $block) {
                $start = \Carbon\Carbon::parse($block->start_date)->startOfDay();
                $end = \Carbon\Carbon::parse($block->end_date)->startOfDay();
                for ($d = $start->copy(); $d->lt($end); $d->addDay()) {
                    $dStr = $d->toDateString();
                    $dateOccupiedRooms[$dStr][$block->room_id] = 'blocked';
                }
            }

            // Find dates where ALL active rooms are occupied/blocked
            $fullyOccupiedDates = [];
            foreach ($dateOccupiedRooms as $dStr => $occupiedMap) {
                if (count($occupiedMap) >= $totalRooms) {
                    $hasOnlyBlocks = !in_array('booked', array_values($occupiedMap));
                    $fullyOccupiedDates[$dStr] = $hasOnlyBlocks ? 'blocked' : 'booked';
                }
            }

            ksort($fullyOccupiedDates);
            $rangeStart = null;
            $rangeEnd = null;
            $rangeType = null;

            foreach ($fullyOccupiedDates as $dStr => $type) {
                $currentDate = \Carbon\Carbon::parse($dStr);
                if ($rangeStart === null) {
                    $rangeStart = $currentDate;
                    $rangeEnd = $currentDate->copy()->addDay();
                    $rangeType = $type;
                } else if ($currentDate->equalTo($rangeEnd) && $rangeType === $type) {
                    $rangeEnd = $currentDate->copy()->addDay();
                } else {
                    $result[] = [
                        'check_in' => $rangeStart->toDateString(),
                        'check_out' => $rangeEnd->toDateString(),
                        'type' => $rangeType,
                        'reason' => $rangeType === 'blocked' ? 'All rooms blocked by administration' : null,
                    ];
                    $rangeStart = $currentDate;
                    $rangeEnd = $currentDate->copy()->addDay();
                    $rangeType = $type;
                }
            }

            if ($rangeStart !== null) {
                $result[] = [
                    'check_in' => $rangeStart->toDateString(),
                    'check_out' => $rangeEnd->toDateString(),
                    'type' => $rangeType,
                    'reason' => $rangeType === 'blocked' ? 'All rooms blocked by administration' : null,
                ];
            }

            return $result;
        });

        return response()->json($unavailableRanges);
    }

    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     * Public requests without date filters are cached for 5 minutes.
     */
    public function index(Request $request)
    {
        $hasDateFilter = $request->has(['check_in', 'check_out']);
        $isAdminOrStaff = $request->user() && in_array($request->user()->role, ['admin', 'staff']);

        // Cache public room listings (no date filter, no admin view)
        if (!$hasDateFilter && !$isAdminOrStaff) {
            $rooms = Cache::remember('rooms_public_list', 300, function () {
                return Room::with(['amenities', 'images'])
                    ->orderByRaw('CAST(room_number AS UNSIGNED) ASC, room_number ASC')
                    ->get();
            });
            return response()->json($rooms);
        }

        $query = Room::with(['amenities', 'images'])
            ->orderByRaw('CAST(room_number AS UNSIGNED) ASC, room_number ASC');

        // Optional date-based availability filtering
        if ($hasDateFilter) {
            $checkIn = \Carbon\Carbon::parse($request->check_in);
            $checkOut = \Carbon\Carbon::parse($request->check_out);

            // 1. Check if there is a global block (room_id is null) during this period
            $isGloballyBlocked = \App\Models\BlockedDate::whereNull('room_id')
                ->where('start_date', '<', $checkOut)
                ->where('end_date', '>', $checkIn)
                ->exists();

            if ($isGloballyBlocked) {
                return response()->json([]); // No rooms available globally
            }

            // 2. Filter out rooms that have active reservations during this period
            $query->whereDoesntHave('reservations', function ($q) use ($checkIn, $checkOut) {
                // Rule: Pending, Confirmed, and Checked-in reservations block dates
                $q->whereIn('status', ['pending', 'confirmed', 'checked-in'])
                  ->where(function($subQ) use ($checkIn, $checkOut) {
                      $subQ->where('check_in', '<', $checkOut)
                           ->where('check_out', '>', $checkIn);
                  });
            });

            // 3. Filter out rooms that are individually blocked during this period
            $query->whereDoesntHave('blockedDates', function ($q) use ($checkIn, $checkOut) {
                $q->where('start_date', '<', $checkOut)
                  ->where('end_date', '>', $checkIn);
            });
        }

        $rooms = $query->get();

        // Add dynamic occupancy status for Admin & Staff view
        if ($isAdminOrStaff) {
            $today = \Carbon\Carbon::today();
            foreach ($rooms as $room) {
                $currentRes = Reservation::where('room_id', $room->id)
                    ->whereIn('status', ['confirmed', 'checked-in'])
                    ->where('check_in', '<=', $today)
                    ->where('check_out', '>', $today)
                    ->with('user')
                    ->first();
                
                if ($currentRes) {
                    $room->current_reservation = $currentRes;
                    // If room is physically available but logically occupied today
                    if ($room->status === 'available') {
                        $room->status = 'occupied';
                    }
                }
            }
        }

        return response()->json($rooms);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->has('is_promo')) {
            $isPromo = filter_var($request->input('is_promo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isPromo !== null) {
                $request->merge(['is_promo' => $isPromo]);
            }
        }

        $validator = Validator::make($request->all(), [
            'room_number' => 'required|string|unique:rooms',
            'room_type' => 'required|string',
            'description' => 'nullable|string',
            'price_per_night' => 'required_unless:room_type,Family Room,Barkadahan Room|nullable|numeric',
            'price_per_head' => 'required_if:room_type,Family Room,Barkadahan Room|nullable|numeric',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'max_occupancy' => 'required|integer|min:1',
            'max_extra_guests' => 'nullable|integer|min:0',
            'min_occupancy' => 'nullable|integer',
            'status' => 'required|in:available,unavailable,maintenance',
            'is_promo' => 'nullable|boolean',
            'promo_price' => 'nullable|numeric|min:0',
            'promo_price_per_head' => 'nullable|numeric|min:0',
            'promo_discount_percent' => 'nullable|integer|min:1|max:99',
            'promo_title' => 'nullable|string|max:255',
            'promo_description' => 'nullable|string|max:1000',
            'image' => 'nullable|string',
            'bed_type' => 'nullable|string',
            'room_size' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        if (in_array($data['room_type'] ?? '', ['Family Room', 'Barkadahan Room'])) {
            $data['price_per_night'] = 0.00;
        } else {
            $data['price_per_head'] = 0.00;
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('rooms', 'public');
            $data['image'] = Storage::url($path);
        } elseif (isset($data['image']) && ($data['image'] === 'null' || empty($data['image']))) {
            $data['image'] = null;
        }

        $room = Room::create($data);

        // Handle multiple images if provided
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('rooms', 'public');
                $url = Storage::url($path);
                $room->images()->create([
                    'image_path' => $url,
                    'is_primary' => false,
                ]);
            }
        }

        // If no main image was provided, pick the first gallery image or fallback to preset
        if (empty($room->image)) {
            $firstGallery = $room->images()->first();
            if ($firstGallery) {
                $room->update(['image' => $firstGallery->image_path]);
            } else {
                $type = strtolower($room->room_type ?? '');
                $defaultImg = str_contains($type, 'family')
                    ? '/images/unsplash/suite-room.jpg'
                    : (str_contains($type, 'barkadahan') ? '/images/unsplash/deluxe-room.jpg' : '/images/unsplash/standard-room.jpg');
                $room->update(['image' => $defaultImg]);
                $room->images()->create([
                    'image_path' => '/images/unsplash/room-interior.jpg',
                    'is_primary' => false,
                ]);
            }
        }

        if ($request->has('amenities')) {
            // Handle amenities which might be sent as a stringified JSON array if using FormData
            $amenities = is_string($request->amenities) ? json_decode($request->amenities, true) : $request->amenities;
            $room->amenities()->sync(is_array($amenities) ? $amenities : []);
        }

        // Invalidate room-related caches after creation
        $this->clearRoomCaches();

        return response()->json($room->load(['amenities', 'images']), 201);
    }

    /**
     * Display the specified resource.
     */
    /**
     * Display the specified resource.
     * Cached per room for 5 minutes.
     */
    public function show(string $id)
    {
        $room = Cache::remember("room_detail_{$id}", 300, function () use ($id) {
            return Room::with(['amenities', 'images', 'reservations' => function($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'checked-in'])
                  ->where('check_out', '>=', now())
                  ->orderBy('check_in', 'asc')
                  ->select('id', 'room_id', 'check_in', 'check_out', 'status');
            }])->find($id);
        });

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        return response()->json($room);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        if ($request->has('is_promo')) {
            $isPromo = filter_var($request->input('is_promo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isPromo !== null) {
                $request->merge(['is_promo' => $isPromo]);
            }
        }

        $validator = Validator::make($request->all(), [
            'room_number' => 'sometimes|required|string|unique:rooms,room_number,' . $id,
            'room_type' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'price_per_night' => 'sometimes|required_unless:room_type,Family Room,Barkadahan Room|nullable|numeric',
            'price_per_head' => 'sometimes|required_if:room_type,Family Room,Barkadahan Room|nullable|numeric',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'max_occupancy' => 'sometimes|required|integer|min:1',
            'max_extra_guests' => 'nullable|integer|min:0',
            'min_occupancy' => 'sometimes|required|integer',
            'status' => 'sometimes|required|in:available,unavailable,maintenance',
            'is_promo' => 'nullable|boolean',
            'promo_price' => 'nullable|numeric|min:0',
            'promo_price_per_head' => 'nullable|numeric|min:0',
            'promo_discount_percent' => 'nullable|integer|min:1|max:99',
            'promo_title' => 'nullable|string|max:255',
            'promo_description' => 'nullable|string|max:1000',
            'image' => 'nullable|string',
            'bed_type' => 'nullable|string',
            'room_size' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $roomType = $data['room_type'] ?? $room->room_type;
        if (in_array($roomType, ['Family Room', 'Barkadahan Room'])) {
            $data['price_per_night'] = 0.00;
        } else {
            $data['price_per_head'] = 0.00;
        }

        if ($request->hasFile('image_file')) {
            // Delete old image if it exists and is a local file
            if ($room->image && str_contains($room->image, '/storage/rooms/')) {
                $oldPath = str_replace('/storage/', '', $room->image);
                Storage::disk('public')->delete($oldPath);
            }
            
            $path = $request->file('image_file')->store('rooms', 'public');
            $data['image'] = Storage::url($path);
        } elseif (isset($data['image']) && ($data['image'] === 'null' || empty($data['image']))) {
            $data['image'] = null;
        }

        $room->update($data);

        // Handle multiple images if provided
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('rooms', 'public');
                $url = Storage::url($path);
                $room->images()->create([
                    'image_path' => $url,
                    'is_primary' => false,
                ]);
            }
        }

        // Handle removing specific images
        if ($request->has('remove_images')) {
            $removeIds = is_string($request->remove_images) ? json_decode($request->remove_images) : $request->remove_images;
            if (is_array($removeIds)) {
                $imagesToRemove = \App\Models\RoomImage::whereIn('id', $removeIds)->where('room_id', $room->id)->get();
                foreach ($imagesToRemove as $img) {
                    if (str_contains($img->image_path, '/storage/rooms/')) {
                        $p = str_replace('/storage/', '', $img->image_path);
                        Storage::disk('public')->delete($p);
                    }
                    $img->delete();
                }
            }
        }

        // Ensure room has a valid primary image
        if (empty($room->fresh()->image)) {
            $firstGallery = $room->images()->first();
            if ($firstGallery) {
                $room->update(['image' => $firstGallery->image_path]);
            } else {
                $type = strtolower($room->room_type ?? '');
                $defaultImg = str_contains($type, 'family')
                    ? '/images/unsplash/suite-room.jpg'
                    : (str_contains($type, 'barkadahan') ? '/images/unsplash/deluxe-room.jpg' : '/images/unsplash/standard-room.jpg');
                $room->update(['image' => $defaultImg]);
            }
        }

        if ($request->has('amenities')) {
            $amenities = is_string($request->amenities) ? json_decode($request->amenities, true) : $request->amenities;
            $room->amenities()->sync(is_array($amenities) ? $amenities : []);
        }

        // Invalidate room-related caches after update
        $this->clearRoomCaches($room->id);

        return response()->json($room->load(['amenities', 'images']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $roomId = $room->id;
        $room->delete();

        // Invalidate room-related caches after deletion
        $this->clearRoomCaches($roomId);

        return response()->json(['message' => 'Room deleted successfully']);
    }

    /**
     * Update promotional status & pricing for a room.
     */
    public function updatePromo(Request $request, string $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_promo' => 'required|boolean',
            'promo_price' => 'nullable|numeric|min:0',
            'promo_price_per_head' => 'nullable|numeric|min:0',
            'promo_discount_percent' => 'nullable|integer|min:1|max:99',
            'promo_title' => 'nullable|string|max:255',
            'promo_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $isPromo = (bool) $request->input('is_promo', false);
        $data = [
            'is_promo' => $isPromo,
            'promo_price' => $isPromo ? $request->input('promo_price') : null,
            'promo_price_per_head' => $isPromo ? $request->input('promo_price_per_head') : null,
            'promo_discount_percent' => $isPromo ? $request->input('promo_discount_percent') : null,
            'promo_title' => $isPromo ? ($request->input('promo_title') ?: 'Special Offer') : null,
            'promo_description' => $isPromo ? $request->input('promo_description') : null,
        ];

        $room->update($data);
        $this->clearRoomCaches($room->id);

        return response()->json([
            'message' => $isPromo ? "Promotion activated for Room #{$room->room_number}." : "Promotion deactivated for Room #{$room->room_number}.",
            'room' => $room->fresh(['amenities', 'images'])
        ]);
    }

    /**
     * Remove promotional pricing from a room.
     */
    public function removePromo(string $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $room->update([
            'is_promo' => false,
            'promo_price' => null,
            'promo_price_per_head' => null,
            'promo_discount_percent' => null,
            'promo_title' => null,
            'promo_description' => null,
        ]);

        $this->clearRoomCaches($room->id);

        return response()->json([
            'message' => "Promotion removed from Room #{$room->room_number}.",
            'room' => $room->fresh(['amenities', 'images'])
        ]);
    }

    /**
     * Clear all room-related caches after a mutation.
     */
    private function clearRoomCaches($roomId = null)
    {
        Cache::forget('rooms_public_list');
        Cache::forget('all_booked_dates');
        if ($roomId) {
            Cache::forget("room_detail_{$roomId}");
        }
    }
}
