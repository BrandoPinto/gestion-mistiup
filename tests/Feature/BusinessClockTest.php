<?php

namespace Tests\Feature;

use App\Domain\Shared\Dates\BusinessClock;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessClockTest extends TestCase
{
    public function test_today_follows_lima_not_utc(): void
    {
        // 03:00 UTC del 5 de octubre = 22:00 del 4 de octubre en Lima.
        Carbon::setTestNow(Carbon::parse('2026-10-05 03:00:00', 'UTC'));

        $this->assertSame('2026-10-04', app(BusinessClock::class)->todayString());
    }

    public function test_today_is_a_calendar_date_comparable_with_date_columns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 03:00:00', 'UTC'));

        $today = app(BusinessClock::class)->today();

        // Mismo instante que una columna DATE '2026-10-04' casteada por Eloquent (medianoche en la zona de la app).
        $this->assertTrue($today->equalTo(Carbon::parse('2026-10-04')));
    }

    public function test_app_timestamps_remain_in_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('America/Lima', app(BusinessClock::class)->timezone());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
