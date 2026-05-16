<?php

namespace Padmission\Tickets\Copilot\Services;

use Carbon\CarbonImmutable;
use Padmission\Tickets\Models\TicketActivity;

class EscalationDetector
{
    public function detect(TicketActivity $activity, string $query): ?string
    {
        $query = mb_strtolower($query);

        if (preg_match('/\b\d{3}-\d{2}-\d{4}\b|\bssn\b|\bdisabilit(?:y|ies)\b|\bincome\b/', $query)) {
            return 'pii_sensitive';
        }

        if ($this->hasPermissionDeniedToolResult($activity)) {
            return 'permission_denied';
        }

        if ($this->hasMissingPaymentToolResult($activity)) {
            return 'missing_payment';
        }

        if ($this->hasStaleToolResult($activity)) {
            return 'stale_state';
        }

        if ((str_contains($query, 'hap') || str_contains($query, 'rent') || str_contains($query, 'payment'))
            && (str_contains($query, 'reconcile') || str_contains($query, 'missing') || str_contains($query, 'receive'))
        ) {
            return 'financial_reconciliation';
        }

        if (preg_match('/\b(who|when)\b.*\b(changed|updated|modified)\b.*\b(hap|rent|payment|amount|status|assignee|owner|household|tenancy|record)\b/', $query)) {
            return 'audit_history';
        }

        $confidence = data_get($activity->data, 'confidence');

        if ($confidence !== null && $confidence < config('filament-copilot.escalation_triggers.low_confidence_threshold', 0.6)) {
            return 'low_confidence';
        }

        return data_get($activity->data, 'escalation_reason');
    }

    protected function hasPermissionDeniedToolResult(TicketActivity $activity): bool
    {
        foreach ($this->toolResults($activity) as $result) {
            if (is_array($result) && array_key_exists('available_actions', $result) && $result['available_actions'] === []) {
                return true;
            }

            $text = $this->stringify($result);

            if (str_contains($text, 'permission denied') || str_contains($text, 'access denied') || str_contains($text, 'not allowed')) {
                return true;
            }
        }

        return false;
    }

    protected function hasMissingPaymentToolResult(TicketActivity $activity): bool
    {
        foreach ($this->toolResults($activity) as $result) {
            if (is_array($result) && ($result['found'] ?? true) === false) {
                $recordType = mb_strtolower((string) ($result['record_type'] ?? $result['type'] ?? ''));

                if ($recordType === '' || str_contains($recordType, 'payment') || str_contains($recordType, 'transaction')) {
                    return true;
                }
            }

            $text = $this->stringify($result);

            if ((str_contains($text, 'not found') || str_contains($text, 'no record found'))
                && (str_contains($text, 'payment') || str_contains($text, 'transaction') || str_contains($text, 'hap'))
            ) {
                return true;
            }
        }

        return false;
    }

    protected function hasStaleToolResult(TicketActivity $activity): bool
    {
        $thresholdHours = (int) config('filament-copilot.escalation_triggers.stale_state_hours', 24);
        $staleBefore = now()->subHours($thresholdHours);

        foreach ($this->toolResults($activity) as $result) {
            $updatedAt = data_get($result, 'updated_at')
                ?? data_get($result, 'record.updated_at')
                ?? data_get($result, 'context.updated_at');

            if (! $updatedAt) {
                continue;
            }

            if (CarbonImmutable::parse($updatedAt)->lt($staleBefore)) {
                return true;
            }
        }

        return false;
    }

    protected function toolResults(TicketActivity $activity): array
    {
        return collect($activity->data['trace_tools'] ?? [])
            ->filter(fn (array $tool): bool => ($tool['status'] ?? null) === 'success')
            ->map(fn (array $tool): mixed => $tool['result'] ?? $tool['output'] ?? null)
            ->filter(fn (mixed $result): bool => $result !== null)
            ->values()
            ->all();
    }

    protected function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return mb_strtolower($value);
        }

        return mb_strtolower((string) json_encode($value, JSON_UNESCAPED_UNICODE));
    }
}
