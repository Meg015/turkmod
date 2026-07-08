<?php

declare(strict_types=1);

namespace App\Engine\Users;

use Closure;

final class ProfilePresentation
{
    private Closure $avatarResolver;

    private Closure $defaultAvatarResolver;

    private Closure $initialsResolver;

    private Closure $externalUrlSanitizer;

    private Closure $timeResolver;

    private bool $hasCustomAvatarResolver;

    public function __construct(
        ?callable $avatarResolver = null,
        ?callable $defaultAvatarResolver = null,
        ?callable $initialsResolver = null,
        ?callable $externalUrlSanitizer = null,
        ?callable $timeResolver = null,
    ) {
        $this->hasCustomAvatarResolver = $avatarResolver !== null;
        $this->avatarResolver = $avatarResolver !== null
            ? Closure::fromCallable($avatarResolver)
            : self::defaultAvatarResolver();
        $this->defaultAvatarResolver = $defaultAvatarResolver !== null
            ? Closure::fromCallable($defaultAvatarResolver)
            : self::defaultAvatarFallbackResolver();
        $this->initialsResolver = $initialsResolver !== null
            ? Closure::fromCallable($initialsResolver)
            : self::defaultInitialsResolver();
        $this->externalUrlSanitizer = $externalUrlSanitizer !== null
            ? Closure::fromCallable($externalUrlSanitizer)
            : self::defaultExternalUrlSanitizer();
        $this->timeResolver = $timeResolver !== null
            ? Closure::fromCallable($timeResolver)
            : static fn (): int => time();
    }

    public function groupBadge(string $groupSlug, string $groupName): string
    {
        $slug = strtolower((string) (preg_replace('/[^a-z0-9_-]/i', '', $groupSlug) ?: 'member'));
        $label = trim($groupName) !== '' ? $groupName : 'Kullanıcı Grubu';
        $slugAttr = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');

        return '<span class="badge profile-role-badge profile-group-badge profile-role-badge--' . $slugAttr . ' profile-group-badge--' . $slugAttr . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public function avatarUrl(string $baseUri, ?string $avatar): string
    {
        return (string) ($this->avatarResolver)($avatar, $baseUri);
    }

    public function initials(string $name): string
    {
        return (string) ($this->initialsResolver)($name);
    }

    public function memberSince(string $createdAt): string
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return date('d.m.Y');
        }

