<?php

namespace App\Http\Middleware;

use App\Models\RoomMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRoomSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // รับ Token จาก Header
        $token = $request->header('X-Session-Token');

        if (!$token || !is_string($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Session token is required',
            ], 401);
        }

        // แปลง Token เป็น SHA-256 Hash
        $tokenHash = hash('sha256', $token);

        // ค้นหาสมาชิกจาก Token
        $member = RoomMember::where(
            'session_token_hash',
            $tokenHash
        )->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session token',
            ], 401);
        }

        // สมาชิกที่ออกจากห้องแล้วไม่มีสิทธิ์ใช้งาน
        if ($member->status === 'left') {
            return response()->json([
                'success' => false,
                'message' => 'You have left this room',
            ], 403);
        }
        // ตรวจสอบสถานะห้อง
        $room = $member->room;

        if (
            !$room ||
            $room->status === 'closed' ||
            ($room->expires_at && $room->expires_at->isPast())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Room is closed or expired',
            ], 410);
        }

        // ส่งข้อมูลสมาชิกไปยัง Controller
        $request->attributes->set('room_member', $member);

        return $next($request);
    }
}
