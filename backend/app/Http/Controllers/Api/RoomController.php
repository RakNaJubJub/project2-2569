<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // ตรวจสอบข้อมูลที่รับมาจาก Frontend
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'nickname' => ['required', 'string', 'min:1', 'max:30'],
            'travel_mode' => ['required', 'in:walking,driving'],
        ]);

        // สร้าง PIN 6 หลักที่ยังไม่ถูกใช้งาน
        do {
            $pin = (string) random_int(100000, 999999);
        } while (Room::where('pin', $pin)->exists());

        // สร้าง Session Token สำหรับ Host
        $sessionToken = Str::random(64);

        // สร้างห้องและ Host ใน Transaction เดียวกัน
        $result = DB::transaction(function () use (
            $validated,
            $pin,
            $sessionToken
        ) {
            $room = Room::create([
                'name' => $validated['name'] ?? null,
                'pin' => $pin,
                'travel_mode' => $validated['travel_mode'],
                'status' => 'waiting',
                'expires_at' => now()->addHours(12),
            ]);

            $host = RoomMember::create([
                'room_id' => $room->id,
                'nickname' => $validated['nickname'],
                'session_token_hash' => hash('sha256', $sessionToken),
                'role' => 'host',
                'status' => 'waiting',
                'joined_at' => now(),
            ]);

            // กำหนดสมาชิกคนนี้ให้เป็น Host ของห้อง
            $room->update([
                'host_member_id' => $host->id,
            ]);

            return [
                'room' => $room,
                'host' => $host,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Room created successfully',
            'data' => [
                'room' => [
                    'id' => $result['room']->id,
                    'name' => $result['room']->name,
                    'pin' => $result['room']->pin,
                    'travel_mode' => $result['room']->travel_mode,
                    'status' => $result['room']->status,
                    'expires_at' => $result['room']->expires_at,
                ],
                'member' => [
                    'id' => $result['host']->id,
                    'nickname' => $result['host']->nickname,
                    'role' => $result['host']->role,
                    'status' => $result['host']->status,
                ],
                // แสดง Token จริงครั้งนี้ครั้งเดียว
                'session_token' => $sessionToken,
            ],
        ], 201);
    }
    
    public function join(Request $request): JsonResponse
    {
        // ตรวจสอบข้อมูล
        $validated = $request->validate([
            'pin' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'nickname' => ['required', 'string', 'min:1', 'max:30'],
        ]);

        $nickname = trim($validated['nickname']);

        if ($nickname === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nickname cannot be empty',
            ], 422);
        }

        // สร้าง Token สำหรับสมาชิกใหม่
        $sessionToken = Str::random(64);

        // ตรวจสอบห้องและสร้างสมาชิกใน Transaction
        $result = DB::transaction(function () use (
            $validated,
            $nickname,
            $sessionToken
        ) {
            $room = Room::where('pin', $validated['pin'])
                ->lockForUpdate()
                ->first();

            // ไม่พบห้องหรือห้องหมดอายุ
            if (
                !$room ||
                ($room->expires_at && $room->expires_at->isPast())
            ) {
                return [
                    'error' => 'Room not found or expired',
                    'status' => 404,
                ];
            }

            // ห้องต้องอยู่ในสถานะรอสมาชิก
            if ($room->status !== 'waiting') {
                return [
                    'error' => 'Room is not accepting new members',
                    'status' => 409,
                ];
            }

            // ป้องกันชื่อเล่นซ้ำภายในห้องเดียวกัน
            $nicknameExists = $room->members()
                ->where('nickname', $nickname)
                ->exists();

            if ($nicknameExists) {
                return [
                    'error' => 'Nickname already exists in this room',
                    'status' => 409,
                ];
            }

            // สร้างสมาชิกใหม่
            $member = $room->members()->create([
                'nickname' => $nickname,
                'session_token_hash' => hash('sha256', $sessionToken),
                'role' => 'member',
                'status' => 'waiting',
                'joined_at' => now(),
            ]);

            return [
                'room' => $room,
                'member' => $member,
            ];
        });

        // ส่งข้อผิดพลาดกลับไป
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], $result['status']);
        }

        // ส่งข้อมูลสมาชิกกลับไป
        return response()->json([
            'success' => true,
            'message' => 'Joined room successfully',
            'data' => [
                'room' => [
                    'id' => $result['room']->id,
                    'name' => $result['room']->name,
                    'pin' => $result['room']->pin,
                    'travel_mode' => $result['room']->travel_mode,
                    'status' => $result['room']->status,
                ],
                'member' => [
                    'id' => $result['member']->id,
                    'nickname' => $result['member']->nickname,
                    'role' => $result['member']->role,
                    'status' => $result['member']->status,
                ],
                'session_token' => $sessionToken,
            ],
        ], 201);
    }

    public function members(Request $request, string $pin): JsonResponse
    {
        // ดึงข้อมูลสมาชิกที่ผ่าน Middleware แล้ว
        $currentMember = $request->attributes->get('room_member');

        // ค้นหาห้องจาก PIN
        $room = Room::where('pin', $pin)->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found',
            ], 404);
        }

        // ตรวจสอบว่าสมาชิกอยู่ในห้องที่กำลังเรียกดูหรือไม่
        if ($currentMember->room_id !== $room->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this room',
            ], 403);
        }

        // ตรวจสอบห้องหมดอายุหรือถูกปิด
        if (
            $room->status === 'closed' ||
            ($room->expires_at && $room->expires_at->isPast())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This room is closed or expired',
            ], 410);
        }

        // ดึงรายชื่อสมาชิก
        $members = $room->members()
            ->orderBy('id')
            ->get([
                'id',
                'nickname',
                'role',
                'status',
                'joined_at',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Members retrieved successfully',
            'data' => [
                'room' => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'pin' => $room->pin,
                    'status' => $room->status,
                    'travel_mode' => $room->travel_mode,
                    'host_member_id' => $room->host_member_id,
                ],
                'current_member' => [
                    'id' => $currentMember->id,
                    'nickname' => $currentMember->nickname,
                    'role' => $currentMember->role,
                ],
                'members' => $members,
                'total_members' => $members->count(),
            ],
        ]);
    }

    public function status(Request $request, string $pin): JsonResponse
    {
        $currentMember = $request->attributes->get('room_member');

        // ค้นหาห้อง
        $room = Room::where('pin', $pin)->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found',
            ], 404);
        }

        // ตรวจสอบสมาชิกในห้อง
        if ((int) $currentMember->room_id !== (int) $room->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this room',
            ], 403);
        }

        if (
            $room->status === 'closed' ||
            ($room->expires_at && $room->expires_at->isPast())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Room is closed or expired',
            ], 410);
        }

        // ดึงสมาชิกที่ยังอยู่ในห้อง
        $members = $room->members()
            ->where('status', '!=', 'left')
            ->orderBy('id')
            ->get(['id', 'nickname', 'role', 'status']);

        // ดึงพิกัดทั้งหมดของสมาชิกในห้อง
        $locations = Location::whereIn(
            'member_id',
            $members->pluck('id')
        )->get()->keyBy('member_id');

        // รวมข้อมูลสมาชิกกับพิกัด
        $mapMembers = $members->map(function ($member) use (
            $locations,
            $room
        ) {
            $location = $locations->get($member->id);

            return [
                'id' => $member->id,
                'nickname' => $member->nickname,
                'role' => $member->role,
                'status' => $member->status,
                'is_host' => (int) $member->id ===
                            (int) $room->host_member_id,

                'location' => $location ? [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'source' => $location->source,
                    'updated_at' => $location->updated_at,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'room' => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'pin' => $room->pin,
                    'travel_mode' => $room->travel_mode,
                    'status' => $room->status,
                    'host_member_id' => $room->host_member_id,
                ],
                'current_member_id' => $currentMember->id,
                'total_members' => $mapMembers->count(),
                'ready_members' => $mapMembers
                    ->where('status', 'ready')->count(),
                'waiting_members' => $mapMembers
                    ->where('status', 'waiting')->count(),
                'members' => $mapMembers->values(),
            ],
        ]);
    }

    /**
     * เริ่มค้นหาจุดนัดพบ
     */
    public function startFinding(Request $request, string $pin): JsonResponse
    {
        $currentMember = $request->attributes->get('room_member');

        $result = DB::transaction(function () use ($pin, $currentMember) {

            // ค้นหาห้องและล็อกข้อมูลระหว่างดำเนินการ
            $room = Room::where('pin', $pin)
                ->lockForUpdate()
                ->first();

            if (!$room) {
                return [
                    'error' => 'Room not found',
                    'status' => 404,
                ];
            }

            // ตรวจสอบว่าสมาชิกอยู่ในห้องนี้จริง
            if ((int) $currentMember->room_id !== (int) $room->id) {
                return [
                    'error' => 'You are not a member of this room',
                    'status' => 403,
                ];
            }

            // ตรวจสอบห้องหมดอายุ
            if ($room->expires_at && $room->expires_at->isPast()) {
                return [
                    'error' => 'Room has expired',
                    'status' => 410,
                ];
            }

            // ตรวจสอบว่าเป็น Host จริงหรือไม่
            if ((int) $currentMember->id !== (int) $room->host_member_id) {
                return [
                    'error' => 'Only the Host can start finding',
                    'status' => 403,
                ];
            }

            // ห้องต้องอยู่ในสถานะ waiting
            if ($room->status !== 'waiting') {
                return [
                    'error' => 'Room is not in waiting status',
                    'status' => 409,
                ];
            }

            // ดึงสมาชิกที่ยังอยู่ในห้อง
            $members = $room->members()
                ->where('status', '!=', 'left')
                ->orderBy('id')
                ->get();

            if ($members->isEmpty()) {
                return [
                    'error' => 'No members in this room',
                    'status' => 422,
                ];
            }

            // ตรวจสอบว่าสมาชิกทุกคนพร้อมแล้ว
            $notReadyMembers = $members->filter(function ($member) {
                return $member->status !== 'ready';
            });

            if ($notReadyMembers->isNotEmpty()) {
                return [
                    'error' => 'Not all members have submitted their locations',
                    'status' => 422,
                    'missing_members' => $notReadyMembers->pluck('nickname')->values(),
                ];
            }

            // ดึงพิกัดของสมาชิกทุกคน
            $locations = Location::whereIn(
                'member_id',
                $members->pluck('id')
            )->get()->keyBy('member_id');

            // ตรวจสอบว่ามีพิกัดครบทุกคน
            $missingLocations = $members->filter(function ($member) use ($locations) {
                return !$locations->has($member->id);
            });

            if ($missingLocations->isNotEmpty()) {
                return [
                    'error' => 'Some members do not have locations',
                    'status' => 422,
                    'missing_members' => $missingLocations->pluck('nickname')->values(),
                ];
            }

            // สร้าง Snapshot พิกัด ณ เวลาที่เริ่มค้นหา
            $snapshot = $members->map(function ($member) use ($locations) {
                $location = $locations->get($member->id);

                return [
                    'member_id' => $member->id,
                    'nickname' => $member->nickname,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                ];
            })->values();

            // เปลี่ยนสถานะห้อง
            $room->update([
                'status' => 'calculating',
            ]);

            return [
                'success' => true,
                'room' => $room->fresh(),
                'snapshot' => $snapshot,
            ];
        });

        // จัดการข้อผิดพลาด
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'missing_members' => $result['missing_members'] ?? [],
            ], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Finding started successfully',
            'data' => [
                'room' => [
                    'id' => $result['room']->id,
                    'pin' => $result['room']->pin,
                    'status' => $result['room']->status,
                    'travel_mode' => $result['room']->travel_mode,
                ],
                'total_members' => $result['snapshot']->count(),
                'snapshot' => $result['snapshot'],
            ],
        ]);
    }


}
