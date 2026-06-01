<?php

namespace App\Console\Commands;

use App\Models\SmtpServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckSmtpHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smtp:check-health {--account_id=} {--all : Check all SMTPs, including currently inactive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks SMTP health and auto-deactivates dead SMTP servers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accountId = $this->option('account_id');
        $includeAll = (bool) $this->option('all');

        $query = SmtpServer::query();
        if ($accountId !== null && $accountId !== '') {
            $query->where('account_id', (int) $accountId);
        }

        if (! $includeAll) {
            $query->where('is_active', true);
        }

        $servers = $query->orderBy('account_id')->orderBy('id')->get();

        if ($servers->isEmpty()) {
            $this->info('No SMTP servers found for health check.');
            return self::SUCCESS;
        }

        $checked = 0;
        $deactivated = 0;
        $failed = 0;

        foreach ($servers as $smtp) {
            $checked++;

            try {
                $this->applySmtpConfig($smtp);

                Mail::raw('SMTP health check probe.', function ($message) use ($smtp) {
                    $message->to($smtp->from_email)
                        ->subject('SMTP Health Check')
                        ->from($smtp->from_email, $smtp->from_name);
                });

                if (! $smtp->is_active) {
                    $smtp->update(['is_active' => true]);
                }
            } catch (\Throwable $e) {
                $failed++;

                if ($smtp->is_active) {
                    $smtp->update(['is_active' => false]);
                    $deactivated++;
                }

                Log::warning('SMTP health check failed', [
                    'smtp_id' => $smtp->id,
                    'account_id' => $smtp->account_id,
                    'error_type' => class_basename($e),
                    'error' => $e->getMessage(),
                    'marked_inactive' => true,
                ]);

                $this->warn("Failed SMTP [{$smtp->id}] {$smtp->name}: {$e->getMessage()}");
            }
        }

        $this->info("SMTP health check complete. Checked: {$checked}, Failed: {$failed}, Deactivated: {$deactivated}");

        return self::SUCCESS;
    }

    private function applySmtpConfig(SmtpServer $smtp): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp->host,
            'mail.mailers.smtp.port' => $smtp->port,
            'mail.mailers.smtp.username' => $smtp->username,
            'mail.mailers.smtp.password' => $smtp->password,
            'mail.mailers.smtp.encryption' => $smtp->encryption === 'none' ? null : $smtp->encryption,
            'mail.mailers.smtp.timeout' => 8,
            'mail.from.address' => $smtp->from_email,
            'mail.from.name' => $smtp->from_name,
        ]);
    }
}
