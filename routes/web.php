<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\ContestController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EarningsController;
use App\Http\Controllers\Admin\EngagementController;
use App\Http\Controllers\Admin\MemoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MessagingController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SocialController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\UserController;
use App\Mail\GlobalAppMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index', [
        'appStoreUrl' => config('services.stylebite.app_store_url', '#'),
        'playStoreUrl' => config('services.stylebite.play_store_url', '#'),
    ]);
})->name('home');

Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');
Route::view('/delete-account', 'delete-account')->name('delete-account');

Route::get('/preview/email-template', function () {
    return new GlobalAppMail(
        'Stylebite Email Preview',
        'Welcome to Stylebite',
        "This is a preview of the global Stylebite email template.\n\nYou can use this layout for account verification, password reset codes, notifications, and other user emails.",
        'Open Stylebite',
        config('app.url')
    );
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return auth()->check()
            && auth()->user()->role === 'admin'
            && auth()->user()->status === 'active'
                ? redirect()->route('admin.dashboard')
                : redirect()->route('admin.login');
    })->name('home');

    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
    // Main Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Search
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
        Route::put('/settings', [AccountController::class, 'update'])->name('update');
    });

    // Users Module
     Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('all_users');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/profiles', [UserController::class, 'profiles'])->name('profiles');
        Route::get('/settings', [UserController::class, 'settings'])->name('settings');
        Route::get('/auth-providers', [UserController::class, 'authProviders'])->name('auth_providers');
        Route::get('/sessions', [UserController::class, 'sessions'])->name('sessions'); 
        Route::get('/devices', [UserController::class, 'devices'])->name('devices'); 
        Route::get('/password-resets', [UserController::class, 'passwordResets'])->name('password_resets'); 
        Route::get('/{user}', [UserController::class, 'show'])->withTrashed()->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->withTrashed()->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->withTrashed()->name('update');
        Route::patch('/{user}/suspend', [UserController::class, 'suspend'])->name('suspend');
        Route::patch('/{user}/status', [UserController::class, 'changeStatus'])->withTrashed()->name('status');
        Route::patch('/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('restore');
        Route::patch('/{user}/badges', [UserController::class, 'updateBadge'])->withTrashed()->name('badges.update');
        Route::patch('/{user}/badge/verified', [UserController::class, 'toggleVerifiedBadge'])->withTrashed()->name('badge.verified');
        Route::patch('/{user}/sessions/{session}/revoke', [UserController::class, 'revokeSession'])->withTrashed()->name('sessions.revoke');
        Route::patch('/{user}/devices/{device}/toggle', [UserController::class, 'toggleDevice'])->withTrashed()->name('devices.toggle');
        Route::patch('/{user}/password-resets/{passwordReset}/expire', [UserController::class, 'expirePasswordReset'])->withTrashed()->name('password_resets.expire');
        Route::delete('/{user}', [UserController::class, 'destroy'])->withTrashed()->name('destroy');
    });
   

    // Social Module
     Route::prefix('social')->name('social.')->group(function () {
        Route::get('/', [SocialController::class, 'follows'])->name('follows');
        Route::delete('/follows/{follow}', [SocialController::class, 'deleteFollow'])->name('follows.delete');
        Route::get('/blocks', [SocialController::class, 'blocks'])->name('blocks');
        Route::delete('/blocks/{block}', [SocialController::class, 'deleteBlock'])->name('blocks.delete');
    });

    // Content Module
    Route::prefix('comments')->name('comments.')->group(function () {
        Route::get('/', [CommentController::class, 'index'])->name('comments');
        Route::get('/replies', [CommentController::class, 'replies'])->name('replies');
        Route::patch('/{comment}', [CommentController::class, 'updateComment'])->name('update');
        Route::patch('/replies/{reply}', [CommentController::class, 'updateReply'])->name('replies.update');
    });

    Route::prefix('engagement')->name('engagement.')->group(function () {
        Route::get('/', [EngagementController::class, 'postLikes'])->name('post_likes');
        Route::get('/comment-likes', [EngagementController::class, 'commentLikes'])->name('comment_likes');
        Route::get('/reply-likes', [EngagementController::class, 'replyLikes'])->name('reply_likes');
        Route::get('/shares', [EngagementController::class, 'shares'])->name('shares');
        Route::get('/saved', [EngagementController::class, 'saved'])->name('saved');
        Route::get('/views', [EngagementController::class, 'views'])->name('views');
    });

    Route::prefix('media')->name('media.')->group(function () {
        Route::get('/', [MediaController::class, 'uploads'])->name('uploads');
        Route::get('/tags', [MediaController::class, 'tags'])->name('tags');
    });

     Route::prefix('memories')->name('memories.')->group(function () {
        Route::get('/', [MemoryController::class, 'index'])->name('memories');
        Route::get('/memory-media', [MemoryController::class, 'media'])->name('memory-media');
        Route::get('/memory-comments', [MemoryController::class, 'comments'])->name('memory-comments');
        Route::patch('/memory-comments/{memoryComment}', [MemoryController::class, 'updateComment'])->name('memory-comments.update');
        Route::get('/saved-memories', [MemoryController::class, 'saved'])->name('saved_memories');
    });

    // Post Module
     Route::prefix('posts')->name('posts.')->group(function () {
        Route::get('/', [PostController::class, 'index'])->name('all_posts');
        Route::get('/post-media', [PostController::class, 'media'])->name('post_media');
        Route::get('/post-ratings', [PostController::class, 'ratings'])->name('post_ratings');
        Route::get('/post-tags', [PostController::class, 'tags'])->name('post_tags');
        Route::get('/{post}', [PostController::class, 'show'])->name('show');
        Route::get('/{post}/edit', [PostController::class, 'edit'])->name('edit');
        Route::put('/{post}', [PostController::class, 'update'])->name('update');
        Route::patch('/{post}/moderate', [PostController::class, 'moderate'])->name('moderate');
    });

    // Communication Module
     Route::prefix('messaging')->name('messaging.')->group(function () {
        Route::get('/', [MessagingController::class, 'conversations'])->name('conversations');
        Route::patch('/{conversation}', [MessagingController::class, 'updateConversation'])->name('conversations.update');
        Route::get('/{conversation}/export', [MessagingController::class, 'exportConversation'])->name('conversations.export');
        Route::get('/conversation-members', [MessagingController::class, 'members'])->name('members');
        Route::get('/messages', [MessagingController::class, 'messages'])->name('messages');
        Route::patch('/messages/{message}', [MessagingController::class, 'updateMessage'])->name('messages.update');
        Route::get('/attachments', [MessagingController::class, 'attachments'])->name('attachments');
        Route::patch('/attachments/{attachment}', [MessagingController::class, 'updateAttachment'])->name('attachments.update');
        Route::get('/read-receipts', [MessagingController::class, 'reads'])->name('reads');
    });

     Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'notifications'])->name('notifications');
        Route::post('/announcements', [NotificationController::class, 'sendAnnouncement'])->name('announcements.send');
        Route::get('/push-logs', [NotificationController::class, 'pushLogs'])->name('push_logs');
        Route::post('/push-logs/{pushLog}/retry', [NotificationController::class, 'retryPushLog'])->name('push_logs.retry');
        Route::get('/saved-searches', [NotificationController::class, 'savedSearches'])->name('saved_searches');
    });

    // Moderation and Activity
     Route::prefix('moderation')->name('moderation.')->group(function () {
        Route::get('/', [ModerationController::class, 'reports'])->name('reports');       
        Route::get('/actions', [ModerationController::class, 'actions'])->name('actions');
        Route::patch('/reports/{report}/assign', [ModerationController::class, 'assign'])->name('reports.assign');
        Route::patch('/reports/{report}', [ModerationController::class, 'updateReport'])->name('reports.update');
        Route::patch('/reports/{report}/target', [ModerationController::class, 'updateTarget'])->name('reports.target.update');
        Route::patch('/actions/{moderationAction}/expiry', [ModerationController::class, 'updateActionExpiry'])->name('actions.expiry');
    });

     Route::prefix('activity')->name('activity.')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('activity_logs');
    });

    // Monetization Module
    Route::prefix('contests')->name('contests.')->group(function () {
        Route::get('/', [ContestController::class, 'contests'])->name('contests');
        Route::get('/create', [ContestController::class, 'createContest'])->name('create');
        Route::get('/{contest}/edit', [ContestController::class, 'editContest'])->name('edit');
        Route::post('/', [ContestController::class, 'storeContest'])->name('store');
        Route::patch('/{contest}', [ContestController::class, 'updateContest'])->name('update');
        Route::patch('/{contest}/workflow', [ContestController::class, 'updateContestWorkflow'])->name('workflow.update');
        Route::post('/{contest}/recalculate', [ContestController::class, 'recalculateContest'])->name('recalculate');
        Route::post('/{contest}/leaderboard-snapshot', [ContestController::class, 'regenerateLeaderboardSnapshot'])->name('leaderboards.regenerate');
        Route::get('/contest-rules', [ContestController::class, 'rules'])->name('contest_rules');
        Route::get('/participants', [ContestController::class, 'participants'])->name('participants');
        Route::post('/invitations', [ContestController::class, 'storeInvitation'])->name('invitations.store');
        Route::patch('/participants/{participant}', [ContestController::class, 'updateParticipant'])->name('participants.update');
        Route::get('/invitations', [ContestController::class, 'invitations'])->name('invitations');
        Route::patch('/invitations/{invitation}', [ContestController::class, 'updateInvitation'])->name('invitations.update');
        Route::get('/teams', [ContestController::class, 'teams'])->name('teams');
        Route::get('/team-members', [ContestController::class, 'teamMembers'])->name('team_members');
        Route::get('/submissions', [ContestController::class, 'submissions'])->name('submissions');
        Route::patch('/submissions/{submission}', [ContestController::class, 'updateSubmission'])->name('submissions.update');
        Route::get('/votes', [ContestController::class, 'votes'])->name('votes');
        Route::get('/leader-boards', [ContestController::class, 'leaderboards'])->name('leaderboards');

    });

    Route::prefix('earnings')->name('earnings.')->group(function () {
        Route::get('/', [EarningsController::class, 'wallets'])->name('wallets');
        Route::get('/transactions', [EarningsController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/export', [EarningsController::class, 'exportTransactions'])->name('transactions.export');
        Route::post('/transactions/{transaction}/reverse', [EarningsController::class, 'reverseTransaction'])->name('transactions.reverse');
        Route::get('/withdrawals', [EarningsController::class, 'withdrawals'])->name('withdrawals');
        Route::get('/withdrawals/export', [EarningsController::class, 'exportWithdrawals'])->name('withdrawals.export');
        Route::patch('/withdrawals/{withdrawal}', [EarningsController::class, 'updateWithdrawal'])->name('withdrawals.update');
        Route::get('/reconciliation', [EarningsController::class, 'reconciliation'])->name('reconciliation');
        Route::get('/reconciliation/export', [EarningsController::class, 'exportReconciliation'])->name('reconciliation.export');
        Route::get('/{wallet}', [EarningsController::class, 'showWallet'])->name('show');
        Route::post('/{wallet}/adjustments', [EarningsController::class, 'storeAdjustment'])->name('adjustments.store');
    });

    // System
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/system-health', [SettingsController::class, 'systemHealth'])->name('system_health');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('configs');
        Route::post('/preset-configs', [SettingsController::class, 'savePresetConfigs'])->name('preset_configs.save');
        Route::post('/configs', [SettingsController::class, 'storeConfig'])->name('configs.store');
        Route::patch('/configs/{config}', [SettingsController::class, 'updateConfig'])->name('configs.update');
        Route::delete('/configs/{config}', [SettingsController::class, 'deleteConfig'])->name('configs.delete');
        Route::get('/jobs', [SettingsController::class, 'jobs'])->name('jobs');
        Route::delete('/jobs/{jobId}', [SettingsController::class, 'deleteQueuedJob'])->name('jobs.delete');
        Route::get('/failed-jobs', [SettingsController::class, 'failedJobs'])->name('failed_jobs');
        Route::post('/failed-jobs/{failedJobId}/retry', [SettingsController::class, 'retryFailedJob'])->name('failed_jobs.retry');
        Route::delete('/failed-jobs/{failedJobId}', [SettingsController::class, 'deleteFailedJob'])->name('failed_jobs.delete');
        Route::get('/job-batches', [SettingsController::class, 'jobBatches'])->name('job_batches');
        Route::get('/cache', [SettingsController::class, 'cacheEntries'])->name('cache');
        Route::delete('/cache/prefix', [SettingsController::class, 'clearCachePrefix'])->name('cache.clear_prefix');
        Route::delete('/cache/expired', [SettingsController::class, 'clearExpiredCacheEntries'])->name('cache.clear_expired');
        Route::get('/cache-locks', [SettingsController::class, 'cacheLocks'])->name('cache_locks');
        Route::delete('/cache-locks/{cacheKey}', [SettingsController::class, 'deleteCacheLock'])->name('cache_locks.delete');
        Route::get('/migrations', [SettingsController::class, 'migrations'])->name('migrations');
    });
});
