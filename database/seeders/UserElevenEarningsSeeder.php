<?php

namespace Database\Seeders;

use App\Models\EarningTransaction;
use App\Models\EarningsWallet;
use App\Models\Profile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Seeder;

class UserElevenEarningsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->findOrFail(11);

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'contest_wins' => 12,
            ]
        );

        $wallet = EarningsWallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'currency_code' => 'PKR',
                'available_balance' => 42850,
                'pending_balance' => 12000,
                'lifetime_earned' => 97500,
                'lifetime_withdrawn' => 42650,
                'updated_balance_at' => now()->subMinutes(30),
            ]
        );

        $transactions = [
            [
                'id' => 31001,
                'transaction_type' => 'credit',
                'source_type' => 'contest_reward',
                'amount' => 15000,
                'status' => 'completed',
                'note' => 'Contest reward',
                'metadata_json' => [
                    'title' => 'Summer Streetwear',
                    'reason' => 'Contest Win',
                ],
                'processed_at' => now()->subDays(2),
            ],
            [
                'id' => 31002,
                'transaction_type' => 'credit',
                'source_type' => 'contest_reward',
                'amount' => 4500,
                'status' => 'completed',
                'note' => 'Top 5 contest finish',
                'metadata_json' => [
                    'title' => 'Minimalist Chic',
                    'reason' => 'Top 5 Finish',
                ],
                'processed_at' => now()->subDays(6),
            ],
            [
                'id' => 31003,
                'transaction_type' => 'credit',
                'source_type' => 'engagement_bonus',
                'amount' => 2000,
                'status' => 'completed',
                'note' => 'Engagement reward',
                'metadata_json' => [
                    'title' => 'Creator Bonus',
                    'reason' => 'Engagement Reward',
                ],
                'processed_at' => now()->subDays(10),
            ],
            [
                'id' => 31004,
                'transaction_type' => 'credit',
                'source_type' => 'engagement_bonus',
                'amount' => 10000,
                'status' => 'completed',
                'note' => 'Last month creator income',
                'metadata_json' => [
                    'title' => 'Creator Bonus',
                    'reason' => 'Engagement Reward',
                ],
                'processed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
            ],
            [
                'id' => 31005,
                'transaction_type' => 'credit',
                'source_type' => 'engagement_bonus',
                'amount' => 8474.58,
                'status' => 'completed',
                'note' => 'Previous month creator income',
                'metadata_json' => [
                    'title' => 'Creator Bonus',
                    'reason' => 'Engagement Reward',
                ],
                'processed_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(5),
            ],
            [
                'id' => 31006,
                'transaction_type' => 'debit',
                'source_type' => 'withdrawal',
                'source_id' => 41001,
                'amount' => 12000,
                'status' => 'pending',
                'note' => 'Withdrawal request submitted',
                'metadata_json' => [
                    'title' => 'Transfer to Bank',
                    'reason' => 'Withdrawal Request',
                    'account_ref' => '****4201',
                ],
                'processed_at' => now()->subDay(),
            ],
            [
                'id' => 31007,
                'transaction_type' => 'debit',
                'source_type' => 'withdrawal',
                'source_id' => 41002,
                'amount' => 25000,
                'status' => 'completed',
                'note' => 'Withdrawal completed',
                'metadata_json' => [
                    'title' => 'Transfer to Bank',
                    'reason' => 'Withdrawal Request',
                    'account_ref' => '****4201',
                ],
                'processed_at' => now()->subDays(12),
            ],
            [
                'id' => 31008,
                'transaction_type' => 'debit',
                'source_type' => 'withdrawal',
                'source_id' => 41003,
                'amount' => 5650,
                'status' => 'completed',
                'note' => 'Withdrawal completed',
                'metadata_json' => [
                    'title' => 'Transfer to Bank',
                    'reason' => 'Withdrawal Request',
                    'account_ref' => '****4201',
                ],
                'processed_at' => now()->subDays(20),
            ],
        ];

        foreach ($transactions as $transaction) {
            EarningTransaction::query()->updateOrCreate(
                ['id' => $transaction['id']],
                array_merge($transaction, [
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'currency_code' => 'PKR',
                ])
            );
        }

        $withdrawals = [
            [
                'id' => 41001,
                'amount' => 12000,
                'method' => 'bank_transfer',
                'account_ref' => '****4201',
                'status' => 'processing',
                'requested_at' => now()->subDay(),
                'processed_at' => null,
                'failure_reason' => null,
            ],
            [
                'id' => 41002,
                'amount' => 25000,
                'method' => 'bank_transfer',
                'account_ref' => '****4201',
                'status' => 'completed',
                'requested_at' => now()->subDays(12),
                'processed_at' => now()->subDays(11),
                'failure_reason' => null,
            ],
            [
                'id' => 41003,
                'amount' => 5650,
                'method' => 'bank_transfer',
                'account_ref' => '****4201',
                'status' => 'completed',
                'requested_at' => now()->subDays(20),
                'processed_at' => now()->subDays(19),
                'failure_reason' => null,
            ],
        ];

        foreach ($withdrawals as $withdrawal) {
            WithdrawalRequest::query()->updateOrCreate(
                ['id' => $withdrawal['id']],
                array_merge($withdrawal, [
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'currency_code' => 'PKR',
                ])
            );
        }
    }
}
