<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ImportRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Validator;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(public int $importRunId)
    {
    }

    public function handle(): void
    {
        $importRun = ImportRun::find($this->importRunId);
        if (!$importRun) {
            return;
        }

        $importRun->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $relativePath = trim((string) $importRun->stored_path);
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        $candidatePaths = [
            storage_path('app/' . $relativePath),
            storage_path('app/private/' . $relativePath),
            storage_path('app\\' . str_replace('/', '\\', $relativePath)),
            storage_path('app\\private\\' . str_replace('/', '\\', $relativePath)),
            base_path('storage/app/' . $relativePath),
            base_path('storage/app/private/' . $relativePath),
            base_path('storage\\app\\' . str_replace('/', '\\', $relativePath)),
            base_path('storage\\app\\private\\' . str_replace('/', '\\', $relativePath)),
        ];

        $absolutePath = null;
        foreach ($candidatePaths as $candidatePath) {
            if (is_file($candidatePath)) {
                $absolutePath = $candidatePath;
                break;
            }
        }

        if (!$absolutePath) {
            $importRun->update([
                'status' => 'failed',
                'error_message' => 'Uploaded CSV file not found. Path: ' . $relativePath,
                'finished_at' => now(),
            ]);
            return;
        }

        $handle = fopen($absolutePath, 'r');
        if (!$handle) {
            $importRun->update([
                'status' => 'failed',
                'error_message' => 'Could not open uploaded CSV file.',
                'finished_at' => now(),
            ]);
            return;
        }

        try {
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                $importRun->update([
                    'status' => 'failed',
                    'error_message' => 'CSV file appears to be empty.',
                    'finished_at' => now(),
                ]);
                return;
            }

            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
            $headers = array_map(static fn($h) => trim((string) $h), $headers);

            $nameCol = trim((string) ($importRun->name_column ?? ''));
            $firstNameCol = trim((string) ($importRun->first_name_column ?? ''));
            $lastNameCol = trim((string) ($importRun->last_name_column ?? ''));
            $emailCol = trim((string) ($importRun->email_column ?? 'email'));
            $businessNameCol = trim((string) ($importRun->business_name_column ?? ''));
            $websiteCol = trim((string) ($importRun->website_column ?? ''));

            $nameIndex = $nameCol !== '' ? array_search($nameCol, $headers, true) : false;
            $firstNameIndex = $firstNameCol !== '' ? array_search($firstNameCol, $headers, true) : false;
            $lastNameIndex = $lastNameCol !== '' ? array_search($lastNameCol, $headers, true) : false;
            $emailIndex = array_search($emailCol, $headers, true);
            $businessNameIndex = $businessNameCol !== '' ? array_search($businessNameCol, $headers, true) : false;
            $websiteIndex = $websiteCol !== '' ? array_search($websiteCol, $headers, true) : false;

            $hasName = $nameIndex !== false;
            $hasFirstName = $firstNameIndex !== false;

            if (!$hasName && !$hasFirstName) {
                fclose($handle);
                $importRun->update([
                    'status' => 'failed',
                    'error_message' => 'No valid name column found in CSV headers.',
                    'finished_at' => now(),
                ]);
                return;
            }

            if ($emailIndex === false) {
                fclose($handle);
                $importRun->update([
                    'status' => 'failed',
                    'error_message' => 'Email column not found in CSV headers.',
                    'finished_at' => now(),
                ]);
                return;
            }

            $allRows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $allRows[] = $row;
            }

            fclose($handle);

            $totalRows = count($allRows);
            $importRun->update([
                'total_rows' => $totalRows,
                'processed_rows' => 0,
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'failed_rows' => [],
            ]);

            $accountId = (int) ($importRun->account_id ?? 0);
            $groupIds = collect($importRun->group_ids ?? [])->map(fn($id) => (int) $id)->filter()->values()->all();

            $fileEmails = [];
            $failedRows = [];
            $processed = 0;
            $imported = 0;
            $skipped = 0;
            $rowNumber = 1; // header row at 1

            foreach ($allRows as $row) {
                $rowNumber++;
                $processed++;
                $reasons = [];

                if ($hasName) {
                    $name = trim((string) ($row[$nameIndex] ?? ''));
                } else {
                    $firstName = trim((string) ($row[$firstNameIndex] ?? ''));
                    $lastName = $lastNameIndex !== false ? trim((string) ($row[$lastNameIndex] ?? '')) : '';
                    $name = trim($firstName . ' ' . $lastName);
                }

                $email = strtolower(trim((string) ($row[$emailIndex] ?? '')));
                $businessName = $businessNameIndex !== false ? trim((string) ($row[$businessNameIndex] ?? '')) : null;
                $website = $websiteIndex !== false ? trim((string) ($row[$websiteIndex] ?? '')) : null;

                if ($website && !preg_match('/^https?:\/\//i', $website)) {
                    $website = 'https://' . $website;
                }

                $validator = Validator::make(
                    ['name' => $name, 'email' => $email, 'website' => $website ?: null],
                    [
                        'name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'max:255'],
                        'website' => ['nullable', 'url', 'max:255'],
                    ]
                );

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $msg) {
                        $reasons[] = $msg;
                    }
                }

                if ($email !== '' && in_array($email, $fileEmails, true)) {
                    $reasons[] = 'Duplicate email in this file';
                }

                if (
                    $email !== '' &&
                    empty($reasons) &&
                    Contact::where('account_id', $accountId)->where('email', $email)->exists()
                ) {
                    $reasons[] = 'Email already exists in contacts';
                }

                if (!empty($reasons)) {
                    $skipped++;
                    $failedRows[] = [
                        'row' => $rowNumber,
                        'name' => $name !== '' ? $name : '—',
                        'email' => $email !== '' ? $email : '—',
                        'reasons' => $reasons,
                    ];
                } else {
                    $contact = Contact::create([
                        'account_id' => $accountId,
                        'name' => $name,
                        'business_name' => $businessName ?: null,
                        'email' => $email,
                        'website' => $website ?: null,
                    ]);

                    if (!empty($groupIds)) {
                        $contact->groups()->sync($groupIds);
                    }

                    $fileEmails[] = $email;
                    $imported++;
                }

                if ($processed % 25 === 0 || $processed === $totalRows) {
                    $importRun->update([
                        'processed_rows' => $processed,
                        'imported_rows' => $imported,
                        'skipped_rows' => $skipped,
                        'failed_rows' => $failedRows,
                    ]);
                }
            }

            $importRun->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'imported_rows' => $imported,
                'skipped_rows' => $skipped,
                'failed_rows' => $failedRows,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            $importRun->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
