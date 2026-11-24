<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // รับข้อมูลสมาชิกจาก Session Middleware
        $member = $request->attributes->get('room_member');

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid room session',
            ], 401);
        }

        // ตรวจสอบข้อมูลพิกัด
        $validated = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'source' => [
                'required',
                'in:gps,manual',
            ],
        ]);

        $room = $member->room;

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found',
            ], 404);
        }

        try {
            $location = DB::transaction(function () use (
                $member,
                $room,
                $validated
            ) {
                // ล็อกห้อง ป้องกันการบันทึกพิกัด
                // พร้อมกับการเริ่มค้นหาจุดนัดพบ
                $lockedRoom = DB::table('rooms')
                    ->where('id', $room->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedRoom || $lockedRoom->status !== 'waiting') {
                    throw new \RuntimeException(
                        'Room is not accepting location updates'
                    );
                }

                // ค้นหา Host
                $host = $room->members()
                    ->where('role', 'host')
                    ->first();

                if (!$host) {
                    throw new \RuntimeException(
                        'Room host not found'
                    );
                }

                // ตรวจสอบว่า Host ส่งตำแหน่งแล้วหรือยัง
                $hostLocation = Location::where(
                    'member_id',
                    $host->id
                )->exists();

                if (!$hostLocation && $member->id !== $host->id) {
                    throw new \RuntimeException(
                        'Please wait for the host to submit their location'
                    );
                }

                // บันทึกพิกัด
                $location = Location::updateOrCreate(
                    ['member_id' => $member->id],
                    [
                        'latitude' => $validated['latitude'],
                        'longitude' => $validated['longitude'],
                        'source' => $validated['source'],
                    ]
                );

                // เปลี่ยนสถานะสมาชิก
                $member->update([
                    'status' => 'ready',
                ]);

                return $location;
            });

        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            $statusCode = match ($message) {
                'Room is not accepting location updates' => 409,
                'Room host not found' => 409,
                'Please wait for the host to submit their location' => 403,
                default => 409,
            };

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => 'Location saved successfully',
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'nickname' => $member->nickname,
                    'status' => 'ready',
                ],
                'location' => [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'source' => $location->source,
                    'updated_at' => $location->updated_at,
                ],
            ],
        ]);
    }
}