        return date('d.m.Y', $ts);
    }

    public function tenureLabel(string $createdAt): string
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return '';
        }

        $days = max(0, (int) floor(((int) ($this->timeResolver)() - $ts) / 86400));
        if ($days >= 365) {
            return (string) floor($days / 365) . ' yıl';
        }

        if ($days >= 30) {
            return (string) floor($days / 30) . ' ay';
        }

        if ($days > 0) {
            return (string) $days . ' gün';
        }

        return 'Bugün';
    }

    public function avatarFallbackUrl(string $baseUri): string
    {
        return (string) ($this->defaultAvatarResolver)($baseUri);
    }

    /**
     * @param array<string,mixed> $user
     */
    public function groupName(array $user, string $fallback = 'Kullanıcı Grubu'): string
    {
        $groupName = trim((string) ($user['group_name'] ?? $user['role_name'] ?? ''));
        return $groupName !== '' ? $groupName : $fallback;
    }

    /**
     * @param array<string,mixed> $user
     */
    public function groupSlug(array $user, string $fallback = 'member'): string
    {
        $groupSlug = trim((string) ($user['group_slug'] ?? $user['role_slug'] ?? ''));
        $groupSlug = strtolower((string) (preg_replace('/[^a-z0-9_-]/i', '', $groupSlug) ?: ''));

        return $groupSlug !== '' ? $groupSlug : $fallback;
    }

    /**
     * @param array<string,mixed> $user
     */
    public function statusLabel(array $user): string
    {
        if (!empty($user['is_banned'])) {
            return 'Banli';
        }

        if (($user['status'] ?? 'active') === 'active') {
            return 'Aktif';
        }

        return 'Devre Disi';
    }

    /**
     * @param array<string,mixed> $user
     */
    public function statusBadgeClass(array $user): string
    {
        if (!empty($user['is_banned'])) {
            return 'badge-danger';
        }

        if (($user['status'] ?? 'active') === 'active') {
            return 'badge-success';
        }

        return 'badge-secondary';
    }

    /**
     * @param array<int|string,mixed> $definitions
     * @param array<int,string> $defaultClasses
     * @return array<int,array<string,string>>
     */
    public function statCards(array $definitions, array $defaultClasses = ['stat-info', '', 'stat-success', 'stat-warning']): array
    {
        $cards = [];
        foreach (array_values($definitions) as $index => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $cards[] = [
                'class' => (string) ($definition['class'] ?? $defaultClasses[$index] ?? ''),
                'icon' => (string) ($definition['icon'] ?? 'bi-bar-chart'),
                'value' => $this->formatStatValue($definition['value'] ?? 0),
                'label' => (string) ($definition['label'] ?? ''),
            ];
        }

        return $cards;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function profileContext(array $user, array $options = []): array
    {
        $base = $this->sidebarData($user, $options);
        $username = (string) ($base['username'] ?? $options['username'] ?? $user['username'] ?? 'Kullanici');
        $groupName = (string) ($base['group_name'] ?? $this->groupName($user));
        $groupSlug = (string) ($base['group_slug'] ?? $this->groupSlug($user));
        $avatar = (string) ($base['avatar'] ?? '');
        $location = trim((string) ($base['location'] ?? ($options['location'] ?? $user['location'] ?? '')));

        return array_merge($base, [
            'id' => (int) ($user['id'] ?? $options['id'] ?? 0),
            'email' => (string) ($options['email'] ?? ($user['email'] ?? '')),
            'website' => (string) ($options['website'] ?? ($user['website'] ?? '')),
            'social_github' => (string) ($options['social_github'] ?? ($user['social_github'] ?? '')),
            'social_twitter' => (string) ($options['social_twitter'] ?? ($user['social_twitter'] ?? '')),
            'social_discord' => (string) ($options['social_discord'] ?? ($user['social_discord'] ?? '')),
            'cover' => (string) ($options['cover'] ?? ($user['cover'] ?? $user['cover_image'] ?? '')),
            'avatar_url' => $avatar,
            'has_avatar' => !empty($base['has_avatar']),
            'username' => $username,
            'name' => $username,
            'initials' => (string) ($base['initials'] ?? $this->initials($username)),
            'group' => $groupName,
            'group_name' => $groupName,
            'group_slug' => $groupSlug,
            'role_name' => $groupName,
            'private_group' => $groupName,
            'private_role' => $groupName,
            'location' => $location,
            'has_location' => !empty($base['has_location']) || $location !== '',
            'has_social_links' => !empty($base['social_links']),
            'status_label' => $this->statusLabel($user),
            'status_badge_class' => $this->statusBadgeClass($user),
        ]);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<int,array{icon:string,url:string,title:string}>
     */
    public function socialLinks(array $user): array
    {
        $links = [];

        $website = trim((string) ($user['website'] ?? ''));
        if ($website !== '') {
            $links[] = [
                'icon' => 'bi-globe2',
                'url' => (string) ($this->externalUrlSanitizer)($website),
                'title' => 'Web Sitesi',
            ];
        }

        $github = trim((string) ($user['social_github'] ?? ''));
        if ($github !== '') {
            $links[] = [
                'icon' => 'bi-github',
                'url' => 'https://github.com/' . rawurlencode($github),
                'title' => 'GitHub',
            ];
        }

        $twitter = trim((string) ($user['social_twitter'] ?? ''));
        if ($twitter !== '') {
            $links[] = [
                'icon' => 'bi-twitter-x',
                'url' => 'https://x.com/' . rawurlencode($twitter),
                'title' => 'X / Twitter',
            ];
        }

        $discord = trim((string) ($user['social_discord'] ?? ''));
        if ($discord !== '') {
            $links[] = [
                'icon' => 'bi-discord',
                'url' => '#',
                'title' => 'Discord: ' . $discord,
            ];
        }

        return $links;
    }

    /**
     * @param array<string,string> $labels
     * @return array<int,array<string,string>>
     */
    public function sidebarStats(
        int $topicCount,
        int|string $commentCount,
        int $viewCount,
        int $downloadCount,
        array $labels = []
    ): array {
        $labels = array_merge([
            'topics' => 'Konu',
            'comments' => 'Yorum',
            'views' => 'Görüntülenme',
            'downloads' => 'İndirme',
        ], array_intersect_key($labels, [
            'topics' => true,
            'comments' => true,
            'views' => true,
            'downloads' => true,
        ]));

        return $this->statCards([
            [
                'class' => 'stat-info',
                'icon' => 'bi-file-earmark-text',
                'value' => $topicCount,
                'label' => $labels['topics'],
            ],
            [
                'class' => '',
                'icon' => 'bi-chat-dots',
                'value' => $commentCount,
                'label' => $labels['comments'],
            ],
            [
                'class' => 'stat-success',
                'icon' => 'bi-eye',
                'value' => $viewCount,
                'label' => $labels['views'],
            ],
            [
                'class' => 'stat-warning',
                'icon' => 'bi-download',
                'value' => $downloadCount,
                'label' => $labels['downloads'],
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function sidebarData(array $user, array $options = []): array
    {
        $baseUri = rtrim((string) ($options['base_uri'] ?? ($GLOBALS['baseUri'] ?? '')), '/');
        $avatarFallback = (string) ($options['avatar_fallback'] ?? $this->avatarFallbackUrl($baseUri));
        $avatar = trim((string) ($options['avatar'] ?? ''));
        if ($avatar === '') {
            $avatar = $this->avatarUrl($baseUri, isset($user['avatar']) ? (string) $user['avatar'] : null);
        } elseif ($this->hasCustomAvatarResolver || \function_exists('resolveAvatarUrl')) {
            $avatar = $this->avatarUrl($baseUri, $avatar);
        }
        if ($avatar === '') {
            $avatar = $avatarFallback;
        }

        $groupSlug = (string) ($options['group_slug'] ?? $this->groupSlug($user));
        $groupName = (string) ($options['group_name'] ?? $this->groupName($user));
        $bio = trim((string) ($options['bio'] ?? ($user['bio'] ?? '')));
        $createdAt = (string) ($options['created_at'] ?? ($user['created_at'] ?? date('Y-m-d')));
        $location = trim((string) ($options['location'] ?? ($user['location'] ?? '')));

        if (isset($options['stats']) && is_array($options['stats'])) {
            $stats = array_values(array_filter($options['stats'], static fn ($item): bool => is_array($item)));
        } else {
            $stats = $this->sidebarStats(
                (int) ($options['topic_count'] ?? 0),
                $options['comment_count'] ?? 0,
                (int) ($options['view_count'] ?? 0),
                (int) ($options['download_count'] ?? 0),
                isset($options['stat_labels']) && is_array($options['stat_labels']) ? $options['stat_labels'] : []
            );
        }
        $username = (string) ($user['username'] ?? $options['username'] ?? 'Kullanici');

        return [
            'username' => $username,
            'name' => $username,
            'avatar' => $avatar,
            'avatar_fallback' => $avatarFallback,
            'has_avatar' => $avatar !== '' && $avatar !== $avatarFallback,
            'initials' => $this->initials($username),
            'group' => $groupName,
            'group_name' => $groupName,
            'group_slug' => $groupSlug,
            'group_badge_html' => $this->groupBadge($groupSlug, $groupName),
            'role_name' => $groupName,
            'private_group' => $groupName,
            'private_role' => $groupName,
            'bio' => $bio,
            'created_at' => $createdAt,
            'member_since' => $this->memberSince($createdAt),
            'tenure' => $this->tenureLabel($createdAt),
            'location' => $location,
            'has_location' => $location !== '',
            'website' => (string) ($options['website'] ?? ($user['website'] ?? '')),
            'social_github' => (string) ($options['social_github'] ?? ($user['social_github'] ?? '')),
            'social_twitter' => (string) ($options['social_twitter'] ?? ($user['social_twitter'] ?? '')),
            'social_discord' => (string) ($options['social_discord'] ?? ($user['social_discord'] ?? '')),
            'social_links' => isset($options['social_links']) && is_array($options['social_links'])
                ? array_values(array_filter($options['social_links'], static fn ($item): bool => is_array($item)))
                : $this->socialLinks($user),
            'stats' => $stats,
            'can_report' => !empty($options['can_report']),
            'show_leaderboard' => !empty($options['show_leaderboard']),
            'leaderboard_user_id' => (int) ($options['leaderboard_user_id'] ?? ($user['id'] ?? 0)),
        ];
    }

    private function formatStatValue(mixed $value): string
    {
        if (is_int($value)) {
            return number_format($value, 0, ',', '.');
        }

        $value = trim((string) $value);
        if ($value !== '' && is_numeric($value)) {
            return number_format((int) $value, 0, ',', '.');
        }

        return $value;
    }

    private static function defaultAvatarResolver(): Closure
    {
        return static function (?string $avatar, string $baseUri): string {
            if (\function_exists('resolveAvatarUrl')) {
                return (string) \resolveAvatarUrl($avatar, $baseUri, true);
            }

            if ($avatar !== null && $avatar !== '') {
                return rtrim($baseUri, '/') . '/' . ltrim($avatar, '/');
            }

            return rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
        };
    }

    private static function defaultAvatarFallbackResolver(): Closure
    {
        return static function (string $baseUri): string {
            return \function_exists('defaultAvatarUrl')
                ? (string) \defaultAvatarUrl($baseUri)
                : rtrim($baseUri, '/') . '/assets/images/noavatar-neon-helmet.svg';
        };
    }

    private static function defaultInitialsResolver(): Closure
    {
        return static function (string $name): string {
            if (\function_exists('avatarInitials')) {
                return (string) \avatarInitials($name);
            }

            $parts = explode(' ', trim($name));
            $initials = '';
            foreach ($parts as $part) {
                if ($part !== '') {
                    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                }
                if (mb_strlen($initials) >= 2) {
                    break;
                }
            }

            return $initials !== '' ? $initials : '?';
        };
    }

    private static function defaultExternalUrlSanitizer(): Closure
    {
        return static function (string $url): string {
            return \function_exists('safeExternalUrl') ? (string) \safeExternalUrl($url) : $url;
        };
    }
}
