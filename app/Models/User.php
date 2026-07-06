<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = [];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_online' => 'boolean',
            'is_two_factor_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function authProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class);
    }

    public function passwordResets(): HasMany
    {
        return $this->hasMany(PasswordReset::class);
    }

    public function emailVerificationTokens(): HasMany
    {
        return $this->hasMany(EmailVerificationToken::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function profileBadges(): HasMany
    {
        return $this->hasMany(ProfileBadge::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'following_user_id', 'follower_user_id')
            ->using(UserFollow::class)
            ->withPivot(['id', 'status', 'created_at', 'updated_at', 'deleted_at'])
            ->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_follows', 'follower_user_id', 'following_user_id')
            ->using(UserFollow::class)
            ->withPivot(['id', 'status', 'created_at', 'updated_at', 'deleted_at'])
            ->withTimestamps();
    }

    public function followRequestsSent(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'follower_user_id');
    }

    public function followRequestsReceived(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'following_user_id');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocker_user_id', 'blocked_user_id')
            ->using(UserBlock::class)
            ->withPivot(['id', 'reason', 'created_at']);
    }

    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocked_user_id', 'blocker_user_id')
            ->using(UserBlock::class)
            ->withPivot(['id', 'reason', 'created_at']);
    }

    public function blockedUsersEntries(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_user_id');
    }

    public function blockedByEntries(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_user_id');
    }

    public function mediaUploads(): HasMany
    {
        return $this->hasMany(MediaUpload::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function postRatings(): HasMany
    {
        return $this->hasMany(PostRating::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function commentReplies(): HasMany
    {
        return $this->hasMany(CommentReply::class);
    }

    public function postLikes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function commentLikes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    public function replyLikes(): HasMany
    {
        return $this->hasMany(ReplyLike::class);
    }

    public function postShares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    public function targetedPostShares(): HasMany
    {
        return $this->hasMany(PostShare::class, 'target_user_id');
    }

    public function savedPosts(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    public function savedMemories(): HasMany
    {
        return $this->hasMany(SavedMemory::class);
    }

    public function viewedPosts(): HasMany
    {
        return $this->hasMany(PostView::class, 'viewer_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_user_id');
    }

    public function triggeredNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'actor_user_id');
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function reportsMade(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }

    public function reportsReviewed(): HasMany
    {
        return $this->hasMany(Report::class, 'reviewed_by_user_id');
    }

    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class, 'moderator_user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by_user_id');
    }

    public function conversationMemberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_user_id');
    }

    public function messageReads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function createdContests(): HasMany
    {
        return $this->hasMany(Contest::class, 'creator_user_id');
    }

    public function contestParticipants(): HasMany
    {
        return $this->hasMany(ContestParticipant::class);
    }

    public function contestTeamMemberships(): HasMany
    {
        return $this->hasMany(ContestTeamMember::class);
    }

    public function contestSubmissions(): HasMany
    {
        return $this->hasMany(ContestSubmission::class);
    }

    public function contestVotes(): HasMany
    {
        return $this->hasMany(ContestVote::class, 'voter_user_id');
    }

    public function earningsWallet(): HasOne
    {
        return $this->hasOne(EarningsWallet::class);
    }

    public function earningTransactions(): HasMany
    {
        return $this->hasMany(EarningTransaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function pushNotificationLogs(): HasMany
    {
        return $this->hasMany(PushNotificationLog::class);
    }
}
