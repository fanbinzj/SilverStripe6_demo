<?php

namespace App\MarketData;

interface EarningsCalendarProvider
{
    /**
     * @return EarningsDate[]
     */
    public function fetchDate(string $date): array;

    public function getSourceName(): string;

    public function getSourceUrl(string $date): string;
}
