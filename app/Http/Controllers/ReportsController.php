<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\EmailQueue;
use App\Models\SmtpServer;
use App\Models\SmtpServerUsage;
use App\Models\Unsubscribe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportsController extends Controller
{
    // ── Shared date range resolver ─────────────────────────────────────────────
    private function resolveDateRange(Request $request, string $defaultRange = '30d'): array
    {
        $dateRange = $request->string('date_range', $defaultRange)->toString();

        switch ($dateRange) {
            case 'today':
                $from = now()->startOfDay();
                $to   = now()->endOfDay();
                break;
            case '7d':
                $from = now()->startOfDay()->subDays(6);
                $to   = now()->endOfDay();
                break;
            case '30d':
                $from = now()->startOfDay()->subDays(29);
                $to   = now()->endOfDay();
                break;
            case 'custom':
                $fromInput = $request->input('from');
                $toInput   = $request->input('to');
                $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : now()->startOfDay()->subDays(29);
                $to   = $toInput   ? Carbon::parse($toInput)->endOfDay()     : now()->endOfDay();
                if ($from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                break;
            default:
                $dateRange = '30d';
                $from = now()->startOfDay()->subDays(29);
                $to   = now()->endOfDay();
                break;
        }

        return [$dateRange, $from, $to];
    }

    // ── Campaign Report ────────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        $accountId  = (int) ($request->user()->account_id ?? 0);
        $campaignId = $request->integer('campaign_id') ?: null;

        [$dateRange, $from, $to] = $this->resolveDateRange($request, '30d');

        // Campaign IDs belonging to this account — used to scope email_queue rows
        // even when account_id on email_queue was not populated correctly (legacy rows).
        $accountCampaignIds = Campaign::where('account_id', $accountId)->pluck('id');

        // Sent — scope by campaign ownership (covers rows where account_id is 0/null)
        $sentBase = EmailQueue::query()
            ->whereIn('campaign_id', $accountCampaignIds)
            ->where('status', 'sent')
            ->whereBetween('sent_at', [$from, $to]);

        if ($campaignId) {
            $sentBase->where('campaign_id', $campaignId);
        }

        $sentCount = (clone $sentBase)->count();

        // Opens
        $openBase = EmailOpen::query()
            ->join('email_queue', 'email_queue.id', '=', 'email_opens.email_queue_id')
            ->whereIn('email_queue.campaign_id', $accountCampaignIds)
            ->whereBetween('email_opens.created_at', [$from, $to]);

        if ($campaignId) {
            $openBase->where('email_queue.campaign_id', $campaignId);
        }

        $opensCount = (clone $openBase)->distinct('email_opens.email_queue_id')->count('email_opens.email_queue_id');

        // Clicks
        $clickBase = EmailClick::query()
            ->join('email_queue', 'email_queue.id', '=', 'email_clicks.email_queue_id')
            ->whereIn('email_queue.campaign_id', $accountCampaignIds)
            ->whereBetween('email_clicks.created_at', [$from, $to]);

        if ($campaignId) {
            $clickBase->where('email_queue.campaign_id', $campaignId);
        }

        $clicksCount = (clone $clickBase)->distinct('email_clicks.email_queue_id')->count('email_clicks.email_queue_id');

        // Unsubscribes — scope by emails sent in account campaigns (no campaign_id column on unsubscribes)
        $sentEmailsSubquery = EmailQueue::whereIn('campaign_id', $accountCampaignIds)
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->select('email');

        $unsubscribesCount = Unsubscribe::whereIn('email', $sentEmailsSubquery)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $openRate  = $sentCount > 0 ? round(($opensCount  / $sentCount) * 100, 2) : 0;
        $clickRate = $sentCount > 0 ? round(($clicksCount / $sentCount) * 100, 2) : 0;

        // Chart series (day-by-day)
        $days   = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $days->push($cursor->toDateString());
            $cursor->addDay();
        }

        $sentSeriesRows = (clone $sentBase)
            ->selectRaw('DATE(sent_at) as day, COUNT(*) as total')
            ->groupBy('day')->orderBy('day')
            ->pluck('total', 'day');

        $opensSeriesRows = (clone $openBase)
            ->selectRaw('DATE(email_opens.created_at) as day, COUNT(DISTINCT email_opens.email_queue_id) as total')
            ->groupBy('day')->orderBy('day')
            ->pluck('total', 'day');

        $clicksSeriesRows = (clone $clickBase)
            ->selectRaw('DATE(email_clicks.created_at) as day, COUNT(DISTINCT email_clicks.email_queue_id) as total')
            ->groupBy('day')->orderBy('day')
            ->pluck('total', 'day');

        $chartLabels  = $days->all();
        $sentSeries   = $days->map(fn ($d) => (int) ($sentSeriesRows[$d]   ?? 0))->all();
        $opensSeries  = $days->map(fn ($d) => (int) ($opensSeriesRows[$d]  ?? 0))->all();
        $clicksSeries = $days->map(fn ($d) => (int) ($clicksSeriesRows[$d] ?? 0))->all();

        // Per-campaign breakdown table
        $campaignRows = EmailQueue::query()
            ->leftJoin('campaigns', 'campaigns.id', '=', 'email_queue.campaign_id')
            ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
            ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
            ->whereIn('email_queue.campaign_id', $accountCampaignIds)
            ->where('email_queue.status', 'sent')
            ->whereBetween('email_queue.sent_at', [$from, $to])
            ->when($campaignId, fn ($q) => $q->where('email_queue.campaign_id', $campaignId))
            ->selectRaw('
                email_queue.campaign_id,
                COALESCE(campaigns.name, "Single / Unnamed") as campaign_name,
                campaigns.status as campaign_status,
                COUNT(DISTINCT email_queue.id) as sent_count,
                COUNT(DISTINCT email_opens.email_queue_id) as open_count,
                COUNT(DISTINCT email_clicks.email_queue_id) as click_count
            ')
            ->groupBy('email_queue.campaign_id', 'campaigns.name', 'campaigns.status')
            ->orderByDesc('sent_count')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                $sent  = (int) $row->sent_count;
                $open  = (int) $row->open_count;
                $click = (int) $row->click_count;

                $row->open_rate  = $sent > 0 ? round(($open  / $sent) * 100, 2) : 0;
                $row->click_rate = $sent > 0 ? round(($click / $sent) * 100, 2) : 0;

                return $row;
            });

        // UTM tables
        $utmSourceRows = EmailClick::query()
            ->join('email_queue', 'email_queue.id', '=', 'email_clicks.email_queue_id')
            ->whereIn('email_queue.campaign_id', $accountCampaignIds)
            ->whereBetween('email_clicks.created_at', [$from, $to])
            ->when($campaignId, fn ($q) => $q->where('email_queue.campaign_id', $campaignId))
            ->selectRaw('
                COALESCE(NULLIF(email_queue.utm_source, ""), "(none)") as utm_source,
                COALESCE(NULLIF(email_queue.utm_medium, ""), "(none)") as utm_medium,
                COUNT(*) as total_clicks
            ')
            ->groupBy('utm_source', 'utm_medium')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get();

        $utmCampaignRows = EmailClick::query()
            ->join('email_queue', 'email_queue.id', '=', 'email_clicks.email_queue_id')
            ->whereIn('email_queue.campaign_id', $accountCampaignIds)
            ->whereBetween('email_clicks.created_at', [$from, $to])
            ->when($campaignId, fn ($q) => $q->where('email_queue.campaign_id', $campaignId))
            ->selectRaw('
                COALESCE(NULLIF(email_queue.utm_campaign, ""), "(none)") as utm_campaign,
                COUNT(*) as total_clicks
            ')
            ->groupBy('utm_campaign')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get();

        $campaignOptions = Campaign::query()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.index', [
            'filters' => [
                'date_range'  => $dateRange,
                'from'        => $from->toDateString(),
                'to'          => $to->toDateString(),
                'campaign_id' => $campaignId,
            ],
            'metrics' => [
                'sent'         => $sentCount,
                'opens'        => $opensCount,
                'clicks'       => $clicksCount,
                'unsubscribes' => $unsubscribesCount,
                'open_rate'    => $openRate,
                'click_rate'   => $clickRate,
            ],
            'chart' => [
                'labels' => $chartLabels,
                'sent'   => $sentSeries,
                'opens'  => $opensSeries,
                'clicks' => $clicksSeries,
            ],
            'campaignRows'    => $campaignRows,
            'utmSourceRows'   => $utmSourceRows,
            'utmCampaignRows' => $utmCampaignRows,
            'campaignOptions' => $campaignOptions,
        ]);
    }

    // ── Campaign Detail ────────────────────────────────────────────────────────
    public function campaignDetail(Request $request, int $campaignId): View
    {
        $accountId = (int) ($request->user()->account_id ?? 0);

        $campaign = Campaign::where('account_id', $accountId)->findOrFail($campaignId);

        $totalSent   = EmailQueue::where('campaign_id', $campaignId)->where('status', 'sent')->count();
        $totalFailed = EmailQueue::where('campaign_id', $campaignId)->where('status', 'failed')->count();
        $totalQueued = EmailQueue::where('campaign_id', $campaignId)->whereIn('status', ['queued', 'pending'])->count();

        $totalOpens = EmailOpen::query()
            ->join('email_queue', 'email_queue.id', '=', 'email_opens.email_queue_id')
            ->where('email_queue.campaign_id', $campaignId)
            ->distinct('email_opens.email_queue_id')
            ->count('email_opens.email_queue_id');

        $totalClicks = EmailClick::query()
            ->join('email_queue', 'email_queue.id', '=', 'email_clicks.email_queue_id')
            ->where('email_queue.campaign_id', $campaignId)
            ->distinct('email_clicks.email_queue_id')
            ->count('email_clicks.email_queue_id');

        // Count unsubscribes by matching emails that were sent in this campaign
        $campaignEmails = EmailQueue::where('campaign_id', $campaignId)->pluck('email');
        $totalUnsubs = Unsubscribe::whereIn('email', $campaignEmails)->count();

        $openRate  = $totalSent > 0 ? round(($totalOpens  / $totalSent) * 100, 2) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 2) : 0;
        $unsubRate = $totalSent > 0 ? round(($totalUnsubs / $totalSent) * 100, 2) : 0;

        // Per-recipient delivery + engagement status
        $recipients = EmailQueue::query()
            ->where('email_queue.campaign_id', $campaignId)
            ->where('email_queue.status', 'sent')
            ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
            ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
            ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
            ->leftJoin('unsubscribes', 'unsubscribes.email', '=', 'email_queue.email')
            ->selectRaw('
                email_queue.id,
                email_queue.email,
                email_queue.sent_at,
                smtp_servers.name  as smtp_name,
                smtp_servers.host  as smtp_host,
                MAX(email_opens.id)    as opened_id,
                MAX(email_clicks.id)   as clicked_id,
                MAX(unsubscribes.id)   as unsub_id
            ')
            ->groupBy(
                'email_queue.id', 'email_queue.email', 'email_queue.sent_at',
                'smtp_servers.name', 'smtp_servers.host'
            )
            ->orderByDesc('email_queue.sent_at')
            ->paginate(25)
            ->withQueryString();

        // Failed deliveries
        $failedEmails = EmailQueue::query()
            ->where('email_queue.campaign_id', $campaignId)
            ->where('email_queue.status', 'failed')
            ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
            ->selectRaw('
                email_queue.id,
                email_queue.email,
                email_queue.last_error,
                email_queue.updated_at,
                smtp_servers.name as smtp_name,
                smtp_servers.host as smtp_host
            ')
            ->orderByDesc('email_queue.updated_at')
            ->limit(100)
            ->get();

        return view('reports.campaign-detail', compact(
            'campaign',
            'totalSent', 'totalFailed', 'totalQueued',
            'totalOpens', 'totalClicks', 'totalUnsubs',
            'openRate', 'clickRate', 'unsubRate',
            'recipients', 'failedEmails'
        ));
    }

    // ── Single Email Report ────────────────────────────────────────────────────
    public function singleEmailReport(Request $request): View
    {
        $accountId = (int) ($request->user()->account_id ?? 0);

        [$dateRange, $from, $to] = $this->resolveDateRange($request, '30d');

        $emailLogs = EmailQueue::query()
            ->where('email_queue.account_id', $accountId)
            ->where('email_queue.status', 'sent')
            ->where('email_queue.type', 'single')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'), [$from, $to])
            ->select([
                'email_queue.id',
                'email_queue.created_at',
                'email_queue.sent_at',
                'email_queue.type',
                'email_queue.email',
                'email_queue.subject',
                'email_queue.from_name',
                'email_queue.from_email',
                'email_queue.status',
                'email_queue.body_snapshot',
            ])
            ->orderByDesc(\Illuminate\Support\Facades\DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'))
            ->orderByDesc('email_queue.id')
            ->paginate(20)
            ->withQueryString();

        return view('reports.single-email', [
            'filters' => [
                'date_range' => $dateRange,
                'from'       => $from->toDateString(),
                'to'         => $to->toDateString(),
            ],
            'emailLogs' => $emailLogs,
        ]);
    }

    public function warmupReport(Request $request): View
    {
        $accountId = (int) ($request->user()->account_id ?? 0);
        [$dateRange, $from, $to] = $this->resolveDateRange($request, '30d');

        $campaigns = Campaign::query()
            ->where('account_id', $accountId)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        $rows = $campaigns->map(function (Campaign $campaign) {
            $sentCount = EmailQueue::where('campaign_id', $campaign->id)->where('status', 'sent')->count();
            $pendingCount = EmailQueue::where('campaign_id', $campaign->id)->whereIn('status', ['queued', 'pending'])->count();

            return (object) [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'warmup_enabled' => (bool) $campaign->warmup_enabled,
                'warmup_day' => (int) ($campaign->warmup_day ?: 0),
                'current_warmup_cap' => $campaign->warmup_enabled ? $campaign->currentWarmupCap() : 0,
                'emails_per_minute' => (int) ($campaign->emails_per_minute ?: 0),
                'sent_count' => $sentCount,
                'pending_count' => $pendingCount,
                'created_at' => $campaign->created_at,
            ];
        });

        $metrics = [
            'campaigns' => $rows->count(),
            'warmup_enabled' => $rows->where('warmup_enabled', true)->count(),
            'total_sent' => (int) $rows->sum('sent_count'),
            'total_pending' => (int) $rows->sum('pending_count'),
        ];

        return view('reports.warmup', [
            'filters' => [
                'date_range' => $dateRange,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'metrics' => $metrics,
            'rows' => $rows,
        ]);
    }

    public function smtpReport(Request $request): View
    {
        $accountId = (int) ($request->user()->account_id ?? 0);

        [$dateRange, $from, $to] = $this->resolveDateRange($request, '30d');

        $smtpId = $request->integer('smtp_id') ?: null;
        $status = $request->string('status', 'all')->toString();
        $recipient = trim((string) $request->input('recipient', ''));

        $smtpOptions = SmtpServer::forAccount($accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $smtpBase = SmtpServer::forAccount($accountId);
        $totalSmtp = (clone $smtpBase)->count();
        $activeSmtp = (clone $smtpBase)->where('is_active', true)->count();
        $inactiveSmtp = max($totalSmtp - $activeSmtp, 0);

        $usageRows = SmtpServerUsage::query()
            ->where('account_id', $accountId)
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('smtp_server_id, SUM(sent_count) as sent_total, SUM(fail_count) as fail_total')
            ->groupBy('smtp_server_id')
            ->get()
            ->keyBy('smtp_server_id');

        $smtpHealthRows = SmtpServer::forAccount($accountId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(function (SmtpServer $smtp) use ($usageRows) {
                $usage = $usageRows->get($smtp->id);
                $sent = (int) ($usage->sent_total ?? 0);
                $fail = (int) ($usage->fail_total ?? 0);
                $healthStatus = ! $smtp->is_active
                    ? 'disabled'
                    : (($fail > $sent || ($sent === 0 && $fail > 0)) ? 'not_working' : 'working');

                return (object) [
                    'id' => $smtp->id,
                    'name' => $smtp->name,
                    'host' => $smtp->host,
                    'is_active' => (bool) $smtp->is_active,
                    'priority' => $smtp->priority,
                    'daily_limit' => $smtp->daily_limit,
                    'last_used_at' => $smtp->last_used_at,
                    'sent_total' => $sent,
                    'fail_total' => $fail,
                    'health_status' => $healthStatus,
                ];
            });

        $deadCount = $smtpHealthRows->where('health_status', 'not_working')->count();
        $disabledCount = $smtpHealthRows->where('health_status', 'disabled')->count();

        $recipientRowsQuery = EmailQueue::query()
            ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
            ->leftJoin('campaigns', 'campaigns.id', '=', 'email_queue.campaign_id')
            ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
            ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
            ->leftJoin('email_bounces', 'email_bounces.email_queue_id', '=', 'email_queue.id')
            ->where('email_queue.account_id', $accountId)
            ->whereBetween(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'), [$from, $to])
            ->when($smtpId, fn ($q) => $q->where('email_queue.smtp_server_id', $smtpId))
            ->when($recipient !== '', fn ($q) => $q->where('email_queue.email', 'like', '%' . $recipient . '%'))
            ->when(in_array($status, ['working', 'not_working', 'disabled'], true), function ($q) use ($status) {
                if ($status === 'disabled') {
                    $q->where(function ($x) {
                        $x->where('smtp_servers.is_active', false)->orWhereNull('smtp_servers.id');
                    });
                } elseif ($status === 'working') {
                    $q->where('smtp_servers.is_active', true);
                } elseif ($status === 'not_working') {
                    $q->where('email_queue.status', 'failed');
                }
            })
            ->selectRaw('
                email_queue.id,
                email_queue.email,
                email_queue.status as queue_status,
                email_queue.last_error,
                email_queue.sent_at,
                email_queue.created_at,
                smtp_servers.id as smtp_id,
                smtp_servers.name as smtp_name,
                smtp_servers.host as smtp_host,
                smtp_servers.is_active as smtp_is_active,
                campaigns.id as campaign_id,
                campaigns.name as campaign_name,
                MAX(email_opens.id) as opened_id,
                MAX(email_clicks.id) as clicked_id,
                MAX(email_bounces.id) as bounced_id
            ')
            ->groupBy(
                'email_queue.id',
                'email_queue.email',
                'email_queue.status',
                'email_queue.last_error',
                'email_queue.sent_at',
                'email_queue.created_at',
                'smtp_servers.id',
                'smtp_servers.name',
                'smtp_servers.host',
                'smtp_servers.is_active',
                'campaigns.id',
                'campaigns.name'
            )
            ->orderByDesc(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'));

        $recipientRows = $recipientRowsQuery->paginate(25)->withQueryString();

        return view('reports.smtp', [
            'filters' => [
                'date_range' => $dateRange,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'smtp_id' => $smtpId,
                'status' => $status,
                'recipient' => $recipient,
            ],
            'summary' => [
                'total_smtp' => $totalSmtp,
                'active_smtp' => $activeSmtp,
                'inactive_smtp' => $inactiveSmtp,
                'dead_smtp' => $deadCount,
                'disabled_smtp' => $disabledCount,
            ],
            'smtpOptions' => $smtpOptions,
            'smtpHealthRows' => $smtpHealthRows,
            'recipientRows' => $recipientRows,
        ]);
    }

    public function liveLogs(Request $request): View
    {
        $accountId = (int) ($request->user()->account_id ?? 0);

        // Resolve date range — "all" means no date filter (show every log ever)
        $dateRange = $request->string('date_range', 'all')->toString();
        $from = null;
        $to   = null;

        if ($dateRange !== 'all') {
            [$dateRange, $from, $to] = $this->resolveDateRange($request, 'all');
        }

        $status     = $request->string('status', 'all')->toString();
        $emailType  = $request->string('email_type', 'all')->toString();
        $campaignId = $request->integer('campaign_id') ?: null;
        $smtpId     = $request->integer('smtp_id') ?: null;
        $recipient  = trim((string) $request->input('recipient', ''));

        // All campaign IDs that belong to this account — broadens the scope
        // so emails queued before account_id was populated are still included.
        $accountCampaignIds = Campaign::where('account_id', $accountId)->pluck('id');

        $campaignOptions = Campaign::query()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $smtpOptions = SmtpServer::forAccount($accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Base query: match by account_id OR by campaign ownership
        $baseLogsQuery = EmailQueue::query()
            ->leftJoin('campaigns', 'campaigns.id', '=', 'email_queue.campaign_id')
            ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
            ->where(function ($q) use ($accountId, $accountCampaignIds) {
                $q->where('email_queue.account_id', $accountId)
                  ->orWhereIn('email_queue.campaign_id', $accountCampaignIds);
            });

        // Apply date filter only when a specific range is chosen
        if ($from && $to) {
            $baseLogsQuery->whereBetween(
                DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'),
                [$from, $to]
            );
        }

        if (in_array($status, ['queued', 'pending', 'sent', 'failed'], true)) {
            $baseLogsQuery->where('email_queue.status', $status);
        }

        if (in_array($emailType, ['campaign', 'single', 'test'], true)) {
            $baseLogsQuery->where('email_queue.type', $emailType);
        }

        if ($campaignId) {
            $baseLogsQuery->where('email_queue.campaign_id', $campaignId);
        }

        if ($smtpId) {
            $baseLogsQuery->where('email_queue.smtp_server_id', $smtpId);
        }

        if ($recipient !== '') {
            $baseLogsQuery->where('email_queue.email', 'like', '%' . $recipient . '%');
        }

        $logs = (clone $baseLogsQuery)
            ->select([
                'email_queue.id',
                'email_queue.type',
                'email_queue.email',
                'email_queue.subject',
                'email_queue.from_email',
                'email_queue.status',
                'email_queue.attempts',
                'email_queue.last_error',
                'email_queue.sent_at',
                'email_queue.created_at',
                'campaigns.name as campaign_name',
                'smtp_servers.name as smtp_name',
                'smtp_servers.host as smtp_host',
            ])
            ->orderByDesc(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'))
            ->orderByDesc('email_queue.id')
            ->paginate(50)
            ->withQueryString();

        // Summary counts — same scope, without select columns
        $summaryBase = EmailQueue::query()
            ->where(function ($q) use ($accountId, $accountCampaignIds) {
                $q->where('account_id', $accountId)
                  ->orWhereIn('campaign_id', $accountCampaignIds);
            });

        if ($from && $to) {
            $summaryBase->whereBetween(DB::raw('COALESCE(sent_at, created_at)'), [$from, $to]);
        }

        if (in_array($emailType, ['campaign', 'single', 'test'], true)) {
            $summaryBase->where('type', $emailType);
        }

        if ($campaignId) {
            $summaryBase->where('campaign_id', $campaignId);
        }

        if ($smtpId) {
            $summaryBase->where('smtp_server_id', $smtpId);
        }

        if ($recipient !== '') {
            $summaryBase->where('email', 'like', '%' . $recipient . '%');
        }

        $total    = (clone $summaryBase)->count();
        $pending  = (clone $summaryBase)->whereIn('status', ['queued', 'pending'])->count();
        $sent     = (clone $summaryBase)->where('status', 'sent')->count();
        $failed   = (clone $summaryBase)->where('status', 'failed')->count();
        $campaign = (clone $summaryBase)->where('type', 'campaign')->count();
        $single   = (clone $summaryBase)->where('type', 'single')->count();
        $test     = (clone $summaryBase)->where('type', 'test')->count();

        return view('reports.live-logs', [
            'filters' => [
                'date_range'  => $dateRange,
                'from'        => $from ? $from->toDateString() : '',
                'to'          => $to   ? $to->toDateString()   : '',
                'status'      => $status,
                'email_type'  => $emailType,
                'campaign_id' => $campaignId,
                'smtp_id'     => $smtpId,
                'recipient'   => $recipient,
            ],
            'summary' => [
                'total'    => $total,
                'queued'   => $pending,
                'sent'     => $sent,
                'failed'   => $failed,
                'campaign' => $campaign,
                'single'   => $single,
                'test'     => $test,
            ],
            'campaignOptions' => $campaignOptions,
            'smtpOptions'     => $smtpOptions,
            'logs'            => $logs,
        ]);
    }

    public function export(Request $request)
    {
        $accountId = (int) ($request->user()->account_id ?? 0);
        $type = $request->string('type', 'campaign')->toString();

        // Support "all" date range — no date filter applied
        $dateRangeInput = $request->string('date_range', '30d')->toString();
        $from = null;
        $to   = null;
        if ($dateRangeInput !== 'all') {
            [, $from, $to] = $this->resolveDateRange($request, '30d');
        }

        $campaignId    = $request->integer('campaign_id') ?: null;
        $smtpId        = $request->integer('smtp_id') ?: null;
        $status        = $request->string('status', 'all')->toString();
        $recipient     = trim((string) $request->input('recipient', ''));
        $emailTypeExport = $request->string('email_type', 'all')->toString();

        if (!in_array($type, ['campaign', 'single-email', 'warmup', 'smtp', 'live-logs', 'campaign-detail'], true)) {
            $type = 'campaign';
        }

        $filename = 'report_'.$type.'_'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($type, $accountId, $from, $to, $campaignId, $smtpId, $status, $recipient, $emailTypeExport) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($type === 'single-email') {
                fputcsv($handle, ['Sent At', 'To', 'From Name', 'From Email', 'Subject', 'Status', 'Opened', 'Clicked']);

                $rows = EmailQueue::query()
                    ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
                    ->where('email_queue.account_id', $accountId)
                    ->where('email_queue.type', 'single')
                    ->where('email_queue.status', 'sent')
                    ->whereBetween(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'), [$from, $to])
                    ->selectRaw('
                        email_queue.id,
                        email_queue.email,
                        email_queue.subject,
                        email_queue.from_name,
                        email_queue.from_email,
                        email_queue.status,
                        COALESCE(email_queue.sent_at, email_queue.created_at) as sent_time,
                        COUNT(DISTINCT email_opens.id) as open_hits,
                        COUNT(DISTINCT email_clicks.id) as click_hits
                    ')
                    ->groupBy(
                        'email_queue.id',
                        'email_queue.email',
                        'email_queue.subject',
                        'email_queue.from_name',
                        'email_queue.from_email',
                        'email_queue.status',
                        DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)')
                    )
                    ->orderByDesc(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'))
                    ->get();

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->sent_time,
                        $row->email,
                        $row->from_name,
                        $row->from_email,
                        $row->subject,
                        $row->status,
                        (int) $row->open_hits > 0 ? 'Yes' : 'No',
                        (int) $row->click_hits > 0 ? 'Yes' : 'No',
                    ]);
                }
            } elseif ($type === 'warmup') {
                fputcsv($handle, ['Campaign', 'Status', 'Warmup Enabled', 'Warmup Day', 'Current Warmup Cap/Day', 'Emails/Minute', 'Sent', 'Pending']);

                $campaigns = Campaign::query()
                    ->where('account_id', $accountId)
                    ->whereBetween('created_at', [$from, $to])
                    ->orderByDesc('created_at')
                    ->get();

                foreach ($campaigns as $campaign) {
                    $sentCount = EmailQueue::where('campaign_id', $campaign->id)->where('status', 'sent')->count();
                    $pendingCount = EmailQueue::where('campaign_id', $campaign->id)->whereIn('status', ['queued', 'pending'])->count();

                    fputcsv($handle, [
                        $campaign->name,
                        $campaign->status,
                        $campaign->warmup_enabled ? 'Yes' : 'No',
                        (int) ($campaign->warmup_day ?: 0),
                        $campaign->warmup_enabled ? $campaign->currentWarmupCap() : 0,
                        (int) ($campaign->emails_per_minute ?: 0),
                        $sentCount,
                        $pendingCount,
                    ]);
                }
            } elseif ($type === 'smtp') {
                fputcsv($handle, [
                    'Recipient',
                    'SMTP Name',
                    'SMTP Host',
                    'SMTP Active',
                    'Health Status',
                    'Campaign',
                    'Queue Status',
                    'Opened',
                    'Clicked',
                    'Bounced',
                    'Last Error',
                    'Sent At',
                ]);

                $rows = EmailQueue::query()
                    ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
                    ->leftJoin('campaigns', 'campaigns.id', '=', 'email_queue.campaign_id')
                    ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('email_bounces', 'email_bounces.email_queue_id', '=', 'email_queue.id')
                    ->where('email_queue.account_id', $accountId)
                    ->whereBetween(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'), [$from, $to])
                    ->when($smtpId, fn ($q) => $q->where('email_queue.smtp_server_id', $smtpId))
                    ->when($recipient !== '', fn ($q) => $q->where('email_queue.email', 'like', '%' . $recipient . '%'))
                    ->when(in_array($status, ['working', 'not_working', 'disabled'], true), function ($q) use ($status) {
                        if ($status === 'disabled') {
                            $q->where(function ($x) {
                                $x->where('smtp_servers.is_active', false)->orWhereNull('smtp_servers.id');
                            });
                        } elseif ($status === 'working') {
                            $q->where('smtp_servers.is_active', true);
                        } elseif ($status === 'not_working') {
                            $q->where('email_queue.status', 'failed');
                        }
                    })
                    ->selectRaw('
                        email_queue.id,
                        email_queue.email,
                        email_queue.status as queue_status,
                        email_queue.last_error,
                        COALESCE(email_queue.sent_at, email_queue.created_at) as sent_time,
                        smtp_servers.name as smtp_name,
                        smtp_servers.host as smtp_host,
                        smtp_servers.is_active as smtp_is_active,
                        campaigns.name as campaign_name,
                        MAX(email_opens.id) as opened_id,
                        MAX(email_clicks.id) as clicked_id,
                        MAX(email_bounces.id) as bounced_id
                    ')
                    ->groupBy(
                        'email_queue.id',
                        'email_queue.email',
                        'email_queue.status',
                        'email_queue.last_error',
                        DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'),
                        'smtp_servers.name',
                        'smtp_servers.host',
                        'smtp_servers.is_active',
                        'campaigns.name'
                    )
                    ->orderByDesc(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'))
                    ->get();

                foreach ($rows as $row) {
                    $healthStatus = (is_null($row->smtp_name) || ! $row->smtp_is_active)
                        ? 'disabled'
                        : (($row->queue_status === 'failed') ? 'not_working' : 'working');

                    fputcsv($handle, [
                        $row->email,
                        $row->smtp_name ?: 'N/A',
                        $row->smtp_host ?: 'N/A',
                        is_null($row->smtp_is_active) ? 'No' : ((int) $row->smtp_is_active === 1 ? 'Yes' : 'No'),
                        $healthStatus,
                        $row->campaign_name ?: 'N/A',
                        $row->queue_status,
                        $row->opened_id ? 'Yes' : 'No',
                        $row->clicked_id ? 'Yes' : 'No',
                        $row->bounced_id ? 'Yes' : 'No',
                        $row->last_error,
                        $row->sent_time,
                    ]);
                }
            } elseif ($type === 'campaign-detail') {
                // Per-campaign full detail export (all statuses)
                fputcsv($handle, [
                    'Recipient Email',
                    'SMTP Name',
                    'SMTP Host',
                    'Status',
                    'Sent At',
                    'Opened',
                    'Clicked',
                    'Unsubscribed',
                    'Attempts',
                    'Last Error',
                ]);

                $detailRows = EmailQueue::query()
                    ->where('email_queue.campaign_id', $campaignId)
                    ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
                    ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('unsubscribes', 'unsubscribes.email', '=', 'email_queue.email')
                    ->selectRaw('
                        email_queue.id,
                        email_queue.email,
                        email_queue.status,
                        email_queue.attempts,
                        email_queue.last_error,
                        COALESCE(email_queue.sent_at, email_queue.created_at) as sent_time,
                        smtp_servers.name as smtp_name,
                        smtp_servers.host as smtp_host,
                        MAX(email_opens.id)  as opened_id,
                        MAX(email_clicks.id) as clicked_id,
                        MAX(unsubscribes.id) as unsub_id
                    ')
                    ->groupBy(
                        'email_queue.id', 'email_queue.email', 'email_queue.status',
                        'email_queue.attempts', 'email_queue.last_error',
                        DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'),
                        'smtp_servers.name', 'smtp_servers.host'
                    )
                    ->orderByDesc(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'))
                    ->get();

                foreach ($detailRows as $row) {
                    fputcsv($handle, [
                        $row->email,
                        $row->smtp_name ?: 'N/A',
                        $row->smtp_host ?: 'N/A',
                        $row->status,
                        $row->sent_time,
                        $row->opened_id  ? 'Yes' : 'No',
                        $row->clicked_id ? 'Yes' : 'No',
                        $row->unsub_id   ? 'Yes' : 'No',
                        (int) $row->attempts,
                        $row->last_error ?: '',
                    ]);
                }

            } elseif ($type === 'live-logs') {
                fputcsv($handle, [
                    'Time',
                    'Type',
                    'Recipient',
                    'Subject',
                    'Campaign',
                    'SMTP Name',
                    'SMTP Host',
                    'From Email',
                    'Status',
                    'Attempts',
                    'Last Error',
                ]);

                // Scope by account_id OR campaign ownership so no log is missed
                $accountCampaignIds = Campaign::where('account_id', $accountId)->pluck('id');

                $logsQuery = EmailQueue::query()
                    ->leftJoin('campaigns', 'campaigns.id', '=', 'email_queue.campaign_id')
                    ->leftJoin('smtp_servers', 'smtp_servers.id', '=', 'email_queue.smtp_server_id')
                    ->where(function ($q) use ($accountId, $accountCampaignIds) {
                        $q->where('email_queue.account_id', $accountId)
                          ->orWhereIn('email_queue.campaign_id', $accountCampaignIds);
                    });

                // Apply date filter only when a specific range was requested
                if ($from && $to) {
                    $logsQuery->whereBetween(
                        DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'),
                        [$from, $to]
                    );
                }

                if (in_array($status, ['queued', 'pending', 'sent', 'failed'], true)) {
                    $logsQuery->where('email_queue.status', $status);
                }
                if (in_array($emailTypeExport, ['campaign', 'single', 'test'], true)) {
                    $logsQuery->where('email_queue.type', $emailTypeExport);
                }
                if ($campaignId) {
                    $logsQuery->where('email_queue.campaign_id', $campaignId);
                }
                if ($smtpId) {
                    $logsQuery->where('email_queue.smtp_server_id', $smtpId);
                }
                if ($recipient !== '') {
                    $logsQuery->where('email_queue.email', 'like', '%' . $recipient . '%');
                }

                $liveRows = $logsQuery->selectRaw('
                        COALESCE(email_queue.sent_at, email_queue.created_at) as log_time,
                        email_queue.type,
                        email_queue.email,
                        email_queue.subject,
                        email_queue.from_email,
                        email_queue.status,
                        email_queue.attempts,
                        email_queue.last_error,
                        campaigns.name as campaign_name,
                        smtp_servers.name as smtp_name,
                        smtp_servers.host as smtp_host
                    ')
                    ->orderByDesc(DB::raw('COALESCE(email_queue.sent_at, email_queue.created_at)'))
                    ->orderByDesc('email_queue.id')
                    ->get();

                foreach ($liveRows as $row) {
                    fputcsv($handle, [
                        $row->log_time,
                        ucfirst($row->type ?? 'campaign'),
                        $row->email,
                        $row->subject ?: '',
                        $row->campaign_name ?: 'N/A',
                        $row->smtp_name ?: 'N/A',
                        $row->smtp_host ?: 'N/A',
                        $row->from_email ?: '',
                        $row->status,
                        (int) $row->attempts,
                        $row->last_error ?: '',
                    ]);
                }

            } else {
                fputcsv($handle, ['Campaign', 'Recipient Email', 'Sent At', 'Opened', 'Clicked', 'Unsubscribed', 'Status']);

                $rows = EmailQueue::query()
                    ->leftJoin('campaigns', 'campaigns.id', '=', 'email_queue.campaign_id')
                    ->leftJoin('email_opens', 'email_opens.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('email_clicks', 'email_clicks.email_queue_id', '=', 'email_queue.id')
                    ->leftJoin('unsubscribes', 'unsubscribes.email', '=', 'email_queue.email')
                    ->where('campaigns.account_id', $accountId)
                    ->where('email_queue.status', 'sent')
                    ->whereBetween('email_queue.sent_at', [$from, $to])
                    ->when($campaignId, fn ($q) => $q->where('email_queue.campaign_id', $campaignId))
                    ->selectRaw('
                        campaigns.name as campaign_name,
                        email_queue.email,
                        email_queue.sent_at,
                        email_queue.status,
                        MAX(email_opens.id) as opened_id,
                        MAX(email_clicks.id) as clicked_id,
                        MAX(unsubscribes.id) as unsub_id
                    ')
                    ->groupBy('campaigns.name', 'email_queue.email', 'email_queue.sent_at', 'email_queue.status')
                    ->orderByDesc('email_queue.sent_at')
                    ->get();

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->campaign_name,
                        $row->email,
                        optional($row->sent_at)->toDateTimeString(),
                        $row->opened_id ? 'Yes' : 'No',
                        $row->clicked_id ? 'Yes' : 'No',
                        $row->unsub_id ? 'Yes' : 'No',
                        $row->status,
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    // ── Email Preview (JSON) ───────────────────────────────────────────────────
    public function showEmail(Request $request, int $id)
    {
        $accountId = (int) ($request->user()?->account_id ?? 0);

        $emailLog = EmailQueue::query()
            ->where('account_id', $accountId)
            ->findOrFail($id);

        return response()->json([
            'id'            => $emailLog->id,
            'subject'       => $emailLog->subject,
            'to'            => $emailLog->email,
            'from_name'     => $emailLog->from_name,
            'from_email'    => $emailLog->from_email,
            'type'          => $emailLog->type,
            'status'        => $emailLog->status,
            'sent_at'       => optional($emailLog->sent_at)->toDateTimeString(),
            'created_at'    => optional($emailLog->created_at)->toDateTimeString(),
            'body_snapshot' => $emailLog->body_snapshot ?: $emailLog->body ?: '',
        ]);
    }
}
