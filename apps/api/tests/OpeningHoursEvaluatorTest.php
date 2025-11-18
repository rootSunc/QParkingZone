<?php
declare(strict_types=1);

use ParkingZones\Domain\OpeningHoursEvaluator;
use PHPUnit\Framework\TestCase;

final class OpeningHoursEvaluatorTest extends TestCase
{
    public function testEvaluatesOpenWeekdayMorning(): void
    {
        $evaluator = new OpeningHoursEvaluator(new DateTimeImmutable('2025-01-13T10:00:00+02:00'));
        $result = $evaluator->evaluate('active', [
            'weekdays' => '06:00-23:00',
            'weekends' => '08:00-23:00',
        ]);

        self::assertTrue($result['isOpen']);
        self::assertSame('open', $result['state']);
        self::assertSame('Open now', $result['badge']);
        self::assertSame('Closes at 23:00', $result['detail']);
    }

    public function testInactiveZonesAreNeverOpen(): void
    {
        $evaluator = new OpeningHoursEvaluator(new DateTimeImmutable('2025-01-13T10:00:00+02:00'));
        $result = $evaluator->evaluate('inactive', [
            'weekdays' => '00:00-23:59',
            'weekends' => '00:00-23:59',
        ]);

        self::assertFalse($result['isOpen']);
        self::assertSame('inactive', $result['state']);
    }
}
