<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

final class BulkEmailContentService
{
    private const ALLOWED_TOKENS = ['username', 'email', 'site_name', 'profile_link'];

    /** @return list<string> */
    public function allowedTokens(): array
    {
        return self::ALLOWED_TOKENS;
    }

    /** @return array{subject:string,body_html:string} */
    public function validateAndSanitize(string $subject, string $bodyHtml): array
    {
        $subject = trim(preg_replace('/[\r\n]+/u', ' ', $subject) ?? $subject);
        $bodyHtml = trim($bodyHtml);
        if ($subject === '' || $bodyHtml === '' || trim(strip_tags($bodyHtml)) === '') {
            throw new RuntimeException('E-posta konusu ve icerigi bos birakilamaz.');
        }
        if (mb_strlen($subject, 'UTF-8') > 255) {
            throw new RuntimeException('E-posta konusu en fazla 255 karakter olabilir.');
        }
        if (strlen($bodyHtml) > 300000) {
            throw new RuntimeException('E-posta icerigi izin verilen boyutu asiyor.');
        }
        if (preg_match('~<(?:iframe|video|audio|object|embed)\b|\bql-video\b~i', $bodyHtml) === 1) {
            throw new RuntimeException('Video ve gomulu medya e-posta istemcilerinde desteklenmedigi icin kaldirilmalidir.');
        }

        $unknown = array_values(array_diff($this->extractTokens($subject . "\n" . $bodyHtml), self::ALLOWED_TOKENS));
        if ($unknown !== []) {
            throw new RuntimeException('Bilinmeyen kisisellestirme degiskeni: {{' . implode('}}, {{', $unknown) . '}}');
        }

        $sanitized = $this->sanitizeHtml($bodyHtml);
        if ($sanitized === '' || trim(strip_tags($sanitized)) === '') {
            throw new RuntimeException('E-posta icerigi guvenli bicimlendirme sonrasinda bos kaldi.');
        }

        return ['subject' => $subject, 'body_html' => $sanitized];
    }

    /** @return array{subject:string,html:string,plain:string} */
    public function render(string $subjectTemplate, string $bodyHtmlTemplate, array $payload): array
    {
        $validated = $this->validateAndSanitize($subjectTemplate, $bodyHtmlTemplate);
        $payload = $this->normalizePayload($payload);
        $subject = $this->replaceTokens($validated['subject'], $payload, false);
        $contentHtml = $this->replaceTokens($validated['body_html'], $payload, true);
        $contentHtml = $this->absoluteResourceUrls($contentHtml);
        $plain = function_exists('appMailTextFromHtml') ? appMailTextFromHtml($contentHtml) : trim(strip_tags($contentHtml));
        $preheader = mb_substr(trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain), 0, 180, 'UTF-8');

        if (function_exists('appRenderMailLayout')) {
            $html = appRenderMailLayout([
                'site_name' => $payload['site_name'],
                'eyebrow' => 'Uye Duyurusu',
                'title' => $subject,
                'preheader' => $preheader,
                'content_html' => $contentHtml,
                'footer_note' => 'Bu e-posta ' . $payload['site_name'] . ' yonetimi tarafindan gonderilmistir.',
            ]);
        } else {
            $html = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>' . $contentHtml . '</body></html>';
        }

