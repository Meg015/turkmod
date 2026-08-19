<?php

declare(strict_types=1);

namespace App\Modules\StaticPages\Services;

use App\Core\Database\SchemaInspector;
use PDO;
use Throwable;

final class StaticPageService
{
    private const STATUSES = ['draft', 'published', 'archived'];
    private ?bool $schemaReady = null;

    public function __construct(private PDO $pdo, private ?SchemaInspector $inspector = null)
    {
        $this->inspector ??= new SchemaInspector();
    }

    public function schemaReady(): bool
    {
        return $this->schemaReady ??= $this->inspector->tableExists($this->pdo, 'static_pages')
            && $this->inspector->tableExists($this->pdo, 'static_page_redirects');
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
    public function paginate(string $search = '', string $status = 'active', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $clauses = [];
        $params = [];
        if ($status === 'active') {
            $clauses[] = "status IN ('draft', 'published')";
        } elseif (in_array($status, self::STATUSES, true)) {
            $clauses[] = 'status = :status';
            $params['status'] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $clauses[] = '(title LIKE :search OR slug LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM static_pages' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $query = $this->pdo->prepare('SELECT * FROM static_pages' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return [
            'items' => array_values(array_filter($query->fetchAll(PDO::FETCH_ASSOC) ?: [], 'is_array')),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM static_pages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array{type:string,page:array<string,mixed>}|null */
    public function resolveRoute(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '' || !$this->schemaReady()) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM static_pages WHERE slug = :slug AND status = 'published' LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($page) && $this->hasVisibleBody((string) ($page['body_html'] ?? ''))) {
            return ['type' => 'page', 'page' => $page];
        }

        $redirect = $this->pdo->prepare("SELECT p.* FROM static_page_redirects r INNER JOIN static_pages p ON p.id = r.page_id WHERE r.old_slug = :slug AND p.status = 'published' LIMIT 1");
        $redirect->execute(['slug' => $slug]);
        $page = $redirect->fetch(PDO::FETCH_ASSOC);
        if (is_array($page) && $this->hasVisibleBody((string) ($page['body_html'] ?? ''))) {
            return ['type' => 'redirect', 'page' => $page];
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    public function footerPages(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, system_key, title, slug, footer_label, footer_order FROM static_pages WHERE status = 'published' AND show_in_footer = 1 AND TRIM(body_html) <> '' ORDER BY footer_order ASC, title ASC, id ASC");

        return $stmt ? array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'is_array')) : [];
    }

    /** @return array<string,mixed>|null */
    public function publishedSystemPage(string $systemKey): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM static_pages WHERE system_key = :system_key AND status = 'published' LIMIT 1");
        $stmt->execute(['system_key' => trim($systemKey)]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($page) && $this->hasVisibleBody((string) ($page['body_html'] ?? '')) ? $page : null;
    }

    /** @return array{type:string,title:string,slug:string}|null */
    public function slugConflict(string $slug): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }
        $page = $this->pdo->prepare('SELECT title, slug FROM static_pages WHERE slug = :slug LIMIT 1');
        $page->execute(['slug' => $slug]);
        $row = $page->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return ['type' => 'page', 'title' => (string) ($row['title'] ?? ''), 'slug' => (string) ($row['slug'] ?? $slug)];
        }
        $redirect = $this->pdo->prepare('SELECT p.title, r.old_slug AS slug FROM static_page_redirects r INNER JOIN static_pages p ON p.id = r.page_id WHERE r.old_slug = :slug LIMIT 1');
        $redirect->execute(['slug' => $slug]);
        $row = $redirect->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? ['type' => 'redirect', 'title' => (string) ($row['title'] ?? ''), 'slug' => (string) ($row['slug'] ?? $slug)]
            : null;
    }

