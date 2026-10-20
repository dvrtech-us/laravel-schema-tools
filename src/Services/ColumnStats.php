<?php

namespace DVRTech\SchemaTools\Services;

/**
 * Running per-column statistics for one streamed analysis.
 */
class ColumnStats
{
    public bool $json = false;
    public bool $text = false;
    public bool $string = false;
    public bool $date = false;
    public bool $datetime = false;
    public bool $time = false;
    public bool $int = false;
    public bool $bigint = false;
    public bool $float = false;
    public bool $decimal = false;
    public bool $sawScientific = false;
    public bool $sawNonScientific = false;
    public bool $scientificOverflow = false;
    public bool $nullSeen = false;
    public bool $valueSeen = false;
    public bool $missingFromASource = false;
    public bool $exactNumeric = false;
    public bool $forcedString = false;
    public int $maxLength = 0;
    public int $maxScale = 0;
    public int $maxIntegerDigits = 0;
    public int $maxFractionDigits = 0;

    public function __construct(public string $name)
    {
    }
}
