<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PostTwoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->updateOrCreate(
            ['id' => 1],
            [
                'username' => 'stylebite_author',
                'email' => 'author@stylebite.test',
                'full_name' => 'Stylebite Author',
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'locale' => 'en',
                'timezone' => 'UTC',
            ]
        );

        $commenter = User::query()->updateOrCreate(
            ['id' => 2],
            [
                'username' => 'stylebite_commenter',
                'email' => 'commenter@stylebite.test',
                'full_name' => 'Stylebite Commenter',
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'locale' => 'en',
                'timezone' => 'UTC',
            ]
        );

        $replier = User::query()->updateOrCreate(
            ['id' => 3],
            [
                'username' => 'stylebite_replier',
                'email' => 'replier@stylebite.test',
                'full_name' => 'Stylebite Replier',
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'locale' => 'en',
                'timezone' => 'UTC',
            ]
        );

        foreach ([
            $author->id => ['display_name' => 'Style Author', 'bio' => 'Seeded author for feed demos.'],
            $commenter->id => ['display_name' => 'First Commenter', 'bio' => 'Leaves the first reply.'],
            $replier->id => ['display_name' => 'Nested Replier', 'bio' => 'Replies in threads.'],
        ] as $userId => $profileData) {
            Profile::query()->updateOrCreate(
                ['user_id' => $userId],
                array_merge($profileData, [
                    'visibility' => 'public',
                    'is_private' => false,
                ])
            );

            UserSetting::query()->updateOrCreate(
                ['user_id' => $userId],
                []
            );
        }

        $post = Post::query()->updateOrCreate(
            ['id' => 2],
            [
                'user_id' => $author->id,
                'post_type' => 'outfit',
                'content_type' => 'fashion',
                'media_kind' => 'image',
                'feed_type' => 'style',
                'caption' => 'Seeded outfit post for post id 2.',
                'visibility' => 'public',
                'status' => 'published',
                'moderation_status' => 'clean',
                'allow_comments' => true,
                'allow_shares' => true,
                'like_count' => 0,
                'comment_count' => 2,
                'share_count' => 0,
                'save_count' => 0,
                'view_count' => 0,
                'rating_count' => 0,
                'rating_enabled' => false,
                'posted_at' => now()->subDay(),
                'published_at' => now()->subDay(),
            ]
        );

        $upload = MediaUpload::query()->updateOrCreate(
            ['id' => 2],
            [
                'user_id' => $author->id,
                'source' => 'gallery',
                'upload_type' => 'post_media',
                'media_type' => 'image',
                'original_file_name' => 'seed-post-2.jpeg',
                'file_path' => 'posts/'.$author->id.'/seed-post-2.jpeg',
                'file_url' => 'posts/'.$author->id.'/seed-post-2.jpeg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 245760,
                'storage_type' => 'local',
                'upload_status' => 'ready',
                'uploaded_at' => now()->subDay(),
            ]
        );

        PostMedia::query()->updateOrCreate(
            ['id' => 2],
            [
                'post_id' => $post->id,
                'upload_id' => $upload->id,
                'media_type' => 'image',
                'media_role' => 'original',
                'file_path' => 'posts/'.$author->id.'/seed-post-2.jpeg',
                'file_url' => 'posts/'.$author->id.'/seed-post-2.jpeg',
                'thumbnail_url' => null,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 245760,
                'storage_type' => 'local',
                'sort_order' => 0,
                'processing_status' => 'ready',
            ]
        );

        $commentOne = Comment::query()->updateOrCreate(
            ['id' => 2001],
            [
                'post_id' => $post->id,
                'user_id' => $commenter->id,
                'body' => 'Love the texture and color balance on this outfit.',
                'status' => 'active',
                'moderation_status' => 'clean',
                'like_count' => 0,
                'reply_count' => 2,
            ]
        );

        $commentTwo = Comment::query()->updateOrCreate(
            ['id' => 2002],
            [
                'post_id' => $post->id,
                'user_id' => $replier->id,
                'body' => 'The styling feels polished and super wearable.',
                'status' => 'active',
                'moderation_status' => 'clean',
                'like_count' => 0,
                'reply_count' => 1,
            ]
        );

        $replyOne = CommentReply::query()->updateOrCreate(
            ['id' => 3001],
            [
                'comment_id' => $commentOne->id,
                'parent_reply_id' => null,
                'user_id' => $author->id,
                'body' => 'Appreciate it, I was going for a clean everyday look.',
                'status' => 'active',
                'moderation_status' => 'clean',
                'like_count' => 0,
                'reply_count' => 1,
            ]
        );

        CommentReply::query()->updateOrCreate(
            ['id' => 3002],
            [
                'comment_id' => $commentOne->id,
                'parent_reply_id' => $replyOne->id,
                'user_id' => $replier->id,
                'body' => 'That comes through really well in the final photo.',
                'status' => 'active',
                'moderation_status' => 'clean',
                'like_count' => 0,
                'reply_count' => 0,
            ]
        );

        CommentReply::query()->updateOrCreate(
            ['id' => 3003],
            [
                'comment_id' => $commentTwo->id,
                'parent_reply_id' => null,
                'user_id' => $commenter->id,
                'body' => 'Agreed, it looks ready for the app feed right away.',
                'status' => 'active',
                'moderation_status' => 'clean',
                'like_count' => 0,
                'reply_count' => 0,
            ]
        );
    }
}