        return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
    }

    /** @return array{username:string,email:string,site_name:string,profile_link:string} */
    public function samplePayload(array $user = []): array
    {
        $username = trim((string) ($user['username'] ?? 'Ornek Uye'));
        $email = trim((string) ($user['email'] ?? 'uye@example.com'));

        return $this->normalizePayload([
            'username' => $username !== '' ? $username : 'Ornek Uye',
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'uye@example.com',
            'site_name' => $this->siteName(),
            'profile_link' => $this->profileUrl((int) ($user['id'] ?? 1001), $username !== '' ? $username : 'Ornek Uye'),
        ]);
    }

    /** @return array{username:string,email:string,site_name:string,profile_link:string} */
    public function recipientPayload(array $recipient): array
    {
        $username = trim((string) ($recipient['recipient_username'] ?? $recipient['username'] ?? 'Uye'));
        $email = trim((string) ($recipient['recipient_email'] ?? $recipient['email'] ?? ''));

        return $this->normalizePayload([
            'username' => $username !== '' ? $username : 'Uye',
            'email' => $email,
            'site_name' => $this->siteName(),
            'profile_link' => $this->profileUrl((int) ($recipient['user_id'] ?? 0), $username),
        ]);
    }

    public function send(string $recipientEmail, string $subjectTemplate, string $bodyHtmlTemplate, array $payload, array $logContext = []): bool
    {
        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Gecerli bir e-posta adresi girin.');
        }

        $message = $this->render($subjectTemplate, $bodyHtmlTemplate, $payload);
        if (!function_exists('appSendMail')) {
            throw new RuntimeException('E-posta gonderim servisi kullanilabilir degil.');
        }

        return appSendMail($recipientEmail, $message['subject'], $message['html'], [
            'plain_text' => $message['plain'],
            'email_log' => array_merge([
                'source' => 'bulk_email',
                'source_key' => 'campaign',
                'recipient_email' => $recipientEmail,
            ], $logContext),
        ]);
    }

    /** @return list<string> */
    private function extractTokens(string $value): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $value, $matches);
        return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    }

    /** @return array{username:string,email:string,site_name:string,profile_link:string} */
    private function normalizePayload(array $payload): array
    {
        $normalized = [];
        foreach (self::ALLOWED_TOKENS as $token) {
            $value = $payload[$token] ?? '';
            $normalized[$token] = is_scalar($value) || $value === null ? (string) $value : '';
        }
        if ($normalized['site_name'] === '') {
            $normalized['site_name'] = $this->siteName();
        }

        return $normalized;
    }

    private function replaceTokens(string $template, array $payload, bool $html): string
    {
        return (string) preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $matches) use ($payload, $html): string {
            $value = (string) ($payload[$matches[1]] ?? '');
            return $html ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $template);
    }

    private function sanitizeHtml(string $html): string
    {
        $allowedTags = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'img', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'code', 'pre', 'div', 'span'];
        $allowedAttributes = [
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'p' => ['class', 'style'], 'div' => ['class', 'style'], 'span' => ['class', 'style'],
            'h1' => ['class', 'style'], 'h2' => ['class', 'style'], 'h3' => ['class', 'style'], 'h4' => ['class', 'style'],
            'ul' => ['class', 'style'], 'ol' => ['class', 'style'], 'li' => ['class', 'style'],
            'blockquote' => ['class', 'style'], 'pre' => ['class', 'style'], 'code' => ['class'],
        ];

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="bulk-email-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('bulk-email-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        $walk = function (DOMNode $node) use (&$walk, $doc, $allowedTags, $allowedAttributes): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'video', 'audio', 'object', 'embed', 'form', 'input', 'button'], true)) {
                        $child->parentNode?->removeChild($child);
                        continue;
                    }
                    $parent = $child->parentNode;
                    if ($parent) {
                        while ($child->firstChild) {
                            $parent->insertBefore($child->firstChild, $child);
                        }
                        $parent->removeChild($child);
                    }
                    continue;
                }

                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = trim($attribute->value);
                    if (str_starts_with($name, 'on') || !in_array($name, $allowedAttributes[$tag] ?? [], true)) {
                        $child->removeAttribute($attribute->name);
                        continue;
                    }
                    if ($name === 'href' || $name === 'src') {
                        if (!$this->isSafeUrl($value, $name === 'href')) {
                            $child->removeAttribute($attribute->name);
                            continue;
                        }
                    } elseif ($name === 'style') {
                        $style = $this->sanitizeStyle($value);
                        $style === '' ? $child->removeAttribute('style') : $child->setAttribute('style', $style);
                    } elseif ($name === 'class') {
                        $class = $this->sanitizeClass($value);
                        $class === '' ? $child->removeAttribute('class') : $child->setAttribute('class', $class);
                    } elseif (($name === 'width' || $name === 'height') && preg_match('/^\d{1,4}$/', $value) !== 1) {
                        $child->removeAttribute($name);
                    }
                }
                if ($tag === 'a') {
                    if ($child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    } else {
                        $child->removeAttribute('target');
                    }
                }
                $walk($child);
            }
        };
        $walk($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
        return trim($output);
    }

    private function isSafeUrl(string $value, bool $allowMailto): bool
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('~^(?:javascript|data|vbscript):~i', $value) === 1 || str_starts_with($value, '//')) {
            return false;
        }
        if (str_starts_with($value, '/') || str_starts_with($value, './') || str_starts_with($value, '../') || str_starts_with($value, '#') || str_contains($value, '{{')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'], true);
    }

    private function sanitizeStyle(string $style): string
    {
        $allowed = ['text-align', 'text-decoration', 'font-weight', 'font-style', 'color', 'background-color', 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'line-height'];
        $clean = [];
        foreach (array_filter(array_map('trim', explode(';', $style))) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (in_array($property, $allowed, true) && preg_match('/(?:expression|url|javascript|@import)/i', $value) !== 1) {
                $clean[] = $property . ':' . $value;
            }
        }
        return implode(';', $clean);
    }

    private function sanitizeClass(string $class): string
    {
        $clean = [];
        foreach (preg_split('/\s+/', trim($class)) ?: [] as $candidate) {
            if (preg_match('/^ql-(?:align-(?:left|center|right|justify)|indent-[1-8]|syntax)$/', strtolower($candidate)) === 1) {
                $clean[] = strtolower($candidate);
            }
        }
        return implode(' ', array_unique($clean));
    }

    private function absoluteResourceUrls(string $html): string
    {
        $base = function_exists('appPublicBaseUrl') ? rtrim((string) appPublicBaseUrl(true), '/') : '';
        if ($base === '') {
            return $html;
        }

        return (string) preg_replace_callback('~\b(href|src)=("|\')(.*?)\2~i', function (array $matches): string {
            $url = html_entity_decode((string) $matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || str_starts_with($url, '#') || preg_match('~^(?:https?://|mailto:)~i', $url) === 1) {
                return $matches[0];
            }
            $absolute = (new NotificationEmailQueueService())->absoluteLink($url) ?? $url;
            return $matches[1] . '=' . $matches[2] . htmlspecialchars($absolute, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $matches[2];
        }, $html);
    }

    private function siteName(): string
    {
        $settings = function_exists('getAdminSettings') ? (array) getAdminSettings($GLOBALS['pdo'] ?? null) : [];
        $name = trim((string) ($settings['site_name'] ?? 'Turk Mod'));
        return $name !== '' ? $name : 'Turk Mod';
    }

    private function profileUrl(int $userId, string $username): string
    {
        $relative = function_exists('publicProfileUrl') && $userId > 0
            ? (string) publicProfileUrl(['id' => $userId, 'username' => $username, 'name' => $username])
            : '/profil';
        if (preg_match('~^https?://~i', $relative) === 1) {
            return $relative;
        }
        return (new NotificationEmailQueueService())->absoluteLink($relative) ?? $relative;
    }
}
