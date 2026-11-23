<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Payment;
use App\Models\User;
use App\Models\RoomPackage;
use App\Notifications\PackageBroadcastNotification;
use App\Notifications\PromoBroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics.
     * Cached for 1 minute per user role (staff sees limited data).
     */
    public function getStats(Request $request)
    {
        $isStaff = $request->user() && $request->user()->role === 'staff';
        $cacheKey = 'admin_stats_' . ($isStaff ? 'staff' : 'admin');

        $stats = Cache::remember($cacheKey, 60, function () use ($isStaff) {
            $today = Carbon::today();

            return [
                'today_check_ins' => Reservation::whereDate('check_in', $today)->count(),
                'today_check_outs' => Reservation::whereDate('check_out', $today)->count(),
                'active_reservations' => Reservation::whereIn('status', ['confirmed', 'checked-in'])->count(),
                'available_rooms' => Room::where('status', 'available')->count(),
                'total_revenue' => $isStaff ? 0 : Payment::where('status', 'Succeeded')->sum('amount'),
                'total_reservations' => Reservation::count(),
                'monthly_revenue' => $isStaff ? 0 : Payment::where('status', 'Succeeded')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->sum('amount'),
            ];
        });

        return response()->json($stats);
    }

    public function getRecentReservations()
    {
        $recent = Reservation::with(['room', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        return response()->json($recent);
    }

    public function getGuests()
    {
        $guests = \App\Models\User::whereIn('role', ['client', 'guest'])
            ->withCount('reservations')
            ->orderBy('name', 'asc')
            ->get();
            
        return response()->json($guests);
    }

    public function storeGuest(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = \App\Models\User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => 'guest',
            'password' => \Illuminate\Support\Facades\Hash::make('Welcome123!'), // Default password
        ]);

        return response()->json($user, 201);
    }

    public function getPayments()
    {
        $payments = Payment::with(['reservation.user', 'reservation.room'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($payments);
    }

    public function getGuestHistory(Request $request)
    {
        $email = $request->query('email');
        if (!$email) {
            return response()->json(['message' => 'Email is required'], 400);
        }

        $history = Reservation::with(['room'])
            ->whereHas('user', function ($query) use ($email) {
                $query->where('email', $email);
            })
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($history);
    }

    /**
     * Get reports data array (cached for 5 minutes).
     */
    protected function getReportsData()
    {
        return Cache::remember('admin_reports', 300, function () {
            $firstPayment = Payment::where('status', 'Succeeded')->orderBy('created_at', 'asc')->first();
            
            // Ensure we don't modify the model's created_at by using copy()
            $startDate = $firstPayment 
                ? $firstPayment->created_at->copy()->startOfMonth() 
                : Carbon::now()->subMonths(6)->startOfMonth();
                
            $totalMonths = (int) $startDate->diffInMonths(Carbon::now());
            $monthCount = max(0, $totalMonths);
            
            $allMonths = collect(range(0, $monthCount))->map(function ($i) use ($startDate) {
                return $startDate->copy()->addMonths($i)->format('Y-m');
            });

            $revenueData = Payment::where('status', 'Succeeded')
                ->get()
                ->groupBy(function ($payment) {
                    return $payment->created_at->format('Y-m');
                })
                ->map(function ($group) {
                    return $group->sum('amount');
                });

            $monthlyRevenueResult = $allMonths->map(function ($month) use ($revenueData) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                    'revenue' => (float) ($revenueData->get($month) ?? 0)
                ];
            })->values();

            $statusDistribution = Reservation::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get();

            $roomTypePerformance = Reservation::join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->select('rooms.room_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'), \Illuminate\Support\Facades\DB::raw('sum(total_amount) as total_revenue'))
                ->where('reservations.status', '!=', 'cancelled')
                ->groupBy('rooms.room_type')
                ->get();

            $weeklyBookings = Reservation::where('created_at', '>=', Carbon::now()->subDays(7))
                ->select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'), \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $totalReservations = Reservation::count();

            // FINANCIAL TIME FRAMES
            $weeklyRev = collect(range(7, 0))->map(function ($weeksAgo) {
                $start = Carbon::now()->subWeeks($weeksAgo)->startOfWeek();
                $end = $start->copy()->endOfWeek();
                $rev = Payment::where('status', 'Succeeded')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('amount');
                return [
                    'month' => $start->format('M d'), // use same key for chart mapping
                    'revenue' => (float) $rev
                ];
            })->values()->toArray();

            $yearlyRevData = Payment::where('status', 'Succeeded')
                ->get()
                ->groupBy(function ($payment) {
                    return $payment->created_at->format('Y');
                })
                ->map(function ($group) {
                    return $group->sum('amount');
                });
            
            $firstYear = $firstPayment ? (int) $firstPayment->created_at->format('Y') : (int) date('Y');
            $currentYear = (int) date('Y');
            $yearlyRev = collect(range($firstYear, $currentYear))->map(function ($year) use ($yearlyRevData) {
                return [
                    'month' => (string) $year,
                    'revenue' => (float) ($yearlyRevData->get($year) ?? 0)
                ];
            })->values()->toArray();

            // STATUS TIME FRAMES
            $weeklyStatus = Reservation::where('created_at', '>=', Carbon::now()->subDays(7))
                ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($r) {
                    return ['status' => $r->status, 'count' => (int) $r->count];
                })->toArray();

            $monthlyStatus = Reservation::where('created_at', '>=', Carbon::now()->subDays(30))
                ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($r) {
                    return ['status' => $r->status, 'count' => (int) $r->count];
                })->toArray();

            $yearlyStatus = Reservation::where('created_at', '>=', Carbon::now()->subDays(365))
                ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($r) {
                    return ['status' => $r->status, 'count' => (int) $r->count];
                })->toArray();

            // TRENDS TIME FRAMES
            $weeklyTrends = collect(range(6, 0))->map(function ($daysAgo) {
                $date = Carbon::now()->subDays($daysAgo)->format('Y-m-d');
                $count = Reservation::whereDate('created_at', $date)->count();
                return [
                    'date' => Carbon::parse($date)->format('D M d'),
                    'count' => $count
                ];
            })->values()->toArray();

            $monthlyTrends = collect(range(29, 0))->map(function ($daysAgo) {
                $date = Carbon::now()->subDays($daysAgo)->format('Y-m-d');
                $count = Reservation::whereDate('created_at', $date)->count();
                return [
                    'date' => Carbon::parse($date)->format('M d'),
                    'count' => $count
                ];
            })->values()->toArray();

            $yearlyTrends = collect(range(11, 0))->map(function ($monthsAgo) {
                $monthStart = Carbon::now()->subMonths($monthsAgo)->startOfMonth();
                $count = Reservation::whereBetween('created_at', [$monthStart, $monthStart->copy()->endOfMonth()])->count();
                return [
                    'date' => $monthStart->format('M Y'),
                    'count' => $count
                ];
            })->values()->toArray();

            // ROOM TIME FRAMES
            $weeklyRoom = Reservation::join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->select('rooms.room_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'), \Illuminate\Support\Facades\DB::raw('sum(total_amount) as total_revenue'))
                ->where('reservations.status', '!=', 'cancelled')
                ->where('reservations.created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('rooms.room_type')
                ->get()
                ->map(function ($r) {
                    return [
                        'room_type' => $r->room_type,
                        'count' => (int) $r->count,
                        'total_revenue' => (float) $r->total_revenue
                    ];
                })->toArray();

            $monthlyRoom = Reservation::join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->select('rooms.room_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'), \Illuminate\Support\Facades\DB::raw('sum(total_amount) as total_revenue'))
                ->where('reservations.status', '!=', 'cancelled')
                ->where('reservations.created_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('rooms.room_type')
                ->get()
                ->map(function ($r) {
                    return [
                        'room_type' => $r->room_type,
                        'count' => (int) $r->count,
                        'total_revenue' => (float) $r->total_revenue
                    ];
                })->toArray();

            $yearlyRoom = Reservation::join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->select('rooms.room_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'), \Illuminate\Support\Facades\DB::raw('sum(total_amount) as total_revenue'))
                ->where('reservations.status', '!=', 'cancelled')
                ->where('reservations.created_at', '>=', Carbon::now()->subDays(365))
                ->groupBy('rooms.room_type')
                ->get()
                ->map(function ($r) {
                    return [
                        'room_type' => $r->room_type,
                        'count' => (int) $r->count,
                        'total_revenue' => (float) $r->total_revenue
                    ];
                })->toArray();

            return [
                'monthly_revenue' => $monthlyRevenueResult,
                'status_distribution' => $statusDistribution,
                'room_performance' => $roomTypePerformance,
                'weekly_trends' => $weeklyBookings,
                'summary' => [
                    'total_revenue' => (float) Payment::where('status', 'Succeeded')->sum('amount'),
                    'total_bookings' => $totalReservations,
                    'avg_booking_value' => (float) Reservation::avg('total_amount'),
                    'cancellation_rate' => $totalReservations > 0 ? (Reservation::where('status', 'cancelled')->count() / $totalReservations) * 100 : 0
                ],
                'timeframes' => [
                    'financial_performance' => [
                        'weekly' => $weeklyRev,
                        'monthly' => $monthlyRevenueResult,
                        'yearly' => $yearlyRev
                    ],
                    'booking_status' => [
                        'weekly' => $weeklyStatus,
                        'monthly' => $monthlyStatus,
                        'yearly' => $yearlyStatus
                    ],
                    'booking_trends' => [
                        'weekly' => $weeklyTrends,
                        'monthly' => $monthlyTrends,
                        'yearly' => $yearlyTrends
                    ],
                    'room_performance' => [
                        'weekly' => $weeklyRoom,
                        'monthly' => $monthlyRoom,
                        'yearly' => $yearlyRoom
                    ]
                ]
            ];
        });
    }

    /**
     * Get reports data as JSON for admin dashboard.
     */
    public function getReports()
    {
        return response()->json($this->getReportsData());
    }

    /**
     * Export analytical reports as a formatted PDF.
     */
    public function exportReportsPdf(Request $request)
    {
        $reports = $this->getReportsData();

        $financialTimeframe = $request->query('financial_timeframe', 'monthly');
        $statusTimeframe = $request->query('status_timeframe', 'yearly');
        $trendsTimeframe = $request->query('trends_timeframe', 'weekly');
        $roomTimeframe = $request->query('room_timeframe', 'yearly');

        $financialData = $reports['timeframes']['financial_performance'][$financialTimeframe] ?? $reports['monthly_revenue'];
        $statusData = $reports['timeframes']['booking_status'][$statusTimeframe] ?? $reports['status_distribution'];
        $trendsData = $reports['timeframes']['booking_trends'][$trendsTimeframe] ?? $reports['weekly_trends'];
        $roomData = $reports['timeframes']['room_performance'][$roomTimeframe] ?? $reports['room_performance'];
        $summary = $reports['summary'];

        $user = $request->user();
        $generatedBy = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Administrator';
        if (empty($generatedBy)) {
            $generatedBy = $user->name ?? 'Administrator';
        }
        $generatedAt = Carbon::now()->format('F d, Y - h:i A');

        $pdf = Pdf::loadView('reports.pdf', compact(
            'summary',
            'financialData',
            'financialTimeframe',
            'statusData',
            'statusTimeframe',
            'trendsData',
            'trendsTimeframe',
            'roomData',
            'roomTimeframe',
            'generatedBy',
            'generatedAt'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('eme_apartelle_report_' . Carbon::now()->format('Y-m-d') . '.pdf');
    }

    public function suspendGuest(Request $request, $id)
    {
        $user = \App\Models\User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Guest not found'], 404);
        }

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $user->update([
            'is_suspended' => true,
            'suspension_reason' => $request->reason,
        ]);

        $user->notify(new \App\Notifications\AccountSuspended($request->reason));

        return response()->json(['message' => 'Guest suspended successfully']);
    }

    public function unsuspendGuest(Request $request, $id)
    {
        $user = \App\Models\User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Guest not found'], 404);
        }

        $user->update([
            'is_suspended' => false,
            'suspension_reason' => null,
        ]);

        $user->notify(new \App\Notifications\AccountRestored());

        return response()->json(['message' => 'Guest unsuspended successfully']);
    }

    public function getStaffList()
    {
        $staff = \App\Models\User::where('role', 'staff')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($staff);
    }

    public function storeStaff(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $user = \App\Models\User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => 'staff',
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        return response()->json($user, 201);
    }

    public function deleteStaff($id)
    {
        $user = \App\Models\User::where('role', 'staff')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Staff member not found'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Staff account deleted successfully']);
    }

    /**
     * Get active promotional rooms and eligible guest count for broadcasting.
     */
    public function getActivePromos(Request $request)
    {
        $promoRooms = Room::where('is_promo', true)
            ->where('status', 'available')
            ->orderByRaw('CAST(room_number AS UNSIGNED) ASC, room_number ASC')
            ->get()
            ->map(function ($r) {
                $isPerHead = in_array($r->room_type, ['Family Room', 'Barkadahan Room']);
                return [
                    'id' => $r->id,
                    'room_number' => $r->room_number,
                    'room_type' => $r->room_type,
                    'promo_title' => $r->promo_title ?: 'Special Promo',
                    'promo_discount_percent' => $r->promo_discount_percent ?: 0,
                    'original_price' => $isPerHead ? (float)$r->price_per_head : (float)$r->price_per_night,
                    'effective_price' => $isPerHead ? (float)$r->effective_price_per_head : (float)$r->effective_price,
                    'pricing_unit' => $isPerHead ? '/ head / night' : '/ night',
                ];
            });

        $activeGuestCount = User::whereIn('role', ['client', 'guest'])
            ->where(function ($q) {
                $q->whereNull('is_suspended')->orWhere('is_suspended', false);
            })
            ->count();

        $promoRoomNames = $promoRooms->map(function ($r) {
            return "Room #{$r['room_number']} ({$r['room_type']})";
        })->join(', ');

        $defaultSubject = $promoRooms->isNotEmpty()
            ? "Special Room Promotion Alert at EME's Apartelle!"
            : "Special Announcement from EME's Apartelle";

        $defaultMessage = $promoRooms->isNotEmpty()
            ? "Exciting news! We have exclusive promotional discounts currently available on selected rooms ({$promoRoomNames}). Book now to enjoy special discounted rates while availability lasts!"
            : "We are delighted to share exclusive special rates and updates with our valued guests. Visit our website to explore our rooms and book your next stay!";

        return response()->json([
            'promo_rooms' => $promoRooms,
            'recipient_count' => $activeGuestCount,
            'default_subject' => $defaultSubject,
            'default_message' => $defaultMessage,
        ]);
    }

    /**
     * Broadcast promotion to guests via email and in-app database notification.
     */
    public function broadcastPromo(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'guest_id' => 'nullable|exists:users,id',
        ]);

        $promoRooms = Room::where('is_promo', true)
            ->where('status', 'available')
            ->orderByRaw('CAST(room_number AS UNSIGNED) ASC, room_number ASC')
            ->get()
            ->map(function ($r) {
                $isPerHead = in_array($r->room_type, ['Family Room', 'Barkadahan Room']);
                return [
                    'id' => $r->id,
                    'room_number' => $r->room_number,
                    'room_type' => $r->room_type,
                    'promo_title' => $r->promo_title,
                    'promo_discount_percent' => $r->promo_discount_percent,
                    'effective_price' => $isPerHead ? (float)$r->effective_price_per_head : (float)$r->effective_price,
                ];
            })->toArray();

        $query = User::query();

        if ($request->filled('guest_id')) {
            $query->where('id', $request->guest_id);
        } else {
            $query->whereIn('role', ['client', 'guest'])
                ->where(function ($q) {
                    $q->whereNull('is_suspended')->orWhere('is_suspended', false);
                });
        }

        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible active guests found to send to.',
            ], 422);
        }

        $sentCount = 0;
        $emailErrors = 0;

        $notification = new PromoBroadcastNotification(
            $request->subject,
            $request->message,
            $promoRooms
        );

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify($notification);
                $sentCount++;
            } catch (\Exception $e) {
                $emailErrors++;
                Log::warning("Promo mail notification failed for user {$recipient->id} ({$recipient->email}): " . $e->getMessage());
                // Fallback: Ensure database notification is always stored even if SMTP encounters an issue
                try {
                    $recipient->notifications()->create([
                        'id' => Str::uuid(),
                        'type' => PromoBroadcastNotification::class,
                        'data' => [
                            'type' => 'promo',
                            'icon' => 'bi bi-tag-fill',
                            'title' => $request->subject,
                            'message' => $request->message,
                            'action_url' => '/rooms',
                            'action_text' => 'View Deals',
                        ],
                        'read_at' => null,
                    ]);
                    $sentCount++;
                } catch (\Exception $dbErr) {
                    Log::error("Promo DB fallback failed for user {$recipient->id}: " . $dbErr->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Promotional announcement successfully sent to {$sentCount} guest(s).",
            'sent_count' => $sentCount,
            'email_errors' => $emailErrors,
        ]);
    }

    /**
     * Get active room packages and eligible guest count for broadcasting.
     */
    public function getActivePackages(Request $request)
    {
        $packages = RoomPackage::with('room:id,room_number,room_type')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->map(function ($pkg) {
                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'tagline' => $pkg->tagline,
                    'duration' => $pkg->duration,
                    'pax' => $pkg->pax,
                    'price' => (float) $pkg->price,
                    'room_type_label' => $pkg->room_type_label ?: ($pkg->room->room_type ?? null),
                    'room_number' => $pkg->room->room_number ?? null,
                    'tour_type' => $pkg->tour_type,
                ];
            });

        $activeGuestCount = User::whereIn('role', ['client', 'guest'])
            ->where(function ($q) {
                $q->whereNull('is_suspended')->orWhere('is_suspended', false);
            })
            ->count();

        $packageNames = $packages->pluck('name')->join(', ');

        $defaultSubject = $packages->isNotEmpty()
            ? "Exclusive Room Packages at EME's Apartelle!"
            : "Special Stay Packages from EME's Apartelle";

        $defaultMessage = $packages->isNotEmpty()
            ? "Discover our curated room packages ({$packageNames}) designed for a memorable and hassle-free stay! Book your preferred package now while slots are available."
            : "Explore our special stay packages and exclusive offers at EME's Apartelle. Visit our website to view available packages and book your next getaway!";

        return response()->json([
            'packages' => $packages,
            'recipient_count' => $activeGuestCount,
            'default_subject' => $defaultSubject,
            'default_message' => $defaultMessage,
        ]);
    }

    /**
     * Broadcast room packages to guests via email and in-app database notification.
     */
    public function broadcastPackage(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'guest_id' => 'nullable|exists:users,id',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer|exists:room_packages,id',
        ]);

        $pkgQuery = RoomPackage::with('room:id,room_number,room_type')
            ->where('is_active', true);

        if ($request->filled('package_ids')) {
            $pkgQuery->whereIn('id', $request->input('package_ids'));
        }

        $packages = $pkgQuery->orderByDesc('id')
            ->get()
            ->map(function ($pkg) {
                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'tagline' => $pkg->tagline,
                    'duration' => $pkg->duration,
                    'pax' => $pkg->pax,
                    'price' => (float) $pkg->price,
                    'room_type_label' => $pkg->room_type_label ?: ($pkg->room->room_type ?? null),
                    'room_number' => $pkg->room->room_number ?? null,
                    'tour_type' => $pkg->tour_type,
                ];
            })->toArray();

        $query = User::query();

        if ($request->filled('guest_id')) {
            $query->where('id', $request->guest_id);
        } else {
            $query->whereIn('role', ['client', 'guest'])
                ->where(function ($q) {
                    $q->whereNull('is_suspended')->orWhere('is_suspended', false);
                });
        }

        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible active guests found to send to.',
            ], 422);
        }

        $sentCount = 0;
        $emailErrors = 0;

        $notification = new PackageBroadcastNotification(
            $request->subject,
            $request->message,
            $packages
        );

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify($notification);
                $sentCount++;
            } catch (\Exception $e) {
                $emailErrors++;
                Log::warning("Package mail notification failed for user {$recipient->id} ({$recipient->email}): " . $e->getMessage());
                try {
                    $recipient->notifications()->create([
                        'id' => Str::uuid(),
                        'type' => PackageBroadcastNotification::class,
                        'data' => [
                            'type' => 'package',
                            'icon' => 'bi bi-box2-heart-fill',
                            'title' => $request->subject,
                            'message' => $request->message,
                            'action_url' => '/rooms',
                            'action_text' => 'View Packages',
                        ],
                        'read_at' => null,
                    ]);
                    $sentCount++;
                } catch (\Exception $dbErr) {
                    Log::error("Package DB fallback failed for user {$recipient->id}: " . $dbErr->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Package offer successfully sent to {$sentCount} guest(s).",
            'sent_count' => $sentCount,
            'email_errors' => $emailErrors,
        ]);
    }
}
