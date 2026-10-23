<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Models\ChatbotResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // Get messages for current user (Guest view)
    public function index(Request $request)
    {
        $user = Auth::user();
        $type = $request->query('type', 'support');

        // If admin or staff, use support/admin conversation list
        if (in_array($user->role, ['admin', 'staff'])) {
            return $this->getAdminConversations($request);
        }

        // Guest: get conversation with Support (Admin / Staff)
        $supportUserIds = User::whereIn('role', ['admin', 'staff'])->pluck('id');
        $isChatbot = ($type === 'chatbot');

        // Proper SQL grouping to retrieve messages exchanged with support team
        $messages = Message::where('is_chatbot', $isChatbot)
            ->where(function($q) use ($user, $supportUserIds) {
                $q->where(function($inner) use ($user, $supportUserIds) {
                    $inner->where('sender_id', $user->id)
                          ->whereIn('receiver_id', $supportUserIds);
                })->orWhere(function($inner) use ($user, $supportUserIds) {
                    $inner->whereIn('sender_id', $supportUserIds)
                          ->where('receiver_id', $user->id);
                });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    // Admin & Staff: Get list of recent conversations
    private function getAdminConversations(Request $request)
    {
        $user = Auth::user();
        $type = ($user && $user->role === 'staff') ? 'support' : $request->query('type', 'support');
        $supportUserIds = User::whereIn('role', ['admin', 'staff'])->pluck('id');

        if ($type === 'suspended') {
            $users = User::whereNotIn('role', ['admin', 'staff'])
                ->where('is_suspended', true)
                ->get();
            $userIds = $users->pluck('id');
            $isChatbot = false;
        } else {
            $isChatbot = ($type === 'chatbot');

            // Get IDs of guests who have sent/received messages to/from support team (filtered by type)
            $userIds = Message::where('is_chatbot', $isChatbot)
                ->where(function($q) use ($supportUserIds) {
                    $q->whereIn('sender_id', $supportUserIds)
                      ->orWhereIn('receiver_id', $supportUserIds);
                })
                ->get()
                ->map(function($msg) use ($supportUserIds) {
                    return $supportUserIds->contains($msg->sender_id) ? $msg->receiver_id : $msg->sender_id;
                })
                ->filter(function($id) use ($supportUserIds) {
                    return !$supportUserIds->contains($id);
                })
                ->unique();
        }

        $users = User::whereIn('id', $userIds)->get()->map(function($user) use ($supportUserIds, $isChatbot) {
             $lastMessage = Message::where('is_chatbot', $isChatbot)
                ->where(function($q) use ($user, $supportUserIds) {
                    $q->where(function($inner) use ($user, $supportUserIds) {
                        $inner->where('sender_id', $user->id)->whereIn('receiver_id', $supportUserIds);
                    })->orWhere(function($inner) use ($user, $supportUserIds) {
                        $inner->whereIn('sender_id', $supportUserIds)->where('receiver_id', $user->id);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->first();

             $unreadCount = Message::where('sender_id', $user->id)
                ->whereIn('receiver_id', $supportUserIds)
                ->where('is_read', false)
                ->where('is_chatbot', $isChatbot)
                ->count();

             $user->last_message = $lastMessage;
             $user->unread_count = $unreadCount;
             return $user;
        })->sortByDesc(function($user) {
            return $user->last_message ? $user->last_message->created_at : 0;
        })->values();

        return response()->json($users);
    }

    // Admin & Staff: Get messages with specific user
    public function show(Request $request, $userId)
    {
        $user = Auth::user();
        $type = ($user && $user->role === 'staff') ? 'support' : $request->query('type', 'support');
        $isChatbot = ($type === 'chatbot');

        if (!in_array(Auth::user()->role, ['admin', 'staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $supportUserIds = User::whereIn('role', ['admin', 'staff'])->pluck('id');

        $messages = Message::where('is_chatbot', $isChatbot)
            ->where(function($q) use ($userId, $supportUserIds) {
                $q->where(function($inner) use ($userId, $supportUserIds) {
                    $inner->whereIn('sender_id', $supportUserIds)->where('receiver_id', $userId);
                })->orWhere(function($inner) use ($userId, $supportUserIds) {
                    $inner->where('sender_id', $userId)->whereIn('receiver_id', $supportUserIds);
                });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark incoming messages from guest as read for the support team
        Message::where('sender_id', $userId)
            ->whereIn('receiver_id', $supportUserIds)
            ->where('is_read', false)
            ->where('is_chatbot', $isChatbot)
            ->update(['is_read' => true]);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required_without:image|string|nullable',
            'image' => 'nullable|image|max:5120', // 5MB max
            'receiver_id' => 'nullable|exists:users,id',
            'is_chatbot' => 'nullable|boolean'
        ]);

        $sender = Auth::user();
        $receiverId = $request->receiver_id;

        if ($sender->role === 'guest' || $sender->role === 'client') {
             $supportTarget = User::where('role', 'admin')->first() ?: User::where('role', 'staff')->first();
             if (!$supportTarget) return response()->json(['message' => 'No support recipient available'], 500);
             $receiverId = $supportTarget->id;
        } else if (in_array($sender->role, ['admin', 'staff'])) {
             if (!$receiverId) return response()->json(['message' => 'Receiver required'], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = '/storage/' . $request->file('image')->store('chat', 'public');
        }

        // Explicit boolean conversion: FormData sends '1' or '0' as strings
        $isChatbot = filter_var($request->is_chatbot, FILTER_VALIDATE_BOOLEAN);

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'message' => $request->message ?? '',
            'image' => $imagePath,
            'is_read' => false,
            'is_chatbot' => $isChatbot
        ]);

        // Chatbot logic: Only trigger if coming from chatbot interface and sender is a guest
        if (!in_array($sender->role, ['admin', 'staff']) && $request->message && $isChatbot) {
            $this->handleChatbot($sender->id, $request->message, $isChatbot);
        }

        // Notify receiver if it's a guest being messaged by support (admin or staff)
        if (in_array($sender->role, ['admin', 'staff'])) {
            $receiver = User::find($receiverId);
            if ($receiver && !in_array($receiver->role, ['admin', 'staff'])) {
                $snippet = $request->message ?? 'Sent an image attachment';
                if (strlen($snippet) > 100) $snippet = substr($snippet, 0, 97) . '...';
                $receiver->notify(new \App\Notifications\NewMessageReceived($sender->name, $snippet));
            }
        } 
        // Notify Support (both Admin and Staff) if it's a Guest sending a Support message
        else if (!$isChatbot) {
            $supportTeam = User::whereIn('role', ['admin', 'staff'])->get();
            $snippet = $request->message ?? 'Sent an image attachment';
            if (strlen($snippet) > 100) $snippet = substr($snippet, 0, 97) . '...';
            foreach ($supportTeam as $member) {
                $member->notify(new \App\Notifications\NewMessageReceived($sender->name, $snippet));
            }
        }

        return response()->json($message, 201);
    }

    private function handleChatbot($guestId, $incomingMessage, $isChatbotSource = false)
    {
        // Strictly only respond if the message came from the chatbot interface
        if (!$isChatbotSource) return;

        $supportUser = User::where('role', 'admin')->first() ?: User::where('role', 'staff')->first();
        if (!$supportUser) return;

        $rules = ChatbotResponse::where('is_active', true)->get();

        foreach ($rules as $rule) {
            $matched = false;
            
            // Support comma-separated triggers: "Time, Check, Open" becomes ["Time", "Check", "Open"]
            $triggers = array_map('trim', explode(',', $rule->trigger));

            if ($rule->match_type === 'exact') {
                // Exact match: entire message must equal ANY trigger (case-insensitive)
                foreach ($triggers as $trigger) {
                    if (strcasecmp(trim($incomingMessage), $trigger) === 0) {
                        $matched = true;
                        break;
                    }
                }
            } else {
                // Contains match: ANY trigger must appear as a WHOLE WORD (case-insensitive)
                foreach ($triggers as $trigger) {
                    if (empty($trigger)) continue;
                    // Using word boundaries \b to ensure "Time" doesn't match "Sometimes"
                    $pattern = '/\b' . preg_quote($trigger, '/') . '\b/i';
                    if (preg_match($pattern, $incomingMessage)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ($matched) {
                $quickReplies = $rule->suggested_triggers 
                    ? array_filter(array_map('trim', explode(',', $rule->suggested_triggers))) 
                    : null;

                Message::create([
                    'sender_id' => $supportUser->id,
                    'receiver_id' => $guestId,
                    'message' => $rule->response,
                    'quick_replies' => $quickReplies,
                    'is_read' => false,
                    'is_chatbot' => true
                ]);
                
                return; // Stop after first match
            }
        }

        // Fallback: If no keywords matched
        $guest = User::find($guestId);
        $firstName = $guest ? explode(' ', $guest->name)[0] : 'there';

        Message::create([
            'sender_id' => $supportUser->id,
            'receiver_id' => $guestId,
            'message' => "Hi {$firstName}! I didn't quite catch that. Could you try asking about our **WiFi**, **Price**, or **Location**? You can also switch to **Live Chat** to speak with our staff.",
            'is_read' => false,
            'is_chatbot' => true
        ]);
    }

    public function markAsRead(Request $request, $senderId) {
        $user = Auth::user();
        $type = $request->query('type', 'support');

        if (in_array($user->role, ['admin', 'staff'])) {
            $supportUserIds = User::whereIn('role', ['admin', 'staff'])->pluck('id');
            Message::where('sender_id', $senderId)
                   ->whereIn('receiver_id', $supportUserIds)
                   ->where('is_chatbot', $type === 'chatbot')
                   ->update(['is_read' => true]);
        } else {
            Message::where('sender_id', $senderId)
                   ->where('receiver_id', $user->id)
                   ->where('is_chatbot', $type === 'chatbot')
                   ->update(['is_read' => true]);
        }
        
        return response()->json(['message' => 'Marked as read']);
    }

    public function getUnreadCount(Request $request)
    {
        $user = Auth::user();
        $type = $request->query('type');
        $isSupportRole = in_array($user->role, ['admin', 'staff']);
        $supportUserIds = $isSupportRole ? User::whereIn('role', ['admin', 'staff'])->pluck('id') : collect([$user->id]);

        if ($isSupportRole) {
            if ($user->role === 'staff') {
                $supportCount = Message::whereIn('receiver_id', $supportUserIds)
                                ->where('is_read', false)
                                ->where('is_chatbot', false)
                                ->count();
                return response()->json([
                    'support' => $supportCount,
                    'chatbot' => 0,
                    'total' => $supportCount,
                    'count' => $supportCount
                ]);
            }

            if ($type) {
                $count = Message::whereIn('receiver_id', $supportUserIds)
                                ->where('is_read', false)
                                ->where('is_chatbot', $type === 'chatbot')
                                ->count();
                return response()->json(['count' => $count]);
            }

            $supportCount = Message::whereIn('receiver_id', $supportUserIds)
                            ->where('is_read', false)
                            ->where('is_chatbot', false)
                            ->count();

            $chatbotCount = Message::whereIn('receiver_id', $supportUserIds)
                            ->where('is_read', false)
                            ->where('is_chatbot', true)
                            ->count();
            
            return response()->json([
                'support' => $supportCount,
                'chatbot' => $chatbotCount,
                'total' => $supportCount + $chatbotCount,
                'count' => $supportCount + $chatbotCount
            ]);
        }

        if ($type) {
            $count = Message::where('receiver_id', $user->id)
                            ->where('is_read', false)
                            ->where('is_chatbot', $type === 'chatbot')
                            ->count();
            return response()->json(['count' => $count]);
        }

        $supportCount = Message::where('receiver_id', $user->id)
                        ->where('is_read', false)
                        ->where('is_chatbot', false)
                        ->count();

        $chatbotCount = Message::where('receiver_id', $user->id)
                        ->where('is_read', false)
                        ->where('is_chatbot', true)
                        ->count();
        
        return response()->json([
            'support' => $supportCount,
            'chatbot' => $chatbotCount,
            'total' => $supportCount + $chatbotCount,
            'count' => $supportCount + $chatbotCount
        ]);
    }

    public function markAllFromAdminAsRead(Request $request) {
        $userId = Auth::id(); // Guest
        $type = $request->query('type', 'support');
        
        $supportUserIds = User::whereIn('role', ['admin', 'staff'])->pluck('id');

        Message::whereIn('sender_id', $supportUserIds)
               ->where('receiver_id', $userId)
               ->where('is_chatbot', $type === 'chatbot')
               ->update(['is_read' => true]);
        
        return response()->json(['message' => 'Messages marked as read']);
    }

    public function destroy(Request $request, $userId)
    {
        $user = Auth::user();
        $type = ($user && $user->role === 'staff') ? 'support' : $request->query('type', 'support');
        $isChatbot = ($type === 'chatbot');

        if (!in_array($user->role, ['admin', 'staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $supportUserIds = User::whereIn('role', ['admin', 'staff'])->pluck('id');

        // Delete all messages between this user and the support team for the given type
        Message::where('is_chatbot', $isChatbot)
            ->where(function($q) use ($userId, $supportUserIds) {
                $q->where(function($inner) use ($userId, $supportUserIds) {
                    $inner->whereIn('sender_id', $supportUserIds)->where('receiver_id', $userId);
                })->orWhere(function($inner) use ($userId, $supportUserIds) {
                    $inner->where('sender_id', $userId)->whereIn('receiver_id', $supportUserIds);
                });
            })
            ->delete();

        return response()->json(['message' => 'Conversation deleted successfully']);
    }
}
