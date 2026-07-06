<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommentController extends Controller
{
    private const COMMENT_STATUSES = ['active', 'hidden', 'deleted', 'blocked'];
    private const COMMENT_MODERATION_STATUSES = ['clean', 'flagged', 'restricted'];

    public function index(Request $request): View
    {
        $comments = Comment::query()
            ->with(['user:id,username,full_name', 'post:id,caption'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('body', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"))
                        ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('moderation_status'), fn ($query) => $query->where('moderation_status', $request->string('moderation_status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.comments.CommentsPage', compact('comments'));
    }

    public function updateComment(Request $request, Comment $comment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::COMMENT_STATUSES)],
            'moderation_status' => ['required', Rule::in(self::COMMENT_MODERATION_STATUSES)],
        ]);

        $comment->fill($data);
        $comment->is_blocked = $data['status'] === 'blocked' || $data['moderation_status'] === 'restricted';
        $comment->is_reported = $data['moderation_status'] !== 'clean';
        $comment->save();

        return back()->with('status', "Comment #{$comment->id} updated successfully.");
    }

    public function replies(Request $request): View
    {
        $replies = CommentReply::query()
            ->with(['user:id,username,full_name', 'comment:id,body'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('body', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('username', 'like', "%{$search}%"))
                        ->orWhereHas('comment', fn ($query) => $query->where('body', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('moderation_status'), fn ($query) => $query->where('moderation_status', $request->string('moderation_status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.comments.RepliesPage', compact('replies'));
    }

    public function updateReply(Request $request, CommentReply $reply): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::COMMENT_STATUSES)],
            'moderation_status' => ['required', Rule::in(self::COMMENT_MODERATION_STATUSES)],
        ]);

        $reply->fill($data);
        $reply->is_blocked = $data['status'] === 'blocked' || $data['moderation_status'] === 'restricted';
        $reply->is_reported = $data['moderation_status'] !== 'clean';
        $reply->save();

        return back()->with('status', "Reply #{$reply->id} updated successfully.");
    }

    public static function tabCounts(): array
    {
        return [
            'comments' => Comment::count(),
            'replies' => CommentReply::count(),
        ];
    }
}
