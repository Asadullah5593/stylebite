<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ShareLinkController extends Controller
{
    private const PACKAGE = 'com.stylebite.app';

    private const PLAY_STORE = 'https://play.google.com/store/apps/details?id=com.stylebite.app';

    public function post(int $postId): Response
    {
        $post = Post::query()
            ->with([
                'user:id,username,full_name,avatar_url',
                'media' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->find($postId);

        $shareUrl = $this->shareUrl('/p/'.$postId);
        $intentUrl = $this->intentUrl('p/'.$postId);

        if (! $post || ! $this->postIsPublic($post)) {
            return $this->page([
                'title' => 'StyleBite',
                'description' => 'Open this post in the StyleBite app.',
                'image' => null,
                'username' => '',
                'author' => '',
                'caption' => '',
                'kind' => 'Post',
                'shareUrl' => $shareUrl,
                'intentUrl' => $intentUrl,
                'playStoreUrl' => self::PLAY_STORE,
                'available' => false,
            ]);
        }

        $username = trim((string) $post->user?->username);
        $author = trim((string) ($post->user?->full_name ?: $username));
        $caption = trim((string) $post->caption);
        $kind = $this->isReel($post) ? 'Reel' : ($post->post_type === 'food' ? 'Food post' : 'Post');
        $title = $caption !== ''
            ? Str::limit($caption, 80, '...')
            : ($username !== '' ? $kind.' by @'.$username : $kind.' on StyleBite');
        $description = $username !== ''
            ? '@'.$username.' on StyleBite'
            : 'Watch this on StyleBite';

        return $this->page([
            'title' => $title,
            'description' => $description,
            'image' => $this->previewImage($post),
            'username' => $username,
            'author' => $author,
            'caption' => $caption,
            'kind' => $kind,
            'shareUrl' => $shareUrl,
            'intentUrl' => $intentUrl,
            'playStoreUrl' => self::PLAY_STORE,
            'available' => true,
        ]);
    }

    public function profile(string $username): Response
    {
        $handle = strtolower(trim($username, " @"));
        $user = $handle === ''
            ? null
            : User::query()
                ->whereRaw('LOWER(username) = ?', [$handle])
                ->where('status', 'active')
                ->first(['id', 'username', 'full_name', 'avatar_url']);

        $shareUrl = $this->shareUrl('/profile/'.rawurlencode($handle));
        $intentUrl = $this->intentUrl('profile/'.$handle);
        $name = trim((string) ($user?->full_name ?: $user?->username));
        $shown = trim((string) $user?->username);

        return $this->page([
            'title' => $shown !== '' ? '@'.$shown.' on StyleBite' : 'StyleBite',
            'description' => $name !== '' ? $name.' on StyleBite' : 'Open this profile in the StyleBite app.',
            'image' => $this->imageUrl($user?->avatar_url),
            'username' => $shown,
            'author' => $name,
            'caption' => '',
            'kind' => 'Profile',
            'shareUrl' => $shareUrl,
            'intentUrl' => $intentUrl,
            'playStoreUrl' => self::PLAY_STORE,
            'available' => $user !== null,
        ]);
    }

    private function page(array $data): Response
    {
        return response()
            ->view('share.link', $data)
            ->header('Cache-Control', 'public, max-age=120');
    }

    private function postIsPublic(Post $post): bool
    {
        if ($post->status !== 'published' || $post->visibility !== 'public' || $post->is_blocked) {
            return false;
        }
        if (in_array($post->moderation_status, ['blocked', 'restricted'], true)) {
            return false;
        }
        if ($post->is_story && $post->expires_at && $post->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    private function isReel(Post $post): bool
    {
        if ($post->post_type === 'reel' || $post->media_kind === 'video') {
            return true;
        }

        return $post->media->contains(fn ($media) => $media->media_type === 'video');
    }

    private function previewImage(Post $post): ?string
    {
        foreach ($post->media as $media) {
            $candidates = $media->media_type === 'video'
                ? [$media->thumbnail_url]
                : [$media->thumbnail_url, $media->optimized_url, $media->file_url];
            foreach ($candidates as $candidate) {
                $url = $this->imageUrl($candidate);
                if ($url !== null) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function imageUrl(?string $path): ?string
    {
        $url = stylebite_asset_url($path);
        if ($url === null || $url === '') {
            return null;
        }
        $target = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if (preg_match('/\.(jpe?g|png|webp|gif)$/i', $target) !== 1) {
            return null;
        }

        return $url;
    }

    private function shareUrl(string $path): string
    {
        $host = strtolower((string) request()->getHost());
        if (in_array($host, ['stylebiteapp.com', 'www.stylebiteapp.com'], true)) {
            return 'https://'.$host.'/'.ltrim($path, '/');
        }

        return url($path);
    }

    private function intentUrl(string $path): string
    {
        return 'intent://'.ltrim($path, '/')
            .'#Intent;scheme=stylebite;package='.self::PACKAGE
            .';S.browser_fallback_url='.rawurlencode(self::PLAY_STORE)
            .';end';
    }
}
