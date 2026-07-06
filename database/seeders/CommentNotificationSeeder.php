<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CommentNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->updateOrCreate(
            ['id' => 11],
            [
                'username' => 'notification_owner',
                'email' => 'notification.owner@stylebite.test',
                'full_name' => 'Notification Owner',
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'locale' => 'en',
                'timezone' => 'UTC',
            ]
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $author->id],
            [
                'display_name' => 'Notification Owner',
                'bio' => 'Receives seeded comment notifications.',
                'visibility' => 'public',
                'is_private' => false,
                'post_count' => 1,
            ]
        );

        UserSetting::query()->updateOrCreate(
            ['user_id' => $author->id],
            [
                'push_notifications_enabled' => true,
            ]
        );

        $post = Post::query()->updateOrCreate(
            ['id' => 11],
            [
                'user_id' => $author->id,
                'post_type' => 'outfit',
                'content_type' => 'fashion',
                'media_kind' => 'image',
                'feed_type' => 'style',
                'caption' => 'Seeded post used for 11 comment notifications.',
                'visibility' => 'public',
                'status' => 'published',
                'moderation_status' => 'clean',
                'allow_comments' => true,
                'allow_shares' => true,
                'comment_count' => 11,
                'posted_at' => now()->subHours(12),
                'published_at' => now()->subHours(12),
            ]
        );

        for ($index = 1; $index <= 11; $index++) {
            $commenterId = 20 + $index;

            $commenter = User::query()->updateOrCreate(
                ['id' => $commenterId],
                [
                    'username' => 'comment_user_'.$index,
                    'email' => 'comment_user_'.$index.'@stylebite.test',
                    'full_name' => 'Comment User '.$index,
                    'password_hash' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'locale' => 'en',
                    'timezone' => 'UTC',
                ]
            );

            Profile::query()->updateOrCreate(
                ['user_id' => $commenter->id],
                [
                    'display_name' => 'Comment User '.$index,
                    'bio' => 'Seeded commenter '.$index.'.',
                    'visibility' => 'public',
                    'is_private' => false,
                ]
            );

            UserSetting::query()->updateOrCreate(
                ['user_id' => $commenter->id],
                [
                    'push_notifications_enabled' => true,
                ]
            );

            $comment = Comment::query()->updateOrCreate(
                ['id' => 11000 + $index],
                [
                    'post_id' => $post->id,
                    'user_id' => $commenter->id,
                    'body' => 'Seeded comment #'.$index.' for notification testing.',
                    'status' => 'active',
                    'moderation_status' => 'clean',
                    'like_count' => 0,
                    'reply_count' => 0,
                    'created_at' => now()->subMinutes(12 - $index),
                    'updated_at' => now()->subMinutes(12 - $index),
                ]
            );

            Notification::query()->updateOrCreate(
                ['id' => 21000 + $index],
                [
                    'recipient_user_id' => $author->id,
                    'actor_user_id' => $commenter->id,
                    'type' => 'comment',
                    'entity_type' => 'comment',
                    'entity_id' => $comment->id,
                    'title' => 'New comment on your post',
                    'body' => $commenter->full_name.' commented on your post.',
                    'image_url' => null,
                    'action_url' => '/posts/'.$post->id,
                    'is_read' => $index <= 3,
                    'read_at' => $index <= 3 ? now()->subMinutes(10 - $index) : null,
                    'push_sent_at' => now()->subMinutes(12 - $index),
                    'email_sent_at' => null,
                    'delivery_status' => 'sent',
                    'created_at' => now()->subMinutes(12 - $index),
                    'updated_at' => now()->subMinutes(12 - $index),
                ]
            );
        }
    }
}
