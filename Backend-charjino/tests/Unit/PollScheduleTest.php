<?php

namespace Tests\Unit;

use App\Modules\Fulfillment\PollSchedule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PollScheduleTest extends TestCase
{
    public function test_steps_and_webhook_deferral(): void
    {
        config(['charjino.orders.poll_schedule' => '30,60,120']);
        $start = Carbon::parse('2026-10-07 12:00:00');
        $this->assertSame(30, PollSchedule::seconds()[0]);
        $first = PollSchedule::nextAt($start, 0);
        $this->assertTrue($first->equalTo($start->copy()->addSeconds(30)));

        $webhook = Carbon::parse('2026-10-07 12:10:00');
        $deferred = PollSchedule::nextAt($start, 0, $webhook);
        $this->assertTrue($deferred->equalTo($webhook->copy()->addMinutes(5)));
    }
}