    /** @return list<array<string,mixed>> */
    public function sitemapPages(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, title, slug, updated_at, published_at FROM static_pages WHERE status = 'published' AND sitemap_include = 1 AND robots_noindex = 0 AND TRIM(body_html) <> '' ORDER BY id ASC");

        return $stmt ? array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'is_array')) : [];
    }

    /** @param array<string,mixed> $input @return array{page:array<string,mixed>,redirect_created:bool} */
    public function save(array $input, ?int $id, int $adminId): array
    {
        $existing = $id !== null ? $this->find($id) : null;
        if ($id !== null && $existing === null) {
            throw new StaticPageValidationException(['page' => 'Sabit sayfa bulunamadı.']);
        }
        $data = $this->normalizeInput($input, $existing);
        $errors = $this->validate($data, $id);
        if ($errors !== []) {
            throw new StaticPageValidationException($errors);
        }

        $now = date('Y-m-d H:i:s');
        $redirectCreated = false;
        $this->pdo->beginTransaction();
        try {
            if ($existing === null) {
                $stmt = $this->pdo->prepare("INSERT INTO static_pages
                    (system_key, title, slug, body_html, status, seo_title, meta_description, og_image, robots_noindex, robots_nofollow, sitemap_include, show_in_footer, footer_label, footer_order, created_by_admin_id, updated_by_admin_id, published_at, archived_at, created_at, updated_at)
                    VALUES (NULL, :title, :slug, :body_html, :status, :seo_title, :meta_description, :og_image, :robots_noindex, :robots_nofollow, :sitemap_include, :show_in_footer, :footer_label, :footer_order, :created_by_admin_id, :updated_by_admin_id, :published_at, :archived_at, :created_at, :updated_at)");
                $stmt->execute($data + [
                    'created_by_admin_id' => $adminId > 0 ? $adminId : null,
                    'updated_by_admin_id' => $adminId > 0 ? $adminId : null,
                    'published_at' => $data['status'] === 'published' ? $now : null,
                    'archived_at' => $data['status'] === 'archived' ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $wasPublished = (string) ($existing['status'] ?? '') === 'published';
                $slugChanged = (string) ($existing['slug'] ?? '') !== $data['slug'];
                $publishedAt = $existing['published_at'] ?? null;
                if ($data['status'] === 'published' && empty($publishedAt)) {
                    $publishedAt = $now;
                }
                $stmt = $this->pdo->prepare("UPDATE static_pages SET
                    title = :title, slug = :slug, body_html = :body_html, status = :status,
                    seo_title = :seo_title, meta_description = :meta_description, og_image = :og_image,
                    robots_noindex = :robots_noindex, robots_nofollow = :robots_nofollow,
                    sitemap_include = :sitemap_include, show_in_footer = :show_in_footer,
                    footer_label = :footer_label, footer_order = :footer_order,
                    updated_by_admin_id = :updated_by_admin_id, published_at = :published_at,
                    archived_at = :archived_at, updated_at = :updated_at
                    WHERE id = :id");
                $stmt->execute($data + [
                    'updated_by_admin_id' => $adminId > 0 ? $adminId : null,
                    'published_at' => $publishedAt,
                    'archived_at' => $data['status'] === 'archived' ? ($existing['archived_at'] ?: $now) : null,
                    'updated_at' => $now,
                    'id' => $id,
                ]);
                if ($wasPublished && $slugChanged) {
                    $redirect = $this->pdo->prepare('INSERT INTO static_page_redirects (page_id, old_slug, created_by_admin_id, created_at) VALUES (:page_id, :old_slug, :admin_id, :created_at)');
                    $redirect->execute([
                        'page_id' => $id,
                        'old_slug' => (string) $existing['slug'],
                        'admin_id' => $adminId > 0 ? $adminId : null,
                        'created_at' => $now,
                    ]);
                    $redirectCreated = true;
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $this->invalidateSitemap();

        return ['page' => $this->find((int) $id) ?? [], 'redirect_created' => $redirectCreated];
    }

    public function archive(int $id, int $adminId): bool
    {
        return $this->setLifecycle($id, 'archived', $adminId);
    }

    public function restoreToDraft(int $id, int $adminId): bool
    {
        return $this->setLifecycle($id, 'draft', $adminId);
    }

    public function publicPath(string $slug): string
    {
        return rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/') . '/' . rawurlencode($this->normalizeSlug($slug));
    }

    public function normalizeSlug(string $value): string
    {
        $value = trim($value);
        $value = function_exists('slugify') ? (string) slugify($value) : strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $value));

        return trim(mb_substr($value, 0, 191, 'UTF-8'), '-');
    }

    /** @param array<string,mixed> $input @param array<string,mixed>|null $existing @return array<string,mixed> */
    private function normalizeInput(array $input, ?array $existing): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->normalizeSlug($slugInput !== '' ? $slugInput : $title);
        $body = (string) ($input['body_html'] ?? '');
        $body = function_exists('sanitizeTopicHtml') ? sanitizeTopicHtml($body) : strip_tags($body, '<p><br><strong><em><b><i><u><s><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><code><pre><hr><div><span>');
        $status = trim((string) ($input['status'] ?? ($existing['status'] ?? 'draft')));

        return [
            'title' => mb_substr($title, 0, 255, 'UTF-8'),
            'slug' => $slug,
            'body_html' => $body,
            'status' => $status,
            'seo_title' => mb_substr(trim((string) ($input['seo_title'] ?? '')), 0, 255, 'UTF-8'),
            'meta_description' => mb_substr(trim((string) ($input['meta_description'] ?? '')), 0, 500, 'UTF-8'),
            'og_image' => mb_substr(trim((string) ($input['og_image'] ?? '')), 0, 2048, 'UTF-8'),
            'robots_noindex' => $this->boolValue($input, 'robots_noindex'),
            'robots_nofollow' => $this->boolValue($input, 'robots_nofollow'),
            'sitemap_include' => $this->boolValue($input, 'sitemap_include'),
            'show_in_footer' => $this->boolValue($input, 'show_in_footer'),
            'footer_label' => mb_substr(trim((string) ($input['footer_label'] ?? '')), 0, 255, 'UTF-8'),
            'footer_order' => max(-9999, min(9999, (int) ($input['footer_order'] ?? 0))),
        ];
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    private function validate(array $data, ?int $id): array
    {
        $errors = [];
        if ($data['title'] === '') {
            $errors['title'] = 'Başlık zorunludur.';
        }
        if ($data['slug'] === '') {
            $errors['slug'] = 'Geçerli bir URL yolu zorunludur.';
        } elseif ($this->slugIsReserved((string) $data['slug'])) {
            $errors['slug'] = 'Bu URL yolu sistem tarafından kullanılıyor.';
        } elseif ($this->slugExists((string) $data['slug'], $id)) {
            $errors['slug'] = 'Bu URL yolu başka bir sayfa veya yönlendirme tarafından kullanılıyor.';
        }
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors['status'] = 'Geçersiz yayın durumu.';
        }
        if ($data['status'] === 'published' && !$this->hasVisibleBody((string) $data['body_html'])) {
            $errors['body_html'] = 'Yayınlamak için sayfa içeriği zorunludur.';
        }
        $ogImage = (string) $data['og_image'];
        if ($ogImage !== '' && preg_match('~^(?:https?://|/)~i', $ogImage) !== 1) {
            $errors['og_image'] = 'Open Graph görseli HTTP(S) veya site içi bir URL olmalıdır.';
        }

        return $errors;
    }

    private function slugExists(string $slug, ?int $exceptId): bool
    {
        $sql = 'SELECT COUNT(*) FROM static_pages WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
        $redirect = $this->pdo->prepare('SELECT COUNT(*) FROM static_page_redirects WHERE old_slug = :slug');
        $redirect->execute(['slug' => $slug]);

        return (int) $redirect->fetchColumn() > 0;
    }

    private function slugIsReserved(string $slug): bool
    {
        $reserved = array_fill_keys([
            'admin', 'api', 'assets', 'themes', 'uploads', 'includes', 'database', 'scripts', 'cron',
            'index.php', 'route.php', 'robots.txt', 'sitemap.xml', 'page-sitemap.xml', 'category-sitemap.xml',
            'topic-sitemap.xml', 'profile-sitemap.xml', 'image-sitemap.xml', 'health', 'favicon.ico', 'xmlrpc.php',
        ], true);
        if (function_exists('routePublicRouteCatalog')) {
            foreach (array_keys(routePublicRouteCatalog()) as $path) {
                $first = strtolower((string) (explode('/', trim((string) $path, '/'))[0] ?? ''));
                if ($first !== '') {
                    $reserved[$first] = true;
                }
            }
        }
        if (function_exists('routePrefixSettings')) {
            foreach (routePrefixSettings($this->pdo) as $prefix) {
                $prefix = strtolower(trim((string) $prefix, '/'));
                if ($prefix !== '') {
                    $reserved[$prefix] = true;
                }
            }
        }

        return isset($reserved[strtolower($slug)]);
    }

    private function setLifecycle(int $id, string $status, int $adminId): bool
    {
        if ($id <= 0 || !in_array($status, ['draft', 'archived'], true)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE static_pages SET status = :status, archived_at = :archived_at, updated_by_admin_id = :admin_id, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'archived_at' => $status === 'archived' ? $now : null,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'updated_at' => $now,
            'id' => $id,
        ]);
        $this->invalidateSitemap();

        return $stmt->rowCount() > 0;
    }

    private function boolValue(array $input, string $key): int
    {
        return isset($input[$key]) && in_array(strtolower((string) $input[$key]), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    private function hasVisibleBody(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00a0}]+/u', ' ', $text) ?? $text;

        return trim($text) !== '';
    }

    private function invalidateSitemap(): void
    {
        if (function_exists('seoInvalidateSitemapCaches')) {
            seoInvalidateSitemapCaches();
        }
    }
}
