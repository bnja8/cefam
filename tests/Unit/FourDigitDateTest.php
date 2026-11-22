<?php

namespace Tests\Unit;

use App\Rules\FourDigitDate;
use App\Rules\FourDigitDateTime;
use PHPUnit\Framework\TestCase;

class FourDigitDateTest extends TestCase
{
    public function test_accepts_a_valid_date_with_exactly_four_year_digits(): void
    {
        $this->assertNull($this->failureFor('2026-11-21'));
    }

    public function test_rejects_extended_years_unpadded_parts_and_invalid_calendar_dates(): void
    {
        foreach (['2000000-11-21', '2026-1-21', '2026-11-2', '2026-02-30'] as $date) {
            $this->assertNotNull($this->failureFor($date), $date);
        }
    }

    public function test_date_time_rule_accepts_local_values_and_rejects_extended_years(): void
    {
        $this->assertNull($this->failureForRule(new FourDigitDateTime(), '2026-11-21T21:16'));
        $this->assertNull($this->failureForRule(new FourDigitDateTime(), '2026-11-21 21:16:58'));
        $this->assertNotNull($this->failureForRule(new FourDigitDateTime(), '2000000-11-21T21:16'));
    }

    private function failureFor(string $value): ?string
    {
        return $this->failureForRule(new FourDigitDate(), $value);
    }

    private function failureForRule(FourDigitDate|FourDigitDateTime $rule, string $value): ?string
    {
        $failure = null;
        $rule->validate('fecha', $value, function (string $message) use (&$failure): void {
            $failure = $message;
        });

        return $failure;
    }
}
