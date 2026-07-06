<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const SCOPES = ['all', 'users', 'posts', 'memories', 'contests', 'messages'];

    public function index(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $scope = $request->string('scope', 'all')->toString();

        if (! in_array($scope, self::SCOPES, true)) {
            $scope = 'all';
        }

        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'scope' => $scope,
                'groups' => [],
                'summary' => [
                    'total_results' => 0,
                    'message' => 'Type at least 2 characters to search.',
                ],
            ]);
        }

        $groups = collect([
            $this->searchUsers($query, $scope),
            $this->searchPosts($query, $scope),
            $this->searchMemories($query, $scope),
            $this->searchContests($query, $scope),
            $this->searchMessages($query, $scope),
        ])->filter(fn (?array $group) => $group !== null && count($group['items']) > 0)
            ->values();

        return response()->json([
            'query' => $query,
            'scope' => $scope,
            'groups' => $groups,
            'summary' => [
                'total_results' => $groups->sum(fn (array $group) => count($group['items'])),
                'message' => $groups->isEmpty() ? 'No matching admin results found.' : null,
            ],
        ]);
    }

    private function searchUsers(string $query, string $scope): ?array
    {
        if (! in_array($scope, ['all', 'users'], true)) {
            return null;
        }

        $items = User::query()
            ->withTrashed()
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('full_name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'title' => $user->full_name ?: '@'.$user->username,
                'subtitle' => collect([
                    '@'.$user->username,
                    $user->email,
                    str($user->status)->title()->toString(),
                ])->filter()->implode(' · '),
                'url' => route('admin.users.show', $user->id),
                'icon' => 'bi-person',
            ])
            ->values()
            ->all();

        return [
            'key' => 'users',
            'label' => 'Users',
            'icon' => 'bi-people',
            'items' => $items,
        ];
    }

    private function searchPosts(string $query, string $scope): ?array
    {
        if (! in_array($scope, ['all', 'posts'], true)) {
            return null;
        }

        $items = Post::query()
            ->with('user:id,username,full_name')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('caption', 'like', "%{$query}%")
                    ->orWhere('restaurant', 'like', "%{$query}%")
                    ->orWhere('dish_name', 'like', "%{$query}%")
                    ->orWhere('location_name', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Post $post) => [
                'title' => str($post->caption ?: 'Untitled post')->limit(70)->toString(),
                'subtitle' => collect([
                    'Post #'.$post->id,
                    $post->user?->full_name ?: ($post->user?->username ? '@'.$post->user->username : null),
                    $post->city,
                ])->filter()->implode(' · '),
                'url' => route('admin.posts.show', $post->id),
                'icon' => 'bi-file-earmark-text',
            ])
            ->values()
            ->all();

        return [
            'key' => 'posts',
            'label' => 'Posts',
            'icon' => 'bi-grid',
            'items' => $items,
        ];
    }

    private function searchMemories(string $query, string $scope): ?array
    {
        if (! in_array($scope, ['all', 'memories'], true)) {
            return null;
        }

        $items = Memory::query()
            ->with('user:id,username,full_name')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('title', 'like', "%{$query}%")
                    ->orWhere('short_title', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('note', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Memory $memory) => [
                'title' => $memory->title ?: 'Untitled memory',
                'subtitle' => collect([
                    'Memory #'.$memory->id,
                    $memory->user?->full_name ?: ($memory->user?->username ? '@'.$memory->user->username : null),
                    $memory->city,
                ])->filter()->implode(' · '),
                'url' => route('admin.memories.memories'),
                'icon' => 'bi-images',
            ])
            ->values()
            ->all();

        return [
            'key' => 'memories',
            'label' => 'Memories',
            'icon' => 'bi-bookmark-heart',
            'items' => $items,
        ];
    }

    private function searchContests(string $query, string $scope): ?array
    {
        if (! in_array($scope, ['all', 'contests'], true)) {
            return null;
        }

        $items = Contest::query()
            ->with('creator:id,username,full_name')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('title', 'like', "%{$query}%")
                    ->orWhere('subtitle', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Contest $contest) => [
                'title' => $contest->title,
                'subtitle' => collect([
                    'Contest #'.$contest->id,
                    str($contest->status)->replace('_', ' ')->title()->toString(),
                    $contest->city,
                ])->filter()->implode(' · '),
                'url' => route('admin.contests.contests', ['q' => $contest->title]),
                'icon' => 'bi-trophy',
            ])
            ->values()
            ->all();

        return [
            'key' => 'contests',
            'label' => 'Contests',
            'icon' => 'bi-trophy',
            'items' => $items,
        ];
    }

    private function searchMessages(string $query, string $scope): ?array
    {
        if (! in_array($scope, ['all', 'messages'], true)) {
            return null;
        }

        $items = Message::query()
            ->with('sender:id,username,full_name', 'conversation:id,title')
            ->where('body', 'like', "%{$query}%")
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Message $message) => [
                'title' => str($message->body ?: 'Empty message')->limit(70)->toString(),
                'subtitle' => collect([
                    'Message #'.$message->id,
                    $message->sender?->full_name ?: ($message->sender?->username ? '@'.$message->sender->username : null),
                    $message->conversation?->title,
                ])->filter()->implode(' · '),
                'url' => route('admin.messaging.messages', ['q' => $message->body]),
                'icon' => 'bi-chat-left-text',
            ])
            ->values()
            ->all();

        return [
            'key' => 'messages',
            'label' => 'Messages',
            'icon' => 'bi-chat-dots',
            'items' => $items,
        ];
    }
}
