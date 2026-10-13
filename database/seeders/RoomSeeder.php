<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            [
                'room_number' => '101',
                'room_type' => 'Couple Room',
                'description' => 'A cozy couple room with standard amenities.',
                'price_per_night' => 1500.00,
                'price_per_head' => 0.00,
                'min_occupancy' => 1,
                'max_occupancy' => 2,
                'bed_type' => 'Queen Bed',
                'room_size' => '20',
                'status' => 'available',
                'image' => '/images/unsplash/standard-room.jpg',
                'gallery' => [
                    '/images/unsplash/room-interior.jpg',
                ],
            ],
            [
                'room_number' => '102',
                'room_type' => 'Couple Room',
                'description' => 'Perfect for couples, featuring a queen-sized bed.',
                'price_per_night' => 2500.00,
                'price_per_head' => 0.00,
                'min_occupancy' => 1,
                'max_occupancy' => 2,
                'bed_type' => 'Queen Bed',
                'room_size' => '22',
                'status' => 'available',
                'image' => '/images/unsplash/standard-room.jpg',
                'gallery' => [
                    '/images/unsplash/room-interior.jpg',
                ],
            ],
            [
                'room_number' => '201',
                'room_type' => 'Family Room',
                'description' => 'Luxurious family room with a mountain view and private balcony.',
                'price_per_night' => 0.00,
                'price_per_head' => 500.00,
                'min_occupancy' => 2,
                'max_occupancy' => 4,
                'bed_type' => 'King Bed',
                'room_size' => '35',
                'status' => 'available',
                'image' => '/images/unsplash/suite-room.jpg',
                'gallery' => [
                    '/images/unsplash/suite-room.jpg',
                    '/images/unsplash/room-interior.jpg',
                ],
            ],
            [
                'room_number' => '202',
                'room_type' => 'Barkadahan Room',
                'description' => 'Spacious room with multiple beds, perfect for group of friends.',
                'price_per_night' => 0.00,
                'price_per_head' => 350.00,
                'min_occupancy' => 4,
                'max_occupancy' => 6,
                'bed_type' => 'Multiple Beds',
                'room_size' => '40',
                'status' => 'available',
                'image' => '/images/unsplash/deluxe-room.jpg',
                'gallery' => [
                    '/images/unsplash/deluxe-room.jpg',
                    '/images/unsplash/room-interior.jpg',
                ],
            ],
        ];

        foreach ($rooms as $roomData) {
            $gallery = $roomData['gallery'] ?? [];
            unset($roomData['gallery']);

            $room = Room::updateOrCreate(
                ['room_number' => $roomData['room_number']],
                $roomData
            );

            foreach ($gallery as $imgPath) {
                $room->images()->firstOrCreate([
                    'image_path' => $imgPath,
                ]);
            }
        }
    }
}
