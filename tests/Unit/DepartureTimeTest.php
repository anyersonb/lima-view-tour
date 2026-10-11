<?php

namespace Tests\Unit;

use App\Support\DepartureTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DepartureTimeTest extends TestCase
{
    public static function readable(): array
    {
        return [
            '12h with leading zero' => ['05:00 AM', '05:00'],
            'lowercase' => ['5:00 am', '05:00'],
            '24h' => ['05:00', '05:00'],
            'hour and meridiem only' => ['5 AM', '05:00'],
            'glued meridiem' => ['5am', '05:00'],
            'dotted meridiem' => ['5:00 a.m.', '05:00'],
            'midnight' => ['12:00 AM', '00:00'],
            'noon' => ['12:00 PM', '12:00'],
            'afternoon' => ['5:30 pm', '17:30'],
            'dot separator' => ['5.30 pm', '17:30'],
            'h suffix' => ['17h', '17:00'],
            'hs suffix' => ['17:30hs', '17:30'],
            'with seconds (TIME/TimePicker)' => ['05:00:00', '05:00'],
            'surrounding spaces' => [' 06:00 ', '06:00'],
            '00:00' => ['0:00', '00:00'],
            '8h' => ['8h', '08:00'],
        ];
    }

    #[DataProvider('readable')]
    public function test_it_normalizes_readable_values(string $raw, string $expected): void
    {
        $this->assertSame($expected, DepartureTime::normalize($raw));
    }

    public static function unreadable(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'blank' => ['   '],
            'free text' => ['Todo el día'],
            'range' => ['05:00 - 06:00'],
            'bare number is ambiguous' => ['5'],
            'pm over 12' => ['13:00 PM'],
            'hour 24' => ['24:00'],
            'minute 60' => ['7:60'],
            'zero with meridiem' => ['0:30 am'],
        ];
    }

    #[DataProvider('unreadable')]
    public function test_it_returns_null_for_what_it_cannot_read_with_certainty(?string $raw): void
    {
        $this->assertNull(DepartureTime::normalize($raw));
    }
}
