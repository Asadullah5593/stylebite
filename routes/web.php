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
use App\Http\Controllers\Admin\RoleController;
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
        return auth()->check() && auth()->user()->canAccessAdminPanel()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.login');
    })->name('home');

    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
    // Main Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('permission:dashboard.view');

    // Search
    Route::get('/search', [SearchController::class, 'index'])->name('search')->middleware('permission:search.view');

    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
        Route::put('/settings', [AccountController::class, 'update'])->name('update');
    });

    // Users Module
     Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('all_users')->middleware('permission:users.view');
        Route::get('/create', [UserController::class, 'create'])->name('create')->middleware('permission:users.create');
        Route::post('/', [UserController::class, 'store'])->name('store')->middleware('permission:users.create');
        Route::patch('/bulk-lifecycle', [UserController::class, 'bulkLifecycle'])->name('bulk_lifecycle')->middleware('permission:users.moderate');
        Route::get('/profiles', [UserController::class, 'profiles'])->name('profiles')->middleware('permission:users.view');
        Route::get('/settings', [UserController::class, 'settings'])->name('settings')->middleware('permission:users.view');
        Route::get('/auth-providers', [UserController::class, 'authProviders'])->name('auth_providers')->middleware('permission:users.view');
        Route::get('/sessions', [UserController::class, 'sessions'])->name('sessions')->middleware('permission:users.view');
        Route::get('/devices', [UserController::class, 'devices'])->name('devices')->middleware('permission:users.view');
        Route::get('/password-resets', [UserController::class, 'passwordResets'])->name('password_resets')->middleware('permission:users.view');
        Route::get('/{user}', [UserController::class, 'show'])->withTrashed()->name('show')->middleware('permission:users.view');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->withTrashed()->name('edit')->middleware('permission:users.update');
        Route::put('/{user}', [UserController::class, 'update'])->withTrashed()->name('update')->middleware('permission:users.update');
        Route::patch('/{user}/suspend', [UserController::class, 'suspend'])->name('suspend')->middleware('permission:users.moderate');
        Route::patch('/{user}/status', [UserController::class, 'changeStatus'])->withTrashed()->name('status')->middleware('permission:users.moderate');
        Route::patch('/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('restore')->middleware('permission:users.update');
        Route::patch('/{user}/badges', [UserController::class, 'updateBadge'])->withTrashed()->name('badges.update')->middleware('permission:users.update');
        Route::patch('/{user}/badge/verified', [UserController::class, 'toggleVerifiedBadge'])->withTrashed()->name('badge.verified')->middleware('permission:users.update');
        Route::patch('/{user}/streak/restore', [UserController::class, 'restoreStreak'])->name('streak.restore')->middleware('permission:users.update');
        Route::patch('/{user}/streak/reset', [UserController::class, 'resetStreak'])->name('streak.reset')->middleware('permission:users.update');
        Route::patch('/{user}/sessions/{session}/revoke', [UserController::class, 'revokeSession'])->withTrashed()->name('sessions.revoke')->middleware('permission:users.update');
        Route::patch('/{user}/devices/{device}/toggle', [UserController::class, 'toggleDevice'])->withTrashed()->name('devices.toggle')->middleware('permission:users.update');
        Route::patch('/{user}/password-resets/{passwordReset}/expire', [UserController::class, 'expirePasswordReset'])->withTrashed()->name('password_resets.expire')->middleware('permission:users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->withTrashed()->name('destroy')->middleware('permission:users.delete');
    });

    // Roles & Permissions
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index')->middleware('permission:roles.view');
        Route::get('/create', [RoleController::class, 'create'])->name('create')->middleware('permission:roles.create');
        Route::post('/', [RoleController::class, 'store'])->name('store')->middleware('permission:roles.create');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit')->middleware('permission:roles.update');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update')->middleware('permission:roles.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy')->middleware('permission:roles.delete');
    });


    // Social Module
     Route::prefix('social')->name('social.')->group(function () {
        Route::get('/', [SocialController::class, 'follows'])->name('follows')->middleware('permission:social.view');
        Route::delete('/follows/{follow}', [SocialController::class, 'deleteFollow'])->name('follows.delete')->middleware('permission:social.manage');
        Route::get('/blocks', [SocialController::class, 'blocks'])->name('blocks')->middleware('permission:social.view');
        Route::delete('/blocks/{block}', [SocialController::class, 'deleteBlock'])->name('blocks.delete')->middleware('permission:social.manage');
    });

    // Content Module
    Route::prefix('comments')->name('comments.')->group(function () {
        Route::get('/', [CommentController::class, 'index'])->name('comments')->middleware('permission:comments.view');
        Route::get('/replies', [CommentController::class, 'replies'])->name('replies')->middleware('permission:comments.view');
        Route::patch('/{comment}', [CommentController::class, 'updateComment'])->name('update')->middleware('permission:comments.update');
        Route::patch('/replies/{reply}', [CommentController::class, 'updateReply'])->name('replies.update')->middleware('permission:comments.update');
    });

    Route::prefix('engagement')->name('engagement.')->middleware('permission:engagement.view')->group(function () {
        Route::get('/', [EngagementController::class, 'postLikes'])->name('post_likes');
        Route::get('/comment-likes', [EngagementController::class, 'commentLikes'])->name('comment_likes');
        Route::get('/reply-likes', [EngagementController::class, 'replyLikes'])->name('reply_likes');
        Route::get('/shares', [EngagementController::class, 'shares'])->name('shares');
        Route::get('/saved', [EngagementController::class, 'saved'])->name('saved');
        Route::get('/views', [EngagementController::class, 'views'])->name('views');
    });

    Route::prefix('media')->name('media.')->middleware('permission:media.view')->group(function () {
        Route::get('/', [MediaController::class, 'uploads'])->name('uploads');
        Route::get('/tags', [MediaController::class, 'tags'])->name('tags');
    });

     Route::prefix('memories')->name('memories.')->group(function () {
        Route::get('/', [MemoryController::class, 'index'])->name('memories')->middleware('permission:memories.view');
        Route::get('/memory-media', [MemoryController::class, 'media'])->name('memory-media')->middleware('permission:memories.view');
        Route::get('/memory-comments', [MemoryController::class, 'comments'])->name('memory-comments')->middleware('permission:memories.view');
        Route::patch('/memory-comments/{memoryComment}', [MemoryController::class, 'updateComment'])->name('memory-comments.update')->middleware('permission:memories.update');
        Route::get('/saved-memories', [MemoryController::class, 'saved'])->name('saved_memories')->middleware('permission:memories.view');
    });

    // Post Module
     Route::prefix('posts')->name('posts.')->group(function () {
        Route::get('/', [PostController::class, 'index'])->name('all_posts')->middleware('permission:posts.view');
        Route::get('/post-media', [PostController::class, 'media'])->name('post_media')->middleware('permission:posts.view');
        Route::get('/post-ratings', [PostController::class, 'ratings'])->name('post_ratings')->middleware('permission:posts.view');
        Route::get('/post-tags', [PostController::class, 'tags'])->name('post_tags')->middleware('permission:posts.view');
        Route::get('/{post}', [PostController::class, 'show'])->name('show')->middleware('permission:posts.view');
        Route::get('/{post}/edit', [PostController::class, 'edit'])->name('edit')->middleware('permission:posts.update');
        Route::put('/{post}', [PostController::class, 'update'])->name('update')->middleware('permission:posts.update');
        Route::patch('/{post}/moderate', [PostController::class, 'moderate'])->name('moderate')->middleware('permission:posts.moderate');
    });

    // Communication Module
     Route::prefix('messaging')->name('messaging.')->group(function () {
        Route::get('/', [MessagingController::class, 'conversations'])->name('conversations')->middleware('permission:messaging.view');
        Route::patch('/{conversation}', [MessagingController::class, 'updateConversation'])->name('conversations.update')->middleware('permission:messaging.update');
        Route::get('/{conversation}/export', [MessagingController::class, 'exportConversation'])->name('conversations.export')->middleware('permission:messaging.view');
        Route::get('/conversation-members', [MessagingController::class, 'members'])->name('members')->middleware('permission:messaging.view');
        Route::get('/messages', [MessagingController::class, 'messages'])->name('messages')->middleware('permission:messaging.view');
        Route::patch('/messages/{message}', [MessagingController::class, 'updateMessage'])->name('messages.update')->middleware('permission:messaging.update');
        Route::get('/attachments', [MessagingController::class, 'attachments'])->name('attachments')->middleware('permission:messaging.view');
        Route::patch('/attachments/{attachment}', [MessagingController::class, 'updateAttachment'])->name('attachments.update')->middleware('permission:messaging.update');
        Route::get('/read-receipts', [MessagingController::class, 'reads'])->name('reads')->middleware('permission:messaging.view');
    });

     Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'notifications'])->name('notifications')->middleware('permission:notifications.view');
        Route::post('/announcements', [NotificationController::class, 'sendAnnouncement'])->name('announcements.send')->middleware('permission:notifications.send');
        Route::get('/push-logs', [NotificationController::class, 'pushLogs'])->name('push_logs')->middleware('permission:notifications.view');
        Route::post('/push-logs/{pushLog}/retry', [NotificationController::class, 'retryPushLog'])->name('push_logs.retry')->middleware('permission:notifications.send');
        Route::get('/saved-searches', [NotificationController::class, 'savedSearches'])->name('saved_searches')->middleware('permission:notifications.view');
    });

    // Moderation and Activity
     Route::prefix('moderation')->name('moderation.')->group(function () {
        Route::get('/', [ModerationController::class, 'reports'])->name('reports')->middleware('permission:moderation.view');
        Route::get('/actions', [ModerationController::class, 'actions'])->name('actions')->middleware('permission:moderation.view');
        Route::patch('/reports/{report}/assign', [ModerationController::class, 'assign'])->name('reports.assign')->middleware('permission:moderation.manage');
        Route::patch('/reports/{report}', [ModerationController::class, 'updateReport'])->name('reports.update')->middleware('permission:moderation.manage');
        Route::patch('/reports/{report}/target', [ModerationController::class, 'updateTarget'])->name('reports.target.update')->middleware('permission:moderation.manage');
        Route::patch('/actions/{moderationAction}/expiry', [ModerationController::class, 'updateActionExpiry'])->name('actions.expiry')->middleware('permission:moderation.manage');
    });

     Route::prefix('activity')->name('activity.')->middleware('permission:activity.view')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('activity_logs');
    });

    // Monetization Module
    Route::prefix('contests')->name('contests.')->group(function () {
        Route::get('/', [ContestController::class, 'contests'])->name('contests')->middleware('permission:contests.view');
        Route::get('/create', [ContestController::class, 'createContest'])->name('create')->middleware('permission:contests.create');
        Route::get('/{contest}/edit', [ContestController::class, 'editContest'])->name('edit')->middleware('permission:contests.update');
        Route::post('/', [ContestController::class, 'storeContest'])->name('store')->middleware('permission:contests.create');
        Route::patch('/{contest}', [ContestController::class, 'updateContest'])->name('update')->middleware('permission:contests.update');
        Route::patch('/{contest}/workflow', [ContestController::class, 'updateContestWorkflow'])->name('workflow.update')->middleware('permission:contests.update');
        Route::post('/{contest}/recalculate', [ContestController::class, 'recalculateContest'])->name('recalculate')->middleware('permission:contests.update');
        Route::post('/{contest}/leaderboard-snapshot', [ContestController::class, 'regenerateLeaderboardSnapshot'])->name('leaderboards.regenerate')->middleware('permission:contests.update');
        Route::get('/contest-rules', [ContestController::class, 'rules'])->name('contest_rules')->middleware('permission:contests.view');
        Route::get('/participants', [ContestController::class, 'participants'])->name('participants')->middleware('permission:contests.view');
        Route::post('/invitations', [ContestController::class, 'storeInvitation'])->name('invitations.store')->middleware('permission:contests.create');
        Route::patch('/participants/{participant}', [ContestController::class, 'updateParticipant'])->name('participants.update')->middleware('permission:contests.update');
        Route::get('/invitations', [ContestController::class, 'invitations'])->name('invitations')->middleware('permission:contests.view');
        Route::patch('/invitations/{invitation}', [ContestController::class, 'updateInvitation'])->name('invitations.update')->middleware('permission:contests.update');
        Route::get('/teams', [ContestController::class, 'teams'])->name('teams')->middleware('permission:contests.view');
        Route::get('/team-members', [ContestController::class, 'teamMembers'])->name('team_members')->middleware('permission:contests.view');
        Route::get('/submissions', [ContestController::class, 'submissions'])->name('submissions')->middleware('permission:contests.view');
        Route::patch('/submissions/{submission}', [ContestController::class, 'updateSubmission'])->name('submissions.update')->middleware('permission:contests.update');
        Route::get('/votes', [ContestController::class, 'votes'])->name('votes')->middleware('permission:contests.view');
        Route::get('/leader-boards', [ContestController::class, 'leaderboards'])->name('leaderboards')->middleware('permission:contests.view');

    });

    Route::prefix('earnings')->name('earnings.')->group(function () {
        Route::get('/', [EarningsController::class, 'wallets'])->name('wallets')->middleware('permission:earnings.view');
        Route::get('/transactions', [EarningsController::class, 'transactions'])->name('transactions')->middleware('permission:earnings.view');
        Route::get('/transactions/export', [EarningsController::class, 'exportTransactions'])->name('transactions.export')->middleware('permission:earnings.view');
        Route::post('/transactions/{transaction}/reverse', [EarningsController::class, 'reverseTransaction'])->name('transactions.reverse')->middleware('permission:earnings.manage');
        Route::get('/withdrawals', [EarningsController::class, 'withdrawals'])->name('withdrawals')->middleware('permission:earnings.view');
        Route::get('/withdrawals/export', [EarningsController::class, 'exportWithdrawals'])->name('withdrawals.export')->middleware('permission:earnings.view');
        Route::patch('/withdrawals/{withdrawal}', [EarningsController::class, 'updateWithdrawal'])->name('withdrawals.update')->middleware('permission:earnings.manage');
        Route::get('/reconciliation', [EarningsController::class, 'reconciliation'])->name('reconciliation')->middleware('permission:earnings.view');
        Route::get('/reconciliation/export', [EarningsController::class, 'exportReconciliation'])->name('reconciliation.export')->middleware('permission:earnings.view');
        Route::get('/{wallet}', [EarningsController::class, 'showWallet'])->name('show')->middleware('permission:earnings.view');
        Route::post('/{wallet}/adjustments', [EarningsController::class, 'storeAdjustment'])->name('adjustments.store')->middleware('permission:earnings.manage');
    });

    // System
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings')->middleware('permission:settings.access');
    Route::get('/system-health', [SettingsController::class, 'systemHealth'])->name('system_health')->middleware('permission:system.access');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('configs')->middleware('permission:settings.access');
        Route::post('/preset-configs', [SettingsController::class, 'savePresetConfigs'])->name('preset_configs.save')->middleware('permission:settings.access');
        Route::post('/configs', [SettingsController::class, 'storeConfig'])->name('configs.store')->middleware('permission:settings.access');
        Route::patch('/configs/{config}', [SettingsController::class, 'updateConfig'])->name('configs.update')->middleware('permission:settings.access');
        Route::delete('/configs/{config}', [SettingsController::class, 'deleteConfig'])->name('configs.delete')->middleware('permission:settings.access');
        Route::get('/jobs', [SettingsController::class, 'jobs'])->name('jobs')->middleware('permission:system.access');
        Route::delete('/jobs/{jobId}', [SettingsController::class, 'deleteQueuedJob'])->name('jobs.delete')->middleware('permission:system.access');
        Route::get('/failed-jobs', [SettingsController::class, 'failedJobs'])->name('failed_jobs')->middleware('permission:system.access');
        Route::post('/failed-jobs/{failedJobId}/retry', [SettingsController::class, 'retryFailedJob'])->name('failed_jobs.retry')->middleware('permission:system.access');
        Route::delete('/failed-jobs/{failedJobId}', [SettingsController::class, 'deleteFailedJob'])->name('failed_jobs.delete')->middleware('permission:system.access');
        Route::get('/job-batches', [SettingsController::class, 'jobBatches'])->name('job_batches')->middleware('permission:system.access');
        Route::get('/cache', [SettingsController::class, 'cacheEntries'])->name('cache')->middleware('permission:system.access');
        Route::delete('/cache/prefix', [SettingsController::class, 'clearCachePrefix'])->name('cache.clear_prefix')->middleware('permission:system.access');
        Route::delete('/cache/expired', [SettingsController::class, 'clearExpiredCacheEntries'])->name('cache.clear_expired')->middleware('permission:system.access');
        Route::get('/cache-locks', [SettingsController::class, 'cacheLocks'])->name('cache_locks')->middleware('permission:system.access');
        Route::delete('/cache-locks/{cacheKey}', [SettingsController::class, 'deleteCacheLock'])->name('cache_locks.delete')->middleware('permission:system.access');
        Route::get('/migrations', [SettingsController::class, 'migrations'])->name('migrations')->middleware('permission:system.access');
    });
});
