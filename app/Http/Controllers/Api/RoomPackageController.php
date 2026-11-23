<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomPackageController extends Controller
{
    /**
     * Get all packages (Admin / Staff).
     */
    public function index()
    {
        $packages = RoomPackage::with(['room.amenities', 'room.images'])->orderBy('id', 'desc')->get();
        return response()->json($packages);
    }

    /**
     * Get active packages for public display (Guest-facing).
     */
    public function publicIndex()
    {
        $packages = RoomPackage::where('is_active', true)
            ->with(['room.amenities', 'room.images'])
            ->orderBy('id', 'desc')
            ->get();
        return response()->json($packages);
    }

    /**
     * Get single package details.
     */
    public function show($id)
    {
        $package = RoomPackage::with(['room.amenities', 'room.images'])->findOrFail($id);
        return response()->json($package);
    }

    /**
     * Create a new room package.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:100',
            'pax' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'room_id' => 'nullable|exists:rooms,id',
            'room_type_label' => 'nullable|string|max:100',
            'tour_type' => 'nullable|string|max:100',
            'room_inclusions' => 'nullable',
            'tour_destinations' => 'nullable',
            'tour_inclusions' => 'nullable',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'image' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $imagePath = '/images/unsplash/hero-resort.jpg';
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('packages', 'public');
        } elseif ($request->filled('image_url')) {
            $imagePath = $request->input('image_url');
        }

        $package = RoomPackage::create([
            'name' => $validated['name'],
            'tagline' => $validated['tagline'] ?? null,
            'duration' => $validated['duration'] ?? '3 Days, 2 Nights',
            'pax' => $validated['pax'] ?? 'Good for 2 pax',
            'price' => $validated['price'],
            'room_id' => !empty($validated['room_id']) ? $validated['room_id'] : null,
            'room_type_label' => $validated['room_type_label'] ?? 'Private Room',
            'tour_type' => $validated['tour_type'] ?? 'Joiners Tour',
            'room_inclusions' => $this->parseList($request->input('room_inclusions')),
            'tour_destinations' => $this->parseList($request->input('tour_destinations')),
            'tour_inclusions' => $this->parseList($request->input('tour_inclusions')),
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? 'Pob. 1 General Luna (Along the road)',
            'contact_number' => $validated['contact_number'] ?? '0950 560 2175',
            'image' => $imagePath,
            'is_active' => filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        return response()->json([
            'message' => 'Room package created successfully.',
            'package' => $package->load('room'),
        ], 201);
    }

    /**
     * Update an existing room package.
     */
    public function update(Request $request, $id)
    {
        $package = RoomPackage::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:100',
            'pax' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'room_id' => 'nullable',
            'room_type_label' => 'nullable|string|max:100',
            'tour_type' => 'nullable|string|max:100',
            'room_inclusions' => 'nullable',
            'tour_destinations' => 'nullable',
            'tour_inclusions' => 'nullable',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'image' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'tagline' => $validated['tagline'] ?? null,
            'duration' => $validated['duration'] ?? $package->duration,
            'pax' => $validated['pax'] ?? $package->pax,
            'price' => $validated['price'],
            'room_id' => !empty($validated['room_id']) ? $validated['room_id'] : null,
            'room_type_label' => $validated['room_type_label'] ?? $package->room_type_label,
            'tour_type' => $validated['tour_type'] ?? $package->tour_type,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? $package->location,
            'contact_number' => $validated['contact_number'] ?? $package->contact_number,
        ];

        if ($request->has('room_inclusions')) {
            $updateData['room_inclusions'] = $this->parseList($request->input('room_inclusions'));
        }
        if ($request->has('tour_destinations')) {
            $updateData['tour_destinations'] = $this->parseList($request->input('tour_destinations'));
        }
        if ($request->has('tour_inclusions')) {
            $updateData['tour_inclusions'] = $this->parseList($request->input('tour_inclusions'));
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('image')) {
            if ($package->image && !str_starts_with($package->image, '/') && !str_starts_with($package->image, 'http')) {
                Storage::disk('public')->delete($package->image);
            }
            $updateData['image'] = $request->file('image')->store('packages', 'public');
        } elseif ($request->filled('image_url')) {
            $updateData['image'] = $request->input('image_url');
        }

        $package->update($updateData);

        return response()->json([
            'message' => 'Room package updated successfully.',
            'package' => $package->fresh()->load('room'),
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id)
    {
        $package = RoomPackage::findOrFail($id);
        $package->is_active = !$package->is_active;
        $package->save();

        return response()->json([
            'message' => 'Package status updated.',
            'is_active' => $package->is_active,
            'package' => $package->load('room'),
        ]);
    }

    /**
     * Delete a package.
     */
    public function destroy($id)
    {
        $package = RoomPackage::findOrFail($id);

        if ($package->image && !str_starts_with($package->image, '/') && !str_starts_with($package->image, 'http')) {
            Storage::disk('public')->delete($package->image);
        }

        $package->delete();

        return response()->json([
            'message' => 'Room package deleted successfully.',
        ]);
    }

    /**
     * Helper to parse array, json, newline, or comma separated list into clean array of strings.
     */
    private function parseList($input): array
    {
        if (empty($input)) {
            return [];
        }

        if (is_array($input)) {
            return array_values(array_filter(array_map('trim', $input)));
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            // Split by newline or comma or bullet
            $lines = preg_split('/[\r\n]+/', $input);
            $items = [];
            foreach ($lines as $line) {
                $cleaned = preg_replace('/^[\s•\-\*\d\.\)\>]+/u', '', trim($line));
                if (!empty($cleaned)) {
                    $items[] = $cleaned;
                }
            }
            return $items;
        }

        return [];
    }
}

