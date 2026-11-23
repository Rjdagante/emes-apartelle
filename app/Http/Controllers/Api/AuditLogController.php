<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * Build the filtered query for audit logs.
     */
    protected function buildFilteredQuery(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'LIKE', "%{$search}%")
                  ->orWhere('actor_email', 'LIKE', "%{$search}%")
                  ->orWhere('guest_name', 'LIKE', "%{$search}%")
                  ->orWhere('guest_email', 'LIKE', "%{$search}%")
                  ->orWhere('room_number', 'LIKE', "%{$search}%")
                  ->orWhere('room_type', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('action', 'LIKE', "%{$search}%")
                  ->orWhere('entity_id', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('action') && $request->input('action') !== 'all') {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('actor_role') && $request->input('actor_role') !== 'all') {
            $role = strtolower($request->input('actor_role'));
            if ($role === 'staff_and_admin') {
                $query->whereIn('actor_role', ['staff', 'admin']);
            } else {
                $query->where('actor_role', $role);
            }
        }

        if ($request->filled('room_id') && $request->input('room_id') !== 'all') {
            $query->where('room_id', $request->input('room_id'));
        }

        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->input('start_date'))->startOfDay();
            $query->where('created_at', '>=', $start);
        }

        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->input('end_date'))->endOfDay();
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }

    /**
     * Display a paginated listing of audit logs along with booking activity summaries.
     */
    public function index(Request $request)
    {
        $filteredQuery = $this->buildFilteredQuery($request);

        $maxPerPage = $request->boolean('all') ? 1000 : 100;
        $perPage = min(max((int) $request->input('per_page', 15), 5), $maxPerPage);
        $sort = $request->input('sort', 'latest');

        $logsQuery = clone $filteredQuery;
        if ($sort === 'oldest') {
            $logsQuery->orderBy('id', 'asc');
        } else {
            // Order by latest recorded/updated activity first so recent updates always appear on top
            $logsQuery->orderByRaw('CASE WHEN updated_at > created_at THEN updated_at ELSE NULL END DESC')
                      ->orderByDesc('id');
        }

        $logs = $logsQuery->paginate($perPage);

        // Summary metrics across the filtered date/search/room scope (for booking_created events)
        $bookingScopeQuery = $this->buildFilteredQuery(new Request(
            $request->only(['search', 'room_id', 'start_date', 'end_date', 'actor_role'])
        ))->where('action', 'booking_created');

        $totalActivities = (clone $filteredQuery)->count();
        $totalBookings = (clone $bookingScopeQuery)->count();
        $totalGuestsBooked = (int) (clone $bookingScopeQuery)->sum('guests_count');
        $totalAdultsBooked = (int) (clone $bookingScopeQuery)->sum('adults_count');
        $totalChildrenBooked = (int) (clone $bookingScopeQuery)->sum('children_count');
        $bookedByGuest = (clone $bookingScopeQuery)->where('actor_role', 'guest')->count();
        $bookedByStaff = (clone $bookingScopeQuery)->where('actor_role', 'staff')->count();
        $bookedByAdmin = (clone $bookingScopeQuery)->where('actor_role', 'admin')->count();

        $rooms = Room::select('id', 'room_number', 'room_type')
            ->orderBy('room_type')
            ->orderByRaw('CAST(room_number AS UNSIGNED), room_number')
            ->get();

        // Per-room booking summary (how many bookings & guests per room, and staff vs guest split)
        $roomSummaryQuery = $this->buildFilteredQuery(new Request(
            $request->only(['search', 'start_date', 'end_date', 'actor_role'])
        ))->where('action', 'booking_created');

        $roomStatsById = $roomSummaryQuery
            ->select([
                'room_id',
                DB::raw('COALESCE(room_number, "-") as room_number'),
                DB::raw('COALESCE(room_type, "Unknown Room") as room_type'),
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw('COALESCE(SUM(guests_count), 0) as total_guests'),
                DB::raw('COALESCE(SUM(adults_count), 0) as total_adults'),
                DB::raw('COALESCE(SUM(children_count), 0) as total_children'),
                DB::raw("SUM(CASE WHEN actor_role = 'guest' THEN 1 ELSE 0 END) as guest_bookings"),
                DB::raw("SUM(CASE WHEN actor_role IN ('staff', 'admin') THEN 1 ELSE 0 END) as staff_bookings"),
                DB::raw('MAX(created_at) as last_booked_at'),
            ])
            ->groupBy('room_id', 'room_number', 'room_type')
            ->get()
            ->keyBy('room_id');

        $searchTerm = strtolower(trim((string) $request->input('search', '')));

        $roomSummary = $rooms->map(function ($room) use ($roomStatsById) {
            $stat = $roomStatsById->get($room->id);
            return [
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'room_type' => $room->room_type,
                'bookings_count' => $stat ? (int) $stat->bookings_count : 0,
                'total_guests' => $stat ? (int) $stat->total_guests : 0,
                'total_adults' => $stat ? (int) $stat->total_adults : 0,
                'total_children' => $stat ? (int) $stat->total_children : 0,
                'guest_bookings' => $stat ? (int) $stat->guest_bookings : 0,
                'staff_bookings' => $stat ? (int) $stat->staff_bookings : 0,
                'last_booked_at' => $stat ? $stat->last_booked_at : null,
            ];
        })->when($searchTerm !== '', function ($collection) use ($searchTerm) {
            return $collection->filter(function ($item) use ($searchTerm) {
                if ($item['bookings_count'] > 0) {
                    return true;
                }
                return str_contains(strtolower((string) $item['room_type']), $searchTerm)
                    || str_contains(strtolower((string) $item['room_number']), $searchTerm);
            });
        })->sortBy([
            ['bookings_count', 'desc'],
            ['total_guests', 'desc'],
            ['room_type', 'asc'],
            [fn ($a, $b) => strnatcasecmp((string) $a['room_number'], (string) $b['room_number'])],
        ])->values();

        $availableActions = AuditLog::distinct()
            ->pluck('action')
            ->filter()
            ->values();

        return response()->json([
            'logs' => $logs,
            'summary' => [
                'total_activities' => $totalActivities,
                'total_bookings' => $totalBookings,
                'total_guests_booked' => $totalGuestsBooked,
                'total_adults_booked' => $totalAdultsBooked,
                'total_children_booked' => $totalChildrenBooked,
                'booked_by_guest' => $bookedByGuest,
                'booked_by_staff' => $bookedByStaff,
                'booked_by_admin' => $bookedByAdmin,
                'booked_by_staff_total' => $bookedByStaff + $bookedByAdmin,
            ],
            'room_summary' => $roomSummary,
            'rooms' => $rooms,
            'available_actions' => $availableActions,
        ]);
    }

    /**
     * Export filtered audit logs as CSV.
     */
    public function export(Request $request)
    {
        $sort = $request->input('sort', 'latest');
        $query = $this->buildFilteredQuery($request);

        if ($sort === 'oldest') {
            $query->orderBy('id', 'asc');
        } else {
            $query->orderByRaw('CASE WHEN updated_at > created_at THEN updated_at ELSE NULL END DESC')
                  ->orderByDesc('id');
        }

        $logs = $query->get();

        $filename = 'audit-logs-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Log ID',
                'Date & Time',
                'Activity',
                'Performed By',
                'Role',
                'Actor Email',
                'Booking #',
                'Guest Name',
                'Room',
                'Total Guests',
                'Adults',
                'Children',
                'Check-in',
                'Check-out',
                'Total Amount (PHP)',
                'Payment Status',
                'Description',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at ? $log->created_at->format('Y-m-d h:i:s A') : '',
                    ucwords(str_replace('_', ' ', $log->action)),
                    $log->actor_name ?? 'System',
                    ucfirst($log->actor_role ?? 'guest'),
                    $log->actor_email ?? '',
                    $log->entity_id ? ('#RES-' . str_pad($log->entity_id, 3, '0', STR_PAD_LEFT)) : '',
                    $log->guest_name ?? '',
                    trim(($log->room_type ?? '') . ($log->room_number ? " #{$log->room_number}" : '')),
                    $log->guests_count,
                    $log->adults_count,
                    $log->children_count,
                    $log->check_in ? $log->check_in->format('Y-m-d h:i A') : '',
                    $log->check_out ? $log->check_out->format('Y-m-d h:i A') : '',
                    $log->total_amount !== null ? number_format((float) $log->total_amount, 2, '.', '') : '',
                    $log->payment_status ? ucwords(str_replace('_', ' ', $log->payment_status)) : '',
                    $log->description ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
