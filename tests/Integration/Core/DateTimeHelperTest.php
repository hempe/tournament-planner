<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use DateTime;
use DateTimeZone;
use TP\Core\DateTimeHelper;
use TP\Core\Translator;
use TP\Tests\Integration\IntegrationTestCase;

class DateTimeHelperTest extends IntegrationTestCase
{
    private Translator $translator;
    private DateTimeZone $timezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = Translator::getInstance();
        $this->timezone = new DateTimeZone('Europe/Berlin');
    }

    private function tsAgo(int $years = 0, int $months = 0, int $days = 0, int $hours = 0, int $minutes = 0): string
    {
        $dt = new DateTime('now', $this->timezone);
        if ($years)   $dt->modify("-{$years} years");
        if ($months)  $dt->modify("-{$months} months");
        if ($days)    $dt->modify("-{$days} days");
        if ($hours)   $dt->modify("-{$hours} hours");
        if ($minutes) $dt->modify("-{$minutes} minutes");
        return $dt->format('Y-m-d H:i:s');
    }

    // --- German ---

    public function testDeJustNow(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('Gerade eben', DateTimeHelper::ago($this->tsAgo()));
    }

    public function testDeMinutesSingular(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('1 Min.', DateTimeHelper::ago($this->tsAgo(minutes: 1)));
    }

    public function testDeMinutesPlural(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('5 Min.', DateTimeHelper::ago($this->tsAgo(minutes: 5)));
    }

    public function testDeHours(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('2 Std.', DateTimeHelper::ago($this->tsAgo(hours: 2)));
    }

    public function testDeDaysSingular(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('1 Tag', DateTimeHelper::ago($this->tsAgo(days: 1)));
    }

    public function testDeDaysPlural(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('3 Tage', DateTimeHelper::ago($this->tsAgo(days: 3)));
    }

    public function testDeMonthsSingular(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('1 Monat', DateTimeHelper::ago($this->tsAgo(months: 1)));
    }

    public function testDeMonthsPlural(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('2 Monate', DateTimeHelper::ago($this->tsAgo(months: 2)));
    }

    public function testDeYearsSingular(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('1 Jahr', DateTimeHelper::ago($this->tsAgo(years: 1)));
    }

    public function testDeYearsPlural(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('2 Jahre', DateTimeHelper::ago($this->tsAgo(years: 2)));
    }

    public function testDeCombined(): void
    {
        $this->translator->setLocale('de');
        $this->assertEquals('1 Jahr 2 Monate', DateTimeHelper::ago($this->tsAgo(years: 1, months: 2)));
    }

    // --- English ---

    public function testEnJustNow(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('Just now', DateTimeHelper::ago($this->tsAgo()));
    }

    public function testEnMinutesSingular(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('1 min.', DateTimeHelper::ago($this->tsAgo(minutes: 1)));
    }

    public function testEnMinutesPlural(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('5 min.', DateTimeHelper::ago($this->tsAgo(minutes: 5)));
    }

    public function testEnHours(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('2 hr.', DateTimeHelper::ago($this->tsAgo(hours: 2)));
    }

    public function testEnDaysSingular(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('1 day', DateTimeHelper::ago($this->tsAgo(days: 1)));
    }

    public function testEnDaysPlural(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('3 days', DateTimeHelper::ago($this->tsAgo(days: 3)));
    }

    public function testEnMonthsSingular(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('1 month', DateTimeHelper::ago($this->tsAgo(months: 1)));
    }

    public function testEnMonthsPlural(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('2 months', DateTimeHelper::ago($this->tsAgo(months: 2)));
    }

    public function testEnYearsSingular(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('1 year', DateTimeHelper::ago($this->tsAgo(years: 1)));
    }

    public function testEnYearsPlural(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('2 years', DateTimeHelper::ago($this->tsAgo(years: 2)));
    }

    public function testEnCombined(): void
    {
        $this->translator->setLocale('en');
        $this->assertEquals('1 year 2 months', DateTimeHelper::ago($this->tsAgo(years: 1, months: 2)));
    }

    // --- Spanish ---

    public function testEsJustNow(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('Justo ahora', DateTimeHelper::ago($this->tsAgo()));
    }

    public function testEsMinutesSingular(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('1 min.', DateTimeHelper::ago($this->tsAgo(minutes: 1)));
    }

    public function testEsMinutesPlural(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('5 min.', DateTimeHelper::ago($this->tsAgo(minutes: 5)));
    }

    public function testEsHours(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('2 h.', DateTimeHelper::ago($this->tsAgo(hours: 2)));
    }

    public function testEsDaysSingular(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('1 día', DateTimeHelper::ago($this->tsAgo(days: 1)));
    }

    public function testEsDaysPlural(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('3 días', DateTimeHelper::ago($this->tsAgo(days: 3)));
    }

    public function testEsMonthsSingular(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('1 mes', DateTimeHelper::ago($this->tsAgo(months: 1)));
    }

    public function testEsMonthsPlural(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('2 meses', DateTimeHelper::ago($this->tsAgo(months: 2)));
    }

    public function testEsYearsSingular(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('1 año', DateTimeHelper::ago($this->tsAgo(years: 1)));
    }

    public function testEsYearsPlural(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('2 años', DateTimeHelper::ago($this->tsAgo(years: 2)));
    }

    public function testEsCombined(): void
    {
        $this->translator->setLocale('es');
        $this->assertEquals('1 año 2 meses', DateTimeHelper::ago($this->tsAgo(years: 1, months: 2)));
    }
}
