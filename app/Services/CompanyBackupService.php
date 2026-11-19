<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;

final class CompanyBackupService
{
    private const EXCLUDED_TABLES = [
        'company_memberships',
        'company_invitations',
        'company_audit_events',
    ];

    public function create(Company $company): string
    {
        $tables = [];
        foreach ($this->companyTables() as $table) {
            if ($this->isProtectedTable($table)) continue;
            $tables[$table] = DB::table($table)
                ->where('company_id', $company->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        $payload = [
            'format' => 'rmc-flow-company-backup',
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'company' => [
                'id' => (int) $company->id,
                'document' => (string) ($company->document ?? ''),
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'email' => $company->email,
                'phone' => $company->phone,
                'timezone' => $company->timezone,
                'last_sale_number' => (int) ($company->last_sale_number ?? 0),
                'last_quote_number' => (int) ($company->last_quote_number ?? 0),
                'last_work_order_number' => (int) ($company->last_work_order_number ?? 0),
            ],
            'tables' => $tables,
        ];

        return $this->encodeEnvelope($payload);
    }

    public function restore(Company $company, string $contents): int
    {
        try {
            $envelope = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['backup_file' => 'O arquivo de backup não contém um JSON válido.']);
        }

        $payload = $envelope['payload'] ?? null;
        $signature = $envelope['signature'] ?? null;
        if (! is_array($payload) || ! is_string($signature) || ! hash_equals($this->signature($payload), $signature)) {
            throw ValidationException::withMessages(['backup_file' => 'O backup está inválido ou foi alterado. Gere um novo arquivo pelo sistema.']);
        }
        if (($payload['format'] ?? null) !== 'rmc-flow-company-backup' || ($payload['version'] ?? null) !== 1) {
            throw ValidationException::withMessages(['backup_file' => 'Este formato de backup não é compatível com esta versão do sistema.']);
        }
        if ((int) ($payload['company']['id'] ?? 0) !== (int) $company->id) {
            throw ValidationException::withMessages(['backup_file' => 'Este backup pertence a outra empresa.']);
        }
        if (! is_array($payload['tables'] ?? null) || $payload['tables'] === []) {
            throw ValidationException::withMessages(['backup_file' => 'O backup não contém dados para restaurar.']);
        }

        $allowedTables = array_fill_keys($this->companyTables(), true);
        foreach ($payload['tables'] as $table => $rows) {
            if (! is_string($table) || ! isset($allowedTables[$table]) || $this->isProtectedTable($table) || ! is_array($rows)) {
                throw ValidationException::withMessages(['backup_file' => 'O backup contém uma tabela não permitida.']);
            }
            if (! Schema::hasColumn($table, 'company_id')) {
                throw ValidationException::withMessages(['backup_file' => 'O backup contém dados fora do escopo da empresa.']);
            }
            $columns = Schema::getColumnListing($table);
            foreach ($rows as $row) {
                if (! is_array($row) || (int) ($row['company_id'] ?? 0) !== (int) $company->id || array_diff(array_keys($row), $columns)) {
                    throw ValidationException::withMessages(['backup_file' => 'O backup contém um registro inválido ou de outra empresa.']);
                }
            }
            $this->assertNoCrossCompanyIdCollision($table, $rows, (int) $company->id);
        }

        $restoreDocument = trim((string) ($payload['company']['document'] ?? ''));
        if ($restoreDocument !== '' && Company::query()->where('document', $restoreDocument)->where('id', '<>', $company->id)->exists()) {
            throw ValidationException::withMessages(['backup_file' => 'O CNPJ/CPF do backup está sendo usado por outra empresa. O backup não pode ser restaurado.']);
        }

        $connection = DB::connection();
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $connection->transaction(function () use ($company, $payload): void {
                foreach ($payload['tables'] as $table => $rows) {
                    DB::table($table)->where('company_id', $company->id)->delete();
                }
                foreach ($payload['tables'] as $table => $rows) {
                    foreach (array_chunk($rows, 250) as $batch) {
                        if ($batch !== []) DB::table($table)->insert($batch);
                    }
                }

                $snapshot = $payload['company'] ?? [];
                $company->update([
                    'legal_name' => $snapshot['legal_name'] ?? $company->legal_name,
                    'trade_name' => $snapshot['trade_name'] ?? $company->trade_name,
                    'document' => $snapshot['document'] ?? $company->document,
                    'email' => $snapshot['email'] ?? null,
                    'phone' => $snapshot['phone'] ?? null,
                    'timezone' => $snapshot['timezone'] ?? $company->timezone,
                    'last_sale_number' => max((int) $company->last_sale_number, (int) ($snapshot['last_sale_number'] ?? 0)),
                    'last_quote_number' => max((int) $company->last_quote_number, (int) ($snapshot['last_quote_number'] ?? 0)),
                    'last_work_order_number' => max((int) $company->last_work_order_number, (int) ($snapshot['last_work_order_number'] ?? 0)),
                ]);
            });
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return collect($payload['tables'])->sum(fn ($rows) => count($rows));
    }

    private function companyTables(): array
    {
        $tables = [];
        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = (string) array_values((array) $row)[0];
            if (Schema::hasColumn($table, 'company_id')) $tables[] = $table;
        }

        sort($tables);
        return $tables;
    }

    private function isProtectedTable(string $table): bool
    {
        return in_array($table, self::EXCLUDED_TABLES, true)
            || str_contains($table, 'subscription')
            || str_contains($table, 'payment_event');
    }

    private function assertNoCrossCompanyIdCollision(string $table, array $rows, int $companyId): void
    {
        $ids = collect($rows)->pluck('id')->filter()->unique()->values()->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            if (DB::table($table)->whereIn('id', $chunk)->where('company_id', '<>', $companyId)->exists()) {
                throw ValidationException::withMessages(['backup_file' => 'A restauração foi interrompida para proteger registros de outra empresa. O backup precisa ser analisado.']);
            }
        }
    }

    private function encodeEnvelope(array $payload): string
    {
        return json_encode([
            'payload' => $payload,
            'signature' => $this->signature($payload),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function signature(array $payload): string
    {
        $serialized = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return hash_hmac('sha256', $serialized, (string) config('app.key'));
    }
}
