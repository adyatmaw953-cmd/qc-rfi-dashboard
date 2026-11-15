<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter');

        try {
            $query = $user->notifications();

            if ($filter === 'unread') {
                $query->whereNull('read_at');
            }

            $notifications = $query->latest()->paginate(15)->withQueryString();
            $unreadCount = $user->unreadNotifications()->count();
            $totalCount = $user->notifications()->count();
        } catch (Throwable) {
            $notifications = new LengthAwarePaginator([], 0, 15);
            $unreadCount = 0;
            $totalCount = 0;
        }

        return view('modules.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
            'currentFilter' => $filter,
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $targetUrl = $notification->data['url'] ?? route('notifications.index');

        return redirect()->to($targetUrl);
    }

    public function readAll(Request $request): RedirectResponse
    {
        try {
            $request->user()->unreadNotifications->markAsRead();
        } catch (Throwable) {
            // Table may not exist yet
        }

        return back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    }
}
