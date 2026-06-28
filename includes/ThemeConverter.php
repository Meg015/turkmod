<?php

declare(strict_types=1);

final class ThemeConverter
{
    /**
     * DLE-like aliases. This intentionally stays conservative; converted files
     * should become normal TurkMod TPL files instead of carrying runtime hacks.
     *
     * @return array<string, string>
     */
    public static function dleAliasMap(): array
    {
        return [
            'THEME' => 'theme_url',
            'theme' => 'theme_url',
            'title' => 'page_title',
            'news-title' => 'topic.title',
            'full-title' => 'topic.title',
            'short-title' => 'topic.title',
            'description' => 'meta_description',
            'content' => 'content',
            'headers' => 'head',
            'AJAX' => 'scripts',
            'login' => 'login_url',
            'registration-link' => 'register_url',
            'profile-link' => 'profile_url',
            'logout-link' => 'logout_url',
            'speedbar' => 'breadcrumbs_html',
            'full-link' => 'topic.url',
            'link' => 'topic.url',
            'category' => 'topic.category',
            'category-url' => 'topic.category_url',
            'date' => 'topic.date',
            'short-story' => 'topic.excerpt',
            'full-story' => 'content',
            'image-1' => 'topic.image',
            'image-2' => 'topic.image_2',
            'image-3' => 'topic.image_3',
            'alt-name' => 'topic.slug',
            'id' => 'topic.id',
            'views' => 'topic.views',
            'comments-num' => 'topic.comments_count',
            'favorites-count' => 'topic.favorites_count',
            'rating' => 'topic.rating',
            'author' => 'topic.author',
            'author-url' => 'topic.author_url',
            'login-link' => 'login_url',
            'lostpassword-link' => 'forgot_password_url',
            'addnews-link' => 'upload_topic_url',
        ];
    }

    public static function convertDleTemplate(string $content): string
    {
        $content = self::stripExecutableCode($content);

        foreach (self::dleAliasMap() as $source => $target) {
            $content = str_replace('{' . $source . '}', '{' . $target . '}', $content);
        }

        $content = self::convertDleBlocks($content);

        $content = str_replace(['[full-link]', '[/full-link]'], ['{topic.url}', ''], $content);
        $content = preg_replace_callback('/\[xfvalue_([a-zA-Z0-9_-]+)\]/', static function (array $matches): string {
            return '{xfield.' . $matches[1] . '}';
        }, $content) ?? $content;

        return trim($content) . "\n";
    }

    private static function convertDleBlocks(string $content): string
    {
        $content = preg_replace('/\[not-logged\](.*?)\[\/not-logged\]/is', '{if !logged_in}$1{/if}', $content) ?? $content;
        $content = preg_replace('/\[logged\](.*?)\[\/logged\]/is', '{if logged_in}$1{/if}', $content) ?? $content;

        $pageMap = [
            'main' => 'page_is_home',
            'cat' => 'page_is_category',
            'showfull' => 'page_is_topic',
            'userinfo' => 'page_is_profile',
            'register' => 'page_is_register',
            'lostpassword' => 'page_is_forgot_password',
            'addnews' => 'page_is_upload_topic',
            'search' => 'page_is_search',
            'static' => 'page_is_static',
        ];

        $content = preg_replace_callback('/\[(not-)?av(?:ail|i)able=([a-z0-9_,|-]+)\](.*?)\[\/(?:not-)?av(?:ail|i)able\]/is', static function (array $matches) use ($pageMap): string {
            $negated = ((string) ($matches[1] ?? '')) !== '';
            $pages = preg_split('/[,|]/', strtolower((string) ($matches[2] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $body = (string) ($matches[3] ?? '');
            $vars = [];

            foreach ($pages as $page) {
                $page = trim($page);
                if (isset($pageMap[$page])) {
                    $vars[] = $pageMap[$page];
                }
            }

            if ($vars === []) {
                return $body;
            }

            $wrapped = $body;
            foreach (array_reverse(array_unique($vars)) as $var) {
                $wrapped = '{if ' . ($negated ? '!' : '') . $var . '}' . $wrapped . '{/if}';
            }

            return $wrapped;
        }, $content) ?? $content;

        $content = preg_replace_callback('/\[(not-)?group=([0-9,]+)\](.*?)\[\/(?:not-)?group\]/is', static function (array $matches): string {
            $negated = ((string) ($matches[1] ?? '')) !== '';
            return '{if ' . ($negated ? '!' : '') . 'user_in_group}' . (string) ($matches[3] ?? '') . '{/if}';
        }, $content) ?? $content;

        return $content;
    }

    public static function stripExecutableCode(string $content): string
    {
        $content = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', '', $content) ?? $content;
        $content = preg_replace('/<script\b[^>]*>\s*<\?(?:php|=)?[\s\S]*?\?>\s*<\/script>/i', '', $content) ?? $content;

        return $content;
    }
}
