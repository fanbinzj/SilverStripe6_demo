<?php

namespace App\MarketData;

/**
 * Reads values out of an SEC "companyfacts" document.
 *
 * Each concept has a list of reported values. The same period is often reported
 * more than once (original filing, later filings, amendments), so for each
 * period end date the most recently filed value wins.
 */
class XbrlFacts
{
    public function __construct(
        private readonly array $companyFacts,
    ) {
    }

    /**
     * Latest point-in-time value (e.g. cash, shares outstanding).
     *
     * @param string[] $concepts tried in order; the first concept with data is used
     * @return array{value: float, asOf: string}|null
     */
    public function latestInstant(string $taxonomy, array $concepts, string $unit): ?array
    {
        foreach ($concepts as $concept) {
            $entries = $this->entries($taxonomy, $concept, $unit);
            if ($entries) {
                $latest = end($entries);
                return ['value' => (float) $latest['val'], 'asOf' => $latest['end']];
            }
        }
        return null;
    }

    /**
     * Latest three-month value of a flow (e.g. operating cash flow).
     *
     * Cash flow statements are usually reported year-to-date: a Q2 10-Q reports
     * January to June. When the latest value covers more than a quarter, the
     * quarter is worked out by subtracting the previous year-to-date value with
     * the same start date (Jan-Jun minus Jan-Mar = Apr-Jun).
     *
     * @return array{value: float, asOf: string}|null
     */
    public function latestQuarter(string $taxonomy, string $concept, string $unit): ?array
    {
        $entries = array_filter($this->entries($taxonomy, $concept, $unit), fn(array $e) => isset($e['start']));
        if (!$entries) {
            return null;
        }

        $latest = end($entries);
        $days = self::daysBetween($latest['start'], $latest['end']);
        if ($days <= 100) {
            return ['value' => (float) $latest['val'], 'asOf' => $latest['end']];
        }

        foreach ($entries as $previous) {
            $gap = self::daysBetween($previous['end'], $latest['end']);
            if ($previous['start'] === $latest['start'] && $gap >= 80 && $gap <= 100) {
                return ['value' => (float) $latest['val'] - (float) $previous['val'], 'asOf' => $latest['end']];
            }
        }

        return null;
    }

    /**
     * Values for one concept, one per period, sorted by period end date.
     */
    private function entries(string $taxonomy, string $concept, string $unit): array
    {
        $raw = $this->companyFacts['facts'][$taxonomy][$concept]['units'][$unit] ?? [];

        $byPeriod = [];
        foreach ($raw as $entry) {
            $key = ($entry['start'] ?? '') . '/' . $entry['end'];
            if (!isset($byPeriod[$key]) || $entry['filed'] >= $byPeriod[$key]['filed']) {
                $byPeriod[$key] = $entry;
            }
        }

        usort($byPeriod, fn(array $a, array $b) => [$a['end'], $a['start'] ?? ''] <=> [$b['end'], $b['start'] ?? '']);
        return $byPeriod;
    }

    private static function daysBetween(string $from, string $to): int
    {
        return (int) ((strtotime($to) - strtotime($from)) / 86400);
    }
}
