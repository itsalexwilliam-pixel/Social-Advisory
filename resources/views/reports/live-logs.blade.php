@extends('layouts.app')

@section('page_title', 'Live Logs')

@section('content')
<div class="space-y-6">

    {{-- ── Tab Navigation ──────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2 shadow-sm">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.single-email') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Single Email Report
            </a>
            <a href="{{ route('reports.index') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Campaign Report
            </a>
            <a href="{{ route('reports.warmup') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Warmup Report
            </a>
            <a href="{{ route('reports.smtp') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                SMTP Report
            </a>
            <a href="{{ route('reports.live-logs') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white border border-indigo-600">
                Live Logs
            </a>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('reports.live-logs') }}" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">

            {{-- Date Range --}}
            <div>
                <label for="date_range" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all"    {{ ($filters['date_range'] ?? 'all') === 'all'    ? 'selected' : '' }}>All Time</option>
                    <option value="today"  {{ ($filters['date_range'] ?? 'all') === 'today'  ? 'selected' : '' }}>Today</option>
                    <option value="7d"     {{ ($filters['date_range'] ?? 'all') === '7d'     ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30d"    {{ ($filters['date_range'] ?? 'all') === '30d'    ? 'selected' : '' }}>Last 30 days</option>
                    <option value="custom" {{ ($filters['date_range'] ?? 'all') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>

            {{-- From --}}
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>

            {{-- To --}}
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>

            {{-- Email Type --}}
            <div>
                <label for="email_type" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Type</label>
                <select id="email_type" name="email_type" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all"      {{ ($filters['email_type'] ?? 'all') === 'all'      ? 'selected' : '' }}>All Types</option>
                    <option value="campaign" {{ ($filters['email_type'] ?? 'all') === 'campaign' ? 'selected' : '' }}>Campaign</option>
                    <option value="single"   {{ ($filters['email_type'] ?? 'all') === 'single'   ? 'selected' : '' }}>Single Email</option>
                    <option value="test"     {{ ($filters['email_type'] ?? 'all') === 'test'     ? 'selected' : '' }}>Test Mail</option>
                </select>
            </div>

            {{-- Status --}}
            <div>
                <label for="status" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all"     {{ ($filters['status'] ?? 'all') === 'all'     ? 'selected' : '' }}>All</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending'    ? 'selected' : '' }}>Pending</option>
                    <option value="sent"    {{ ($filters['status'] ?? '') === 'sent'       ? 'selected' : '' }}>Sent</option>
                    <option value="failed"  {{ ($filters['status'] ?? '') === 'failed'     ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            {{-- Campaign --}}
            <div>
                <label for="campaign_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Campaign</label>
                <select id="campaign_id" name="campaign_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All campaigns</option>
                    @foreach($campaignOptions as $campaign)
                        <option value="{{ $campaign->id }}" {{ (int)($filters['campaign_id'] ?? 0) === (int)$campaign->id ? 'selected' : '' }}>
                            {{ $campaign->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- SMTP --}}
            <div>
                <label for="smtp_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">SMTP</label>
                <select id="smtp_id" name="smtp_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All SMTP</option>
                    @foreach($smtpOptions as $smtp)
                        <option value="{{ $smtp->id }}" {{ (int)($filters['smtp_id'] ?? 0) === (int)$smtp->id ? 'selected' : '' }}>
                            {{ $smtp->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Recipient --}}
            <div>
                <label for="recipient" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Recipient</label>
                <input id="recipient" type="text" name="recipient" value="{{ $filters['recipient'] ?? '' }}"
                       placeholder="search@email.com"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit"
                    class="inline-flex items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2">
                Apply
            </button>
            <a href="{{ route('reports.live-logs') }}"
               class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm px-4 py-2">
                Reset
            </a>
            <button type="button" id="toggle-refresh"
                    class="inline-flex items-center rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30 text-sm px-4 py-2">
                Auto Refresh: ON (10s)
            </button>
            <a href="{{ route('reports.export', array_filter([
                    'type'        => 'live-logs',
                    'date_range'  => $filters['date_range'] ?? 'all',
                    'from'        => $filters['from'] ?? null,
                    'to'          => $filters['to'] ?? null,
                    'email_type'  => ($filters['email_type'] ?? 'all') !== 'all' ? $filters['email_type'] : null,
                    'status'      => ($filters['status'] ?? 'all') !== 'all' ? $filters['status'] : null,
                    'campaign_id' => $filters['campaign_id'] ?? null,
                    'smtp_id'     => $filters['smtp_id'] ?? null,
                    'recipient'   => $filters['recipient'] ?? null,
                ])) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30 text-sm px-4 py-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </a>
        </div>
    </form>

    {{-- ── Summary Stats — Row 1: Status ──────────────────────────────────── --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-saas-stat-card title="Total Logs"      :value="$summary['total']  ?? 0" />
        <x-saas-stat-card title="Sent"            :value="$summary['sent']   ?? 0" />
        <x-saas-stat-card title="Pending"         :value="$summary['queued'] ?? 0" />
        <x-saas-stat-card title="Failed"          :value="$summary['failed'] ?? 0" />
    </div>

    {{-- ── Summary Stats — Row 2: By Type ─────────────────────────────────── --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-2xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-4 shadow-sm text-center">
            <div class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($summary['campaign'] ?? 0) }}</div>
            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mt-1">Campaign Emails</div>
        </div>
        <div class="rounded-2xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-900/20 p-4 shadow-sm text-center">
            <div class="text-2xl font-bold text-violet-700 dark:text-violet-300">{{ number_format($summary['single'] ?? 0) }}</div>
            <div class="text-xs font-medium text-violet-600 dark:text-violet-400 mt-1">Single Emails</div>
        </div>
        <div class="rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 shadow-sm text-center">
            <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ number_format($summary['test'] ?? 0) }}</div>
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400 mt-1">Test Emails</div>
        </div>
    </div>

    {{-- ── Logs Table ───────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                Sending Live Logs
                <span class="ml-2 text-xs font-normal text-slate-500 dark:text-slate-400">
                    ({{ number_format($logs->total()) }} total &middot; showing {{ $logs->perPage() }} per page)
                </span>
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <th class="py-2 pr-3 whitespace-nowrap">#</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Type</th>
                        <th class="py-2 pr-3 whitespace-nowrap">Time</th>
                        <th class="py-2 pr-3">Recipient</th>
                        <th class="py-2 pr-3">From</th>
                        <th class="py-2 pr-3">Subject</th>
                        <th class="py-2 pr-3">Campaign</th>
                        <th class="py-2 pr-3">SMTP Used</th>
                        <th class="py-2 pr-3 text-center">Tries</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $index => $log)
                        <tr class="border-b border-slate-100 dark:border-slate-800/70 hover:bg-slate-50/50 dark:hover:bg-slate-800/30">

                            {{-- Row # --}}
                            <td class="py-2 pr-3 text-xs text-slate-400 whitespace-nowrap">{{ $logs->firstItem() + $index }}</td>

                            {{-- Type Badge --}}
                            <td class="py-2 pr-3 whitespace-nowrap">
                                @if($log->type === 'campaign')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-medium">Campaign</span>
                                @elseif($log->type === 'single')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300 font-medium">Single</span>
                                @elseif($log->type === 'test')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 font-medium">Test</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($log->type ?? '?') }}</span>
                                @endif
                            </td>

                            {{-- Time --}}
                            <td class="py-2 pr-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                {{ \Carbon\Carbon::parse($log->sent_at ?? $log->created_at)->format('Y-m-d H:i:s') }}
                            </td>

                            {{-- Recipient --}}
                            <td class="py-2 pr-3 font-medium text-slate-800 dark:text-slate-100">{{ $log->email }}</td>

                            {{-- From Email --}}
                            <td class="py-2 pr-3 text-xs text-slate-500 dark:text-slate-400 max-w-[130px] truncate" title="{{ $log->from_email }}">
                                {{ $log->from_email ?: '—' }}
                            </td>

                            {{-- Subject --}}
                            <td class="py-2 pr-3 max-w-[180px] truncate text-slate-600 dark:text-slate-300" title="{{ $log->subject }}">
                                {{ $log->subject ?: '—' }}
                            </td>

                            {{-- Campaign --}}
                            <td class="py-2 pr-3 max-w-[130px] truncate text-slate-500 dark:text-slate-400 text-xs" title="{{ $log->campaign_name }}">
                                {{ $log->campaign_name ?: '—' }}
                            </td>

                            {{-- SMTP Used --}}
                            <td class="py-2 pr-3">
                                @if($log->smtp_name)
                                    <span class="font-medium text-indigo-700 dark:text-indigo-300">{{ $log->smtp_name }}</span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500 block">{{ $log->smtp_host }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            {{-- Tries --}}
                            <td class="py-2 pr-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ $log->attempts ?? 0 }}</td>

                            {{-- Status --}}
                            <td class="py-2 pr-3">
                                @if($log->status === 'sent')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Sent</span>
                                @elseif(in_array($log->status, ['queued', 'pending']))
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ ucfirst($log->status) }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Failed</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($log->status ?? '?') }}</span>
                                @endif
                            </td>

                            {{-- Error --}}
                            <td class="py-2 pr-3 max-w-[220px] truncate text-xs text-rose-600 dark:text-rose-400" title="{{ $log->last_error }}">
                                {{ $log->last_error ?: '—' }}
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-10 text-center text-slate-500 dark:text-slate-400">
                                No email logs found. Try adjusting your filters or send an email first.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const dateRange = document.getElementById('date_range');
    const fromInput = document.getElementById('from');
    const toInput   = document.getElementById('to');
    const toggleBtn = document.getElementById('toggle-refresh');

    // Dim From/To unless "Custom" is selected
    function syncCustomDate() {
        const isCustom = dateRange?.value === 'custom';
        if (fromInput) { fromInput.disabled = !isCustom; fromInput.style.opacity = isCustom ? '1' : '0.4'; }
        if (toInput)   { toInput.disabled   = !isCustom; toInput.style.opacity   = isCustom ? '1' : '0.4'; }
    }

    // Auto refresh every 10 seconds
    let autoRefresh = true;
    setInterval(() => { if (autoRefresh) window.location.reload(); }, 10000);

    toggleBtn?.addEventListener('click', function () {
        autoRefresh = !autoRefresh;
        this.textContent = autoRefresh ? 'Auto Refresh: ON (10s)' : 'Auto Refresh: OFF';
    });

    dateRange?.addEventListener('change', syncCustomDate);
    syncCustomDate();
})();
</script>
@endpush
