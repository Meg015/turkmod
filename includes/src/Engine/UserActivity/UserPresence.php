<?php

declare(strict_types=1);

namespace App\Engine\UserActivity;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

final class UserPresence
{
    public const ONLINE_WINDOW_SECONDS = 300;

    public const WRITE_THROTTLE_SECONDS = 60;

    private Closure $timeResolver;

    private DateTimeZone $timezone;

    public function __construct(?callable $timeResolver = null, ?DateTimeZone $timezone = null)
    {
        $this->timeResolver = $timeResolver !== null
            ? Closure::fromCallable($timeResolver)
            : static fn (): int => time();
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
    }

    /**
     * @return array{is_online:bool,status_label:string,relative_label:string,exact_label:string,has_activity:bool,state_class:string,title_label:string}
     */
    public function describe(?string $lastActivityAt): array
    {
        $rawValue = trim((string) $lastActivityAt);
        $now = (int) ($this->timeResolver)();
        $activityTimestamp = false;
        if ($rawValue !== '' && !str_starts_with($rawValue, '0000-00-00')) {
            try {
                $activityTimestamp = (new DateTimeImmutable($rawValue, $this->timezone))->getTimestamp();
            } catch (\Throwable) {
                $activityTimestamp = false;
            }
        }

        if ($activityTimestamp === false || $activityTimestamp > $now) {
            return [
                'is_online' => false,
                'status_label' => 'Çevrimdışı',
                'relative_label' => 'Bilinmiyor',
                'exact_label' => '',
                'has_activity' => false,
                'state_class' => 'is-offline',
                'title_label' => 'Çevrimdışı · Son etkinlik bilinmiyor',
            ];
        }

        $ageSeconds = max(0, $now - $activityTimestamp);
        $isOnline = $ageSeconds < self::ONLINE_WINDOW_SECONDS;
        $exactLabel = $this->dateAt($activityTimestamp)->format('d.m.Y H:i');

        return [
            'is_online' => $isOnline,
            'status_label' => $isOnline ? 'Çevrimiçi' : 'Çevrimdışı',
            'relative_label' => $isOnline ? 'Şimdi çevrimiçi' : $this->relativeLabel($activityTimestamp, $now),
            'exact_label' => $exactLabel,
            'has_activity' => true,
            'state_class' => $isOnline ? 'is-online' : 'is-offline',
            'title_label' => ($isOnline ? 'Çevrimiçi' : 'Çevrimdışı') . ' · Son etkinlik: ' . $exactLabel,
        ];
    }

    /**
     * Public presence never exposes an exact activity time.
     *
     * @return array{is_online:bool,status_label:string,relative_label:string,exact_label:string,has_activity:bool,state_class:string,title_label:string}
     */
    public function describePublic(?string $lastActivityAt): array
    {
        $description = $this->describe($lastActivityAt);
        $relative = (string) $description['relative_label'];
        if (preg_match('/^(\d{2}\.\d{2}\.\d{4})\s+\d{2}:\d{2}$/', $relative, $matches) === 1) {
            $relative = $matches[1];
        }

        $description['relative_label'] = $relative;
        $description['exact_label'] = '';
        $description['title_label'] = (string) $description['status_label'];

        return $description;
    }

    private function relativeLabel(int $activityTimestamp, int $now): string
    {
        $activityDate = $this->dateAt($activityTimestamp);
        $nowDate = $this->dateAt($now);
        $calendarDays = (int) $activityDate->setTime(0, 0)->diff($nowDate->setTime(0, 0))->days;
        $ageSeconds = max(0, $now - $activityTimestamp);

        if ($calendarDays === 0 && $ageSeconds < 3600) {
            return max(1, intdiv($ageSeconds, 60)) . ' dakika önce';
        }

        if ($calendarDays === 0) {
            return max(1, intdiv($ageSeconds, 3600)) . ' saat önce';
        }

        if ($calendarDays === 1) {
            return 'Dün ' . $activityDate->format('H:i');
        }

        if ($calendarDays <= 6) {
            return $calendarDays . ' gün önce';
        }

        return $activityDate->format('d.m.Y H:i');
    }

    private function dateAt(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($this->timezone);
    }
}
