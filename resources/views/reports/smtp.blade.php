@extends('layouts.app')

@section('page_title', 'SMTP Report')

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
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Warmup Report
            </a>
            <a href="{{ route('reports.smtp') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white border border-indigo-600">
                SMTP Report
            </a>
            <a href="{{ route('reports.live-logs') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Live Logs
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.smtp') }}" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div>
                <label for="date_range" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="7d" {{ ($filters['date_range'] ?? '30d') === '7d' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30d" {{ ($filters['date_range'] ?? '30d') === '30d' ? 'selected' : '' }}>Last 30 days</option>
                    <option value="custom" {{ ($filters['date_range'] ?? '30d') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="smtp_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">SMTP</label>
                <select id="smtp_id" name="smtp_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All SMTP</option>
                    @foreach($smtpOptions as $smtp)
                        <option value="{{ $smtp->id }}" {{ (int) ($filters['smtp_id'] ?? 0) === (int) $smtp->id ? 'selected' : '' }}>{{ $smtp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Health</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="working" {{ ($filters['status'] ?? '') === 'working' ? 'selected' : '' }}>Working</option>
                    <option value="not_working" {{ ($filters['status'] ?? '') === 'not_working' ? 'selected' : '' }}>Not Working</option>
                    <option value="disabled" {{ ($filters['status'] ?? '') === 'disabled' ? 'selected' : '' }}>Disabled</option>
                </select>
            </div>
            <div>
                <label for="recipient" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Recipient</label>
                <input id="recipient" type="text" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="search@email.com" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2">Apply</button>
            <a href="{{ route('reports.smtp') }}" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm px-4 py-2">Reset</a>
            <a href="{{ route('reports.export', array_filter(['type' => 'smtp', 'date_range' => $filters['date_range'] ?? '30d', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'smtp_id' => $filters['smtp_id'] ?? null, 'status' => $filters['status'] ?? null, 'recipient' => $filters['recipient'] ?? null])) }}"
               class="inline-flex items-center rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30 text-sm px-4 py-2">
                Export SMTP Report
            </a>
        </div>
    </form>

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
        <x-saas-stat-card title="Total SMTP" :value="$summary['total_smtp'] ?? 0" />
        <x-saas-stat-card title="Active SMTP" :value="$summary['active_smtp'] ?? 0" />
        <x-saas-stat-card title="Inactive SMTP" :value="$summary['inactive_smtp'] ?? 0" />
        <x-saas-stat-card title="Dead / Not Working" :value="$summary['dead_smtp'] ?? 0" />
        <x-saas-stat-card title="Disabled SMTP" :value="$summary['disabled_smtp'] ?? 0" />
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-4">SMTP Health Overview</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <th class="py-2 pr-3">SMTP</th>
                        <th class="py-2 pr-3">Host</th>
                        <th class="py-2 pr-3">Active</th>
                        <th class="py-2 pr-3">Health</th>
                        <th class="py-2 pr-3 text-right">Sent</th>
                        <th class="py-2 pr-3 text-right">Failed</th>
                        <th class="py-2 pr-3">Last Used</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($smtpHealthRows as $row)
                        <tr class="border-b border-slate-100 dark:border-slate-800/70">
                            <td class="py-2 pr-3 font-medium">{{ $row->name }}</td>
                            <td class="py-2 pr-3">{{ $row->host }}</td>
                            <td class="py-2 pr-3">{{ $row->is_active ? 'Yes' : 'No' }}</td>
                            <td class="py-2 pr-3">
                                @if($row->health_status === 'working')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Working</span>
                                @elseif($row->health_status === 'not_working')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Not Working</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">Disabled</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">{{ number_format($row->sent_total) }}</td>
                            <td class="py-2 pr-3 text-right">{{ number_format($row->fail_total) }}</td>
                            <td class="py-2 pr-3">{{ $row->last_used_at ? $row->last_used_at->diffForHumans() : 'Never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-500">No SMTP data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-4">Recipient → SMTP Usage</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <th class="py-2 pr-3">Recipient</th>
                        <th class="py-2 pr-3">SMTP</th>
                        <th class="py-2 pr-3">Campaign</th>
                        <th class="py-2 pr-3">Queue Status</th>
                        <th class="py-2 pr-3">Opened</th>
                        <th class="py-2 pr-3">Clicked</th>
                        <th class="py-2 pr-3">Bounced</th>
                        <th class="py-2 pr-3">Last Error</th>
                        <th class="py-2 pr-3">Sent At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipientRows as $row)
                        <tr class="border-b border-slate-100 dark:border-slate-800/70">
                            <td class="py-2 pr-3">{{ $row->email }}</td>
                            <td class="py-2 pr-3">{{ $row->smtp_name ?? 'N/A' }}</td>
                            <td class="py-2 pr-3">{{ $row->campaign_name ?? 'N/A' }}</td>
                            <td class="py-2 pr-3">{{ ucfirst($row->queue_status ?? 'unknown') }}</td>
                            <td class="py-2 pr-3">{{ $row->opened_id ? 'Yes' : 'No' }}</td>
                            <td class="py-2 pr-3">{{ $row->clicked_id ? 'Yes' : 'No' }}</td>
                            <td class="py-2 pr-3">{{ $row->bounced_id ? 'Yes' : 'No' }}</td>
                            <td class="py-2 pr-3 max-w-[260px] truncate" title="{{ $row->last_error }}">{{ $row->last_error ?: '—' }}</td>
                            <td class="py-2 pr-3">{{ $row->sent_at ?: $row->created_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-slate-500">No recipient SMTP usage data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $recipientRows->links() }}
        </div>
    </div>
</div>
@endsection
