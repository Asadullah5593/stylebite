<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Contest;
use App\Models\ContestLeaderboardSnapshot;
use App\Models\ContestInvitation;
use App\Models\ContestParticipant;
use App\Models\ContestRule;
use App\Models\ContestSubmission;
use App\Models\ContestTeam;
use App\Models\ContestTeamMember;
use App\Models\ContestVote;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContestController extends Controller
{
    public function contests(Request $request): View
    {
        $contests = Contest::query()
            ->with([
                'creator:id,username,full_name',
                'winnerUser:id,username,full_name',
                'winnerTeam:id,name',
                'participants.user:id,username,full_name',
                'teams:id,contest_id,name',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('contest_type'), fn ($query) => $query->where('contest_type', $request->string('contest_type')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.contests.ContestsPage', compact('contests'));
    }

    public function createContest(): View
    {
        return view('admin.contests.CreateContestPage');
    }

    public function editContest(Contest $contest): View
    {
        $contest->load('rules');
        $contest->rules_text = $contest->rules
            ->sortBy('sort_order')
            ->pluck('rule_text')
            ->implode("\n");

        return view('admin.contests.EditContestPage', compact('contest'));
    }

    public function storeContest(Request $request): RedirectResponse
    {
        $contest = $this->persistContest($request);

        $this->logActivity('contest_created', 'contest', $contest->id, [
            'category' => 'admin',
            'contest_type' => $contest->contest_type,
            'status' => $contest->status,
        ]);

        return redirect()->route('admin.contests.contests')->with('status', 'Contest created successfully.');
    }

    public function updateContest(Request $request, Contest $contest): RedirectResponse
    {
        $contest = $this->persistContest($request, $contest);

        $this->logActivity('contest_updated', 'contest', $contest->id, [
            'category' => $contest->category,
            'contest_type' => $contest->contest_type,
            'status' => $contest->status,
        ]);

        return redirect()->route('admin.contests.contests')->with('status', 'Contest updated successfully.');
    }

    public function invitations(Request $request): View
    {
        $invitations = ContestInvitation::query()
            ->with([
                'contest:id,title,status,contest_type',
                'sender:id,username,full_name,avatar_url',
                'receiver:id,username,full_name,avatar_url',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('request_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('sender', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('receiver', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('request_type'), fn ($query) => $query->where('request_type', $request->string('request_type')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $invitationStats = [
            'total' => ContestInvitation::count(),
            'pending' => ContestInvitation::where('status', 'pending')->count(),
            'accepted' => ContestInvitation::where('status', 'accepted')->count(),
            'expiring_soon' => ContestInvitation::whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(7)])
                ->count(),
        ];

        $contestOptions = Contest::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        $userOptions = \App\Models\User::query()
            ->orderBy('full_name')
            ->orderBy('username')
            ->get(['id', 'username', 'full_name']);

        return view('admin.contests.InvitationsPage', compact('invitations', 'contestOptions', 'userOptions', 'invitationStats'));
    }

    public function rules(Request $request): View
    {
        $rules = ContestRule::query()
            ->with('contest:id,title,slug,status')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where('rule_text', 'like', "%{$search}%")
                    ->orWhereHas('contest', fn ($query) => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%"));
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.contests.ContestRulesPage', compact('rules'));
    }

    public function participants(Request $request): View
    {
        $participants = ContestParticipant::query()
            ->with(['contest:id,title,status', 'user:id,username,full_name,email'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('participant_role', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('joined_at')
            ->paginate(10)
            ->withQueryString();

        $participantStats = [
            'total' => ContestParticipant::count(),
            'approved' => ContestParticipant::where('status', 'approved')->count(),
            'pending' => ContestParticipant::where('status', 'joined')->count(),
            'banned' => ContestParticipant::where('status', 'banned')->count(),
        ];

        return view('admin.contests.ParticipantsPage', compact('participants', 'participantStats'));
    }

    public function teams(Request $request): View
    {
        $teams = ContestTeam::query()
            ->with(['contest:id,title,status', 'members.user:id,username,full_name,avatar_url'])
            ->withCount(['members', 'submissions'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('members.user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('contest_id'), fn ($query) => $query->where('contest_id', $request->integer('contest_id')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $contestOptions = Contest::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('admin.contests.TeamsPage', compact('teams', 'contestOptions'));
    }

    public function teamMembers(Request $request): View
    {
        $teamMembers = ContestTeamMember::query()
            ->with([
                'team:id,contest_id,name,city,logo_url,rank_position',
                'team.contest:id,title,status',
                'user:id,username,full_name,avatar_url,email',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('role', 'like', "%{$search}%")
                        ->orWhereHas('team', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('team.contest', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('contest_team_id'), fn ($query) => $query->where('contest_team_id', $request->integer('contest_team_id')))
            ->latest('joined_at')
            ->paginate(10)
            ->withQueryString();

        $teamOptions = ContestTeam::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.contests.TeamMembersPage', compact('teamMembers', 'teamOptions'));
    }

    public function submissions(Request $request): View
    {
        $submissions = ContestSubmission::query()
            ->with([
                'contest:id,title,status',
                'user:id,username,full_name',
                'team:id,name',
                'post:id,caption,status',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('submission_status', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('post', fn ($query) => $query->where('caption', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('submission_status'), fn ($query) => $query->where('submission_status', $request->string('submission_status')))
            ->latest('submitted_at')
            ->paginate(10)
            ->withQueryString();

        $submissionStats = [
            'total' => ContestSubmission::count(),
            'approved' => ContestSubmission::where('submission_status', 'approved')->count(),
            'pending' => ContestSubmission::where('submission_status', 'submitted')->count(),
            'disqualified' => ContestSubmission::where('submission_status', 'disqualified')->count(),
        ];

        return view('admin.contests.SubmissionsPage', compact('submissions', 'submissionStats'));
    }

    public function votes(Request $request): View
    {
        $votes = ContestVote::query()
            ->with([
                'contest:id,title,status',
                'submission:id,contest_id,user_id,submission_status,post_id',
                'submission.user:id,username,full_name',
                'voter:id,username,full_name',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('vote_type', 'like', "%{$search}%")
                        ->orWhere('score', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('voter', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('vote_type'), fn ($query) => $query->where('vote_type', $request->string('vote_type')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.contests.VotesPage', compact('votes'));
    }

    public function leaderboards(Request $request): View
    {
        $leaderboards = ContestLeaderboardSnapshot::query()
            ->with('contest:id,title,status,winner_user_id,winner_team_id')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('period_key', 'like', "%{$search}%")
                        ->orWhere('category_key', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn ($query) => $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('contest_id'), fn ($query) => $query->where('contest_id', $request->integer('contest_id')))
            ->when($request->filled('category_key'), fn ($query) => $query->where('category_key', $request->string('category_key')))
            ->latest('generated_at')
            ->paginate(10)
            ->withQueryString();

        $contestOptions = Contest::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        $categoryOptions = ContestLeaderboardSnapshot::query()
            ->select('category_key')
            ->distinct()
            ->orderBy('category_key')
            ->pluck('category_key');

        $leaderboardStats = [
            'total' => ContestLeaderboardSnapshot::count(),
            'visible' => $leaderboards->total(),
            'contests' => ContestLeaderboardSnapshot::distinct('contest_id')->count('contest_id'),
            'latest_generated_at' => ContestLeaderboardSnapshot::max('generated_at'),
        ];

        return view('admin.contests.LeaderboardsPage', compact('leaderboards', 'contestOptions', 'categoryOptions', 'leaderboardStats'));
    }

    public function storeInvitation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contest_id' => ['required', 'integer', 'exists:contests,id'],
            'receiver_user_id' => ['required', 'integer', 'exists:users,id'],
            'request_type' => ['required', 'in:invite,join_request'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $contest = Contest::query()->findOrFail($data['contest_id']);

        $invitation = ContestInvitation::create([
            'contest_id' => $contest->id,
            'sender_user_id' => auth()->id(),
            'receiver_user_id' => $data['receiver_user_id'],
            'request_type' => $data['request_type'],
            'status' => 'pending',
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivity('contest_invitation_created', 'contest_invitation', $invitation->id, [
            'contest_id' => $contest->id,
            'sender_user_id' => auth()->id(),
            'receiver_user_id' => $invitation->receiver_user_id,
            'request_type' => $invitation->request_type,
        ]);

        return back()->with('status', 'Invitation created successfully.');
    }

    public function updateParticipant(Request $request, ContestParticipant $participant)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:joined,approved,rejected,withdrawn,banned'],
        ]);

        $originalStatus = $participant->status;
        $participant->status = $validated['status'];
        $participant->approved_at = $validated['status'] === 'approved' ? ($participant->approved_at ?? now()) : null;
        $participant->save();

        $this->logActivity('contest_participant_status_updated', 'contest_participant', $participant->id, [
            'contest_id' => $participant->contest_id,
            'user_id' => $participant->user_id,
            'old_status' => $originalStatus,
            'new_status' => $participant->status,
        ]);

        return back()->with('status', 'Participant status updated.');
    }

    public function updateInvitation(Request $request, ContestInvitation $invitation)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,accepted,rejected,cancelled'],
        ]);

        $originalStatus = $invitation->status;
        $invitation->status = $validated['status'];
        $invitation->responded_at = $validated['status'] === 'pending' ? null : now();
        $invitation->save();

        $this->logActivity('contest_invitation_status_updated', 'contest_invitation', $invitation->id, [
            'contest_id' => $invitation->contest_id,
            'sender_user_id' => $invitation->sender_user_id,
            'receiver_user_id' => $invitation->receiver_user_id,
            'old_status' => $originalStatus,
            'new_status' => $invitation->status,
        ]);

        return back()->with('status', 'Invitation status updated.');
    }

    public function updateSubmission(Request $request, ContestSubmission $submission)
    {
        $validated = $request->validate([
            'submission_status' => ['required', 'in:submitted,approved,rejected,disqualified'],
        ]);

        $originalStatus = $submission->submission_status;
        $submission->submission_status = $validated['submission_status'];
        $submission->reviewed_at = in_array($validated['submission_status'], ['approved', 'rejected', 'disqualified'], true) ? now() : null;
        $submission->save();

        $this->logActivity('contest_submission_status_updated', 'contest_submission', $submission->id, [
            'contest_id' => $submission->contest_id,
            'user_id' => $submission->user_id,
            'old_status' => $originalStatus,
            'new_status' => $submission->submission_status,
        ]);

        return back()->with('status', 'Submission status updated.');
    }

    public function updateContestWorkflow(Request $request, Contest $contest)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,active,upcoming,completed,cancelled,archived'],
            'winner_type' => ['nullable', 'in:none,user,team'],
            'winner_user_id' => ['nullable', 'integer'],
            'winner_team_id' => ['nullable', 'integer'],
        ]);

        $original = [
            'status' => $contest->status,
            'winner_user_id' => $contest->winner_user_id,
            'winner_team_id' => $contest->winner_team_id,
        ];

        $contest->status = $validated['status'];
        $winnerType = $validated['winner_type'] ?? 'none';

        if ($winnerType === 'user') {
            $contest->winner_user_id = $validated['winner_user_id'] ?: null;
            $contest->winner_team_id = null;
        } elseif ($winnerType === 'team') {
            $contest->winner_team_id = $validated['winner_team_id'] ?: null;
            $contest->winner_user_id = null;
        } else {
            $contest->winner_user_id = null;
            $contest->winner_team_id = null;
        }

        if ($contest->status === 'completed' && $contest->result_at === null) {
            $contest->result_at = now();
        }

        $contest->save();

        $this->logActivity('contest_workflow_updated', 'contest', $contest->id, [
            'old' => $original,
            'new' => [
                'status' => $contest->status,
                'winner_user_id' => $contest->winner_user_id,
                'winner_team_id' => $contest->winner_team_id,
            ],
        ]);

        return back()->with('status', 'Contest workflow updated.');
    }

    public function recalculateContest(Contest $contest)
    {
        $contest->load([
            'participants',
            'teams',
            'submissions.votes',
        ]);

        $approvedSubmissions = $contest->submissions
            ->where('submission_status', 'approved')
            ->values();

        $rankedSubmissions = $approvedSubmissions
            ->map(function (ContestSubmission $submission) {
                $communityScore = round((float) $submission->votes->avg('score'), 2);
                $juryScore = (float) ($submission->jury_score ?? 0);
                $finalScore = round($juryScore + $communityScore, 2);

                $submission->community_score = $communityScore;
                $submission->final_score = $finalScore;

                return $submission;
            })
            ->sortByDesc(fn (ContestSubmission $submission) => $submission->final_score)
            ->values();

        foreach ($rankedSubmissions as $index => $submission) {
            $submission->rank_position = $index + 1;
            $submission->save();
        }

        $contest->submissions
            ->where('submission_status', '!=', 'approved')
            ->each(function (ContestSubmission $submission) {
                $submission->rank_position = null;
                $submission->final_score = $submission->submission_status === 'disqualified' ? null : $submission->final_score;
                $submission->save();
            });

        $participantScores = $rankedSubmissions
            ->groupBy('user_id')
            ->map(fn ($items) => round((float) $items->sum('final_score'), 2));

        $rankedParticipants = $contest->participants
            ->map(function (ContestParticipant $participant) use ($participantScores) {
                $participant->total_score = $participantScores[$participant->user_id] ?? null;

                return $participant;
            })
            ->sortByDesc(fn (ContestParticipant $participant) => $participant->total_score ?? -1)
            ->values();

        foreach ($rankedParticipants as $index => $participant) {
            $participant->rank_position = $participant->total_score !== null ? $index + 1 : null;
            $participant->save();
        }

        $teamScores = $rankedSubmissions
            ->filter(fn (ContestSubmission $submission) => $submission->contest_team_id !== null)
            ->groupBy('contest_team_id')
            ->map(fn ($items) => round((float) $items->sum('final_score'), 2));

        $rankedTeams = $contest->teams
            ->map(function (ContestTeam $team) use ($teamScores) {
                $team->score = $teamScores[$team->id] ?? null;

                return $team;
            })
            ->sortByDesc(fn (ContestTeam $team) => $team->score ?? -1)
            ->values();

        foreach ($rankedTeams as $index => $team) {
            $team->rank_position = $team->score !== null ? $index + 1 : null;
            $team->save();
        }

        $contest->participant_count = $contest->participants->count();
        $contest->submission_count = $contest->submissions->count();
        $contest->total_vote_count = $contest->submissions->sum(fn (ContestSubmission $submission) => $submission->votes->count());
        $contest->save();

        $this->logActivity('contest_scores_recalculated', 'contest', $contest->id, [
            'approved_submission_count' => $approvedSubmissions->count(),
            'participant_count' => $contest->participant_count,
            'team_count' => $contest->teams->count(),
            'ranking_rule' => 'submission final score = jury score + average community vote; participant/team totals are sums of approved submission final scores',
        ]);

        return back()->with('status', 'Contest scores and ranks recalculated.');
    }

    public function regenerateLeaderboardSnapshot(Contest $contest): RedirectResponse
    {
        $contest->load([
            'creator:id,username,full_name',
            'winnerUser:id,username,full_name',
            'winnerTeam:id,name',
            'participants.user:id,username,full_name',
            'teams.members.user:id,username,full_name',
            'submissions.user:id,username,full_name',
            'submissions.team:id,name',
            'submissions.votes:voter_user_id,submission_id,score',
        ]);

        $payload = [
            'contest' => [
                'id' => $contest->id,
                'title' => $contest->title,
                'status' => $contest->status,
                'category' => $contest->category,
                'contest_type' => $contest->contest_type,
                'winner_user' => $contest->winnerUser?->only(['id', 'username', 'full_name']),
                'winner_team' => $contest->winnerTeam?->only(['id', 'name']),
            ],
            'summary' => [
                'participant_count' => $contest->participants->count(),
                'submission_count' => $contest->submissions->count(),
                'approved_submission_count' => $contest->submissions->where('submission_status', 'approved')->count(),
                'team_count' => $contest->teams->count(),
                'vote_count' => $contest->submissions->sum(fn (ContestSubmission $submission) => $submission->votes->count()),
            ],
            'participants' => $contest->participants
                ->sortByDesc(fn (ContestParticipant $participant) => $participant->total_score ?? -1)
                ->values()
                ->map(fn (ContestParticipant $participant) => [
                    'id' => $participant->id,
                    'user_id' => $participant->user_id,
                    'name' => $participant->user?->full_name ?: $participant->user?->username,
                    'status' => $participant->status,
                    'score' => $participant->total_score,
                    'rank_position' => $participant->rank_position,
                ])
                ->all(),
            'teams' => $contest->teams
                ->sortByDesc(fn (ContestTeam $team) => $team->score ?? -1)
                ->values()
                ->map(fn (ContestTeam $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'score' => $team->score,
                    'rank_position' => $team->rank_position,
                    'members' => $team->members->map(fn ($member) => [
                        'user_id' => $member->user_id,
                        'name' => $member->user?->full_name ?: $member->user?->username,
                        'role' => $member->role,
                    ])->all(),
                ])
                ->all(),
            'submissions' => $contest->submissions
                ->sortByDesc(fn (ContestSubmission $submission) => $submission->final_score ?? -1)
                ->values()
                ->map(fn (ContestSubmission $submission) => [
                    'id' => $submission->id,
                    'user_id' => $submission->user_id,
                    'team_id' => $submission->contest_team_id,
                    'status' => $submission->submission_status,
                    'final_score' => $submission->final_score,
                    'rank_position' => $submission->rank_position,
                    'vote_count' => $submission->votes->count(),
                ])
                ->all(),
        ];

        $snapshot = ContestLeaderboardSnapshot::create([
            'contest_id' => $contest->id,
            'period_key' => 'manual-'.now()->format('Ymd-His'),
            'category_key' => $contest->category,
            'payload_json' => $payload,
            'generated_at' => now(),
        ]);

        $this->logActivity('contest_leaderboard_snapshot_created', 'contest_leaderboard_snapshot', $snapshot->id, [
            'contest_id' => $contest->id,
            'period_key' => $snapshot->period_key,
            'category_key' => $snapshot->category_key,
        ]);

        return back()->with('status', 'Leaderboard snapshot regenerated successfully.');
    }

    public static function tabCounts(): array
    {
        return [
            'contests' => Contest::count(),
            'contest_rules' => ContestRule::count(),
            'participants' => ContestParticipant::count(),
            'invitations' => ContestInvitation::count(),
            'teams' => ContestTeam::count(),
            'team_members' => ContestTeamMember::count(),
            'submissions' => ContestSubmission::count(),
            'votes' => ContestVote::count(),
            'leaderboards' => ContestLeaderboardSnapshot::count(),
        ];
    }

    private function persistContest(Request $request, ?Contest $contest = null): Contest
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,upcoming,completed,cancelled,archived'],
            'visibility' => ['required', 'in:public,private,followers_only'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'entry_fee' => ['nullable', 'numeric', 'min:0'],
            'prize_pool' => ['nullable', 'numeric', 'min:0'],
            'voting_type' => ['required', 'in:community,jury,hybrid'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'banner_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'rules_text' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $contest, $request) {
            $contest ??= Contest::create([
                'slug' => $this->uniqueContestSlug($validated['title']),
                'creator_user_id' => auth()->id(),
                'title' => $validated['title'],
                'category' => 'admin',
                'contest_type' => 'city',
            ]);

            $contest->forceFill([
                'title' => $validated['title'],
                'slug' => $contest->slug ?: $this->uniqueContestSlug($validated['title']),
                'subtitle' => $validated['subtitle'] ?? null,
                'description' => $validated['description'] ?? null,
                'category' => 'admin',
                'contest_type' => 'city',
                'status' => $validated['status'],
                'visibility' => $validated['visibility'],
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'] ?? null,
                'max_participants' => $validated['max_participants'] ?? null,
                'entry_fee' => $validated['entry_fee'] ?? 0,
                'prize_pool' => $validated['prize_pool'] ?? 0,
                'voting_type' => $validated['voting_type'],
                'start_at' => isset($validated['start_at']) ? Carbon::parse($validated['start_at']) : null,
                'end_at' => isset($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            ])->save();

            $coverImageUrl = $contest->cover_image_url;
            $bannerImageUrl = $contest->banner_image_url;

            if ($request->hasFile('cover_image')) {
                $uploadedFile = stylebite_upload_file($request->file('cover_image'), 'contests/'.auth()->id());
                $coverImageUrl = $uploadedFile['file_url'];
            }

            if ($request->hasFile('banner_image')) {
                $uploadedFile = stylebite_upload_file($request->file('banner_image'), 'contests/'.auth()->id());
                $bannerImageUrl = $uploadedFile['file_url'];
            }

            $contest->forceFill([
                'cover_image_url' => $coverImageUrl,
                'banner_image_url' => $bannerImageUrl,
            ])->save();

            ContestRule::query()->where('contest_id', $contest->id)->delete();

            if (! empty($validated['rules_text'])) {
                $rules = collect(preg_split('/\r\n|\r|\n/', (string) $validated['rules_text']))
                    ->map(fn (string $rule) => trim($rule))
                    ->filter()
                    ->values();

                foreach ($rules as $index => $ruleText) {
                    ContestRule::create([
                        'contest_id' => $contest->id,
                        'rule_text' => $ruleText,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            return $contest->fresh();
        });
    }

    private function uniqueContestSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? Str::limit($base, 100, '') : 'contest';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Contest::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function logActivity(string $eventName, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
