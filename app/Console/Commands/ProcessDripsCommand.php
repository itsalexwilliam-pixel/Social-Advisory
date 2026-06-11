<?php

namespace App\Console\Commands;

use App\Mail\DripMail;
use App\Models\DripEnrollment;
use App\Models\EmailQueue;
use App\Models\SmtpServer;
use App\Models\Unsubscribe;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ProcessDripsCommand extends Command
{
    protected $signature = 'queue:process-drips';
    protected $description = 'Process due drip campaign enrollments and send the next step email';

    public function handle()
    {
        $due = DripEnrollment::with(['dripCampaign.steps', 'contact'])
            ->where('status', 'active')
            ->where('next_send_at', '<=', now())
            ->whereHas('dripCampaign', fn($q) => $q->where('status', 'active'))
            ->get();

        if ($due->isEmpty()) {
            $this->info('No drip enrollments due.');
            return self::SUCCESS;
        }

        $this->info("Processing {$due->count()} due drip enrollment(s)...");

        foreach ($due as $enrollment) {
            $this->processEnrollment($enrollment);
        }

        $this->info('Drip processing complete.');
        return self::SUCCESS;
    }

    private function processEnrollment(DripEnrollment $enrollment): void
    {
        $contact = $enrollment->contact;
        $drip    = $enrollment->dripCampaign;

        if (!$contact || !$drip) {
            $enrollment->update(['status' => 'completed']);
            return;
        }

        // Skip unsubscribed / bounced / suppressed
        $isUnsubscribed = Unsubscribe::whereRaw('LOWER(email) = ?', [strtolower($contact->email)])->exists();
        $isBounced      = $contact->is_bounced ?? false;
        $isSuppressed   = \App\Models\SuppressionEntry::where('account_id', $drip->account_id)
            ->whereRaw('LOWER(email) = ?', [strtolower($contact->email)])
            ->exists();

        if ($isUnsubscribed || $isBounced || $isSuppressed) {
            $enrollment->update(['status' => 'unsubscribed']);
            $this->warn("Skipped {$contact->email} (unsubscribed/bounced/suppressed)");
            return;
        }

        // Get the step to send
        $steps     = $drip->steps->sortBy('position')->values();
        $stepIndex = $enrollment->current_step - 1; // 0-based index
        $step      = $steps->get($stepIndex);

        if (!$step) {
            $enrollment->update(['status' => 'completed', 'next_send_at' => null]);
            $this->line("Enrollment #{$enrollment->id} completed (all steps sent).");
            return;
        }

        $accountId = (int) $drip->account_id;
        $today     = Carbon::today()->toDateString();

        // Pick an active SMTP server, respecting daily limits and trying fallbacks.
        // Bug fix: loop through all active servers instead of stopping at the first
        // one that has hit its daily limit; mirrors the campaign worker pattern.
        $smtpServers = SmtpServer::forAccount($accountId)
            ->active()
            ->orderBy('priority')
            ->orderBy('last_used_at')
            ->orderBy('id')
            ->get();

        $smtp = null;
        foreach ($smtpServers as $candidate) {
            if (!is_null($candidate->daily_limit)) {
                // Bug fix: include account_id in the query so the unique index
                // (smtp_server_id, account_id, usage_date) is used precisely.
                $sentToday = \App\Models\SmtpServerUsage::where('smtp_server_id', $candidate->id)
                    ->where('account_id', $accountId)
                    ->where('usage_date', $today)
                    ->value('sent_count') ?? 0;

                if ($sentToday >= (int) $candidate->daily_limit) {
                    $this->warn("SMTP #{$candidate->id} daily limit reached — trying next server.");
                    continue;
                }
            }
            $smtp = $candidate;
            break;
        }

        if (!$smtp) {
            $this->warn("No available SMTP server for account #{$accountId} (all daily limits reached) — skipping enrollment #{$enrollment->id}.");
            return;
        }

        // Create an email_queue row for tracking
        $queueRow = EmailQueue::create([
            'account_id'    => $accountId,
            'contact_id'    => $contact->id,
            'email'         => $contact->email,
            'type'          => 'drip',
            'subject'       => $step->subject,
            'body'          => $step->body,
            'body_snapshot' => $step->body,
            'status'        => 'pending',
            'attempts'      => 0,
        ]);

        // Configure mailer dynamically for this SMTP server
        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.transport'  => 'smtp',
            'mail.mailers.smtp.host'       => $smtp->host,
            'mail.mailers.smtp.port'       => $smtp->port,
            'mail.mailers.smtp.encryption' => $smtp->encryption === 'none' ? null : $smtp->encryption,
            'mail.mailers.smtp.username'   => $smtp->username,
            'mail.mailers.smtp.password'   => $smtp->password,
            'mail.mailers.smtp.timeout'    => 8,
            'mail.from.address'            => $smtp->from_email,
            'mail.from.name'               => $smtp->from_name,
        ]);

        // Step 1: attempt the send — isolated so usage tracking can never
        // corrupt the send result.
        // Bug fix: forgetMailers() clears the Mail facade's cached SMTP transport
        // so the config changes above actually take effect for this send.
        $sendSucceeded = false;
        $sendError     = null;

        try {
            Mail::forgetMailers();
            Mail::to($contact->email)->send(new DripMail($step, $contact, $queueRow->id));
            $sendSucceeded = true;
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            $this->error("Failed to send drip step to {$contact->email}: {$sendError}");
        }

        // Step 2: record usage atomically — in its own try/catch so a DB hiccup
        // here never flips a successfully-sent row back to 'failed'.
        // Bug fix: use upsert (atomic) instead of firstOrCreate + increment which
        // had a race condition when two workers ran simultaneously.
        $smtp->update(['last_used_at' => now()]);

        try {
            $now = now();
            DB::table('smtp_server_usages')->upsert(
                [[
                    'smtp_server_id' => $smtp->id,
                    'account_id'     => $accountId,
                    'usage_date'     => $today,
                    'sent_count'     => $sendSucceeded ? 1 : 0,
                    'fail_count'     => $sendSucceeded ? 0 : 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]],
                ['smtp_server_id', 'account_id', 'usage_date'],
                [
                    'sent_count' => DB::raw('sent_count + ' . ($sendSucceeded ? 1 : 0)),
                    'fail_count' => DB::raw('fail_count + ' . ($sendSucceeded ? 0 : 1)),
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable $usageEx) {
            $this->warn("Usage tracking failed for SMTP #{$smtp->id}: " . $usageEx->getMessage());
        }

        // Step 3: finalise queue row and advance enrollment based on send result
        if ($sendSucceeded) {
            $queueRow->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);

            $nextStep = $steps->get($stepIndex + 1);

            if ($nextStep) {
                $enrollment->update([
                    'current_step' => $enrollment->current_step + 1,
                    'next_send_at' => now()->addDays($nextStep->delay_days),
                ]);
                $this->info("Sent step {$enrollment->current_step} to {$contact->email} — next in {$nextStep->delay_days} day(s).");
            } else {
                $enrollment->update(['status' => 'completed', 'next_send_at' => null]);
                $this->info("Sent final step to {$contact->email} — enrollment completed.");
            }
        } else {
            $queueRow->update([
                'status'     => 'failed',
                'attempts'   => 1,
                'last_error' => $sendError,
            ]);
        }
    }
}
