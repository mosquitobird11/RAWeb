<?php

namespace LegacyApp\Community\Enums;

abstract class AwardType
{
    public const Mastery = 1;

    public const AchievementUnlocksYield = 2;

    public const AchievementPointsYield = 3;

    // public const Referrals = 4;

    // public const FacebookConnect = 5;

    public const PatreonSupporter = 6;

    public const CertifiedLegend = 7;

    public static function cases(): array
    {
        return [
            self::Mastery,
            self::AchievementUnlocksYield,
            self::AchievementPointsYield,
            self::PatreonSupporter,
            self::CertifiedLegend,
        ];
    }

    public static function isActive(int $value): bool
    {
        return in_array($value, self::cases());
    }
}