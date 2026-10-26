<?php

namespace App\Services;

use Carbon\Carbon;

class HolidayResolver
{
    /**
     * Return true if the given date is a holiday.
     * Currently a stub — plug in a config list, DB table, or API.
     */
    public function isHoliday(Carbon $date): bool
    {
        // Example: load from config('holidays.dates') as ["2026-12-25", ...]
        $dates = config('holidays.dates', []);
        return in_array($date->toDateString(), $dates, true);
    }
}