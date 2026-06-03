@extends('layouts.app')

@section('page_title', 'Warmup Report')

@section('content')
<div class="space-y-6">
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
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white border border-indigo-600">
                Warmup Report
            </a>
            <a href="{{ route('reports.smtp') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                SMTP Report
            </a>
            <a href="{{ route('reports.live-logs') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Live Logs
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.warmup') }}" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label for="date_range" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="today" {{ ($filters['date_range'] ?? '30d') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="7d" {{ ($filters['date_range'] ?? '30d') === '7d' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30d" {{ ($filters['date_range'] ?? '30d') === '30d' ? 'selected' : '' }}>Last 30 days</option>
                    <option value="custom" {{ ($filters['date_range'] ?? '30d') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 inline-flex justify-center items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2">
                    Apply
                </button>
                <a href="{{ route('reports.warmup') }}" class="flex-1 inline-flex justify-center items-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm px-4 py-2">
                    Reset
                </a>
                <a href="{{ route('reports.export', array_filter(['type' => 'warmup', 'date_range' => $filters['date_range'] ?? '30d', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])) }}"
                   class="flex-1 inline-flex justify-center items-center rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30 text-sm px-4 py-2">
                    Export CSV
                </a>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <p class="text-xs text-slate-500 dark:text-slate-400">Campaigns</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $metrics['campaigns'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <p class="text-xs text-slate-500 dark:text-slate-400">Warmup Enabled</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $metrics['warmup_enabled'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <p class="text-xs text-slate-500 dark:text-slate-400">Total Sent</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $metrics['total_sent'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <p class="text-xs text-slate-500 dark:text-slate-400">Total Pending</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $metrics['total_pending'] ?? 0 }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <th class="py-2 pr-3">Campaign</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Warmup</th>
                        <th class="py-2 pr-3 text-right">Day</th>
                        <th class="py-2 pr-3 text-right">Cap / Day</th>
                        <th class="py-2 pr-3 text-right">Emails / Min</th>
                        <th class="py-2 pr-3 text-right">Sent</th>
                        <th class="py-2 pr-3 text-right">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-b border-slate-100 dark:border-slate-800/70">
                            <td class="py-2 pr-3 text-slate-700 dark:text-slate-200">{{ $row->name }}</td>
                            <td class="py-2 pr-3 text-slate-600 dark:text-slate-300">{{ ucfirst($row->status) }}</td>
                            <td class="py-2 pr-3">
                                @if($row->warmup_enabled)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Enabled</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">Disabled</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-200">{{ $row->warmup_day }}</td>
                            <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-200">{{ $row->current_warmup_cap }}</td>
                            <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-200">{{ $row->emails_per_minute }}</td>
                            <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-200">{{ $row->sent_count }}</td>
                            <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-200">{{ $row->pending_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-500">No warmup campaign data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const dateRange = document.getElementById('date_range');
    const fromInput = document.getElementById('from');
    const toInput = document.getElementById('to');

    function syncCustomDate() {
        const isCustom = dateRange && dateRange.value === 'custom';
        if (fromInput) fromInput.disabled = !isCustom;
        if (toInput) toInput.disabled = !isCustom;
    }

    dateRange?.addEventListener('change', syncCustomDate);
    syncCustomDate();
})();
</script>
@endpush
