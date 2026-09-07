<?php

declare(strict_types=1);

namespace Period\WpKit\WordPress;

final class MediaReferenceIndex
{
    private string $reverseMetaKey;
    private string $forwardMetaKey;
    private string $stateOption;
    private string $enabledFilter;
    private array $ignoredMetaKeys;

    public function __construct(array $config = [])
    {
        $this->reverseMetaKey = (string) ($config['reverse_meta_key'] ?? '_pwk_media_references');
        $this->forwardMetaKey = (string) ($config['forward_meta_key'] ?? '_pwk_media_reference_targets');
        $this->stateOption = (string) ($config['state_option'] ?? 'pwk_media_reference_index_state');
        $this->enabledFilter = (string) ($config['enabled_filter'] ?? 'wp_kit_media_reference_index_enabled');
        $this->ignoredMetaKeys = array_values(array_unique(array_merge(
            [$this->reverseMetaKey, $this->forwardMetaKey],
            array_map('strval', $config['ignored_meta_keys'] ?? [])
        )));
    }

    public function enabled(): bool
    {
        if (!function_exists('apply_filters')) {
            return false;
        }

        return (bool) apply_filters($this->enabledFilter, false, $this);
    }

    public function boot(): void
    {
        if (!$this->enabled()) {
            return;
        }

        add_action('save_post', [$this, 'onSavePost'], 50, 2);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [$this, 'onPostMetaChanged'], 50, 3);
        }
        add_action('before_delete_post', [$this, 'onBeforeDeletePost'], 10, 2);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('wp-kit media-reference rebuild', function (array $args, array $assocArgs): void {
                $this->cliRebuild($assocArgs);
            });
            \WP_CLI::add_command('wp-kit media-reference status', function (): void {
                $this->cliStatus();
            });
        }
    }

    public function getReferences(int $attachmentId): array
    {
        $references = get_post_meta($attachmentId, $this->reverseMetaKey, true);
        return is_array($references) ? $references : [];
    }

    public function isReferenced(int $attachmentId): bool
    {
        return $this->getReferences($attachmentId) !== [];
    }

    public function refreshPost(int $postId): void
    {
        if ($postId <= 0 || get_post_type($postId) === 'attachment') {
            return;
        }

        $oldTargets = get_post_meta($postId, $this->forwardMetaKey, true);
        $oldTargets = is_array($oldTargets) ? array_map('intval', $oldTargets) : [];
        $refs = $this->scanPost($postId);
        $newTargets = array_map('intval', array_keys($refs));

        foreach (array_diff($oldTargets, $newTargets) as $attachmentId) {
            $this->removeSourceFromAttachment((int) $attachmentId, $postId);
        }

        foreach ($refs as $attachmentId => $locations) {
            $references = get_post_meta((int) $attachmentId, $this->reverseMetaKey, true);
            if (!is_array($references)) {
                $references = [];
            }
            $references[(string) $postId] = [
                'post_id' => $postId,
                'post_type' => (string) get_post_type($postId),
                'post_status' => (string) get_post_status($postId),
                'locations' => array_values($locations),
                'updated_at' => current_time('mysql'),
            ];
            update_post_meta((int) $attachmentId, $this->reverseMetaKey, $references);
        }

        if ($newTargets !== []) {
            update_post_meta($postId, $this->forwardMetaKey, array_values(array_unique($newTargets)));
        } else {
            delete_post_meta($postId, $this->forwardMetaKey);
        }
    }

    public function onSavePost(int $postId, $post): void
    {
        if (!$post || $post->post_type === 'attachment' || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        $this->scheduleRefresh($postId);
    }

    public function onPostMetaChanged($metaId, int $postId, string $metaKey): void
    {
        if (in_array($metaKey, $this->ignoredMetaKeys, true)) {
            return;
        }
        $this->scheduleRefresh($postId);
    }

    public function onBeforeDeletePost(int $postId, $post): void
    {
        if (!$post || $post->post_type === 'attachment') {
            return;
        }

        $targets = get_post_meta($postId, $this->forwardMetaKey, true);
        if (!is_array($targets)) {
            return;
        }

        foreach ($targets as $attachmentId) {
            $this->removeSourceFromAttachment((int) $attachmentId, $postId);
        }
    }

    private function scheduleRefresh(int $postId): void
    {
        static $queued = [];

        if ($postId <= 0 || isset($queued[$postId]) || get_post_type($postId) === 'attachment') {
            return;
        }

        $queued[$postId] = true;
        add_action('shutdown', function () use ($postId): void {
            $this->refreshPost($postId);
        }, 1);
    }

    private function scanPost(int $postId): array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type === 'attachment' || $post->post_status === 'trash') {
            return [];
        }

        $refs = $this->collectFromText((string) $post->post_content, 'post_content');
        $allMeta = get_post_meta($postId);

        foreach ($allMeta as $metaKey => $values) {
            if (in_array($metaKey, $this->ignoredMetaKeys, true)) {
                continue;
            }
            foreach ($values as $value) {
                $this->merge($refs, $this->collectFromValue($value, 'post_meta', (string) $metaKey));
            }
        }

        return $refs;
    }

    private function collectFromValue($value, string $source, string $metaKey = ''): array
    {
        $refs = [];
        $walk = function ($item) use (&$walk, &$refs, $source, $metaKey): void {
            if (is_array($item) || is_object($item)) {
                foreach ((array) $item as $child) {
                    $walk($child);
                }
                return;
            }
            if (!is_scalar($item)) {
                return;
            }

            $string = (string) $item;
            $this->merge($refs, $this->collectFromText($string, $source, $metaKey));

            if (ctype_digit($string) && preg_match('/(?:attachment|media|image|video|thumbnail|poster)(?:_id)?$/i', $metaKey)) {
                $this->addLocation($refs, (int) $string, $source, $metaKey, 'meta-id');
            }
        };

        $walk(maybe_unserialize($value));
        return $refs;
    }

    private function collectFromText(string $text, string $source, string $metaKey = ''): array
    {
        $refs = [];
        if ($text === '') {
            return $refs;
        }

        $patterns = [
            'data-attachment-id' => '/data-attachment-id=["\x27](\d+)["\x27]/',
            'wp-image' => '/wp-image-(\d+)/',
            'attachment-id' => '/attachment[_-]id["\x27\s:=]+(\d+)/i',
        ];
        foreach ($patterns as $kind => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $id) {
                    $this->addLocation($refs, (int) $id, $source, $metaKey, $kind);
                }
            }
        }

        if (preg_match_all('/\[gallery[^\]]*ids=["\x27]([^"\x27]+)["\x27][^\]]*\]/', $text, $matches)) {
            foreach ($matches[1] as $ids) {
                foreach (preg_split('/\s*,\s*/', $ids) as $id) {
                    if (ctype_digit((string) $id)) {
                        $this->addLocation($refs, (int) $id, $source, $metaKey, 'gallery');
                    }
                }
            }
        }

        if (preg_match_all('#https?://[^\s"\x27<>]+/wp-content/uploads/[^\s"\x27<>]+#i', $text, $matches)) {
            foreach ($matches[0] as $url) {
                $url = html_entity_decode($url);
                $id = attachment_url_to_postid($url) ?: $this->resolveUploadPath($url);
                if ($id) {
                    $this->addLocation($refs, $id, $source, $metaKey, 'upload-url');
                }
            }
        }

        if (preg_match_all('#(?:^|["\x27\s(])((?:\d{4}/\d{2}/)[^"\x27\s)<]+)#', $text, $matches)) {
            foreach ($matches[1] as $relative) {
                $id = $this->resolveUploadPath(html_entity_decode($relative));
                if ($id) {
                    $this->addLocation($refs, $id, $source, $metaKey, 'upload-path');
                }
            }
        }

        return $refs;
    }

    private function resolveUploadPath(string $value): int
    {
        $upload = wp_upload_dir();
        $relative = '';

        if (strpos($value, $upload['baseurl']) === 0) {
            $relative = ltrim(substr($value, strlen($upload['baseurl'])), '/');
        } elseif (strpos($value, 'wp-content/uploads/') !== false) {
            $relative = ltrim(substr($value, strpos($value, 'wp-content/uploads/') + strlen('wp-content/uploads/')), '/');
        } elseif (preg_match('#^(?:\d{4}/\d{2}/).+#', $value)) {
            $relative = ltrim($value, '/');
        }

        if ($relative === '') {
            return 0;
        }

        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
            $relative
        ));
    }

    private function addLocation(array &$refs, int $attachmentId, string $source, string $metaKey, string $kind): void
    {
        if ($attachmentId <= 0 || get_post_type($attachmentId) !== 'attachment') {
            return;
        }

        if (!isset($refs[$attachmentId])) {
            $refs[$attachmentId] = [];
        }
        $location = ['source' => $source, 'meta_key' => $metaKey, 'kind' => $kind];
        $fingerprint = wp_json_encode($location);
        foreach ($refs[$attachmentId] as $existing) {
            if (wp_json_encode($existing) === $fingerprint) {
                return;
            }
        }
        $refs[$attachmentId][] = $location;
    }

    private function merge(array &$target, array $sourceRefs): void
    {
        foreach ($sourceRefs as $attachmentId => $locations) {
            foreach ($locations as $location) {
                $this->addLocation(
                    $target,
                    (int) $attachmentId,
                    (string) ($location['source'] ?? ''),
                    (string) ($location['meta_key'] ?? ''),
                    (string) ($location['kind'] ?? '')
                );
            }
        }
    }

    private function removeSourceFromAttachment(int $attachmentId, int $sourcePostId): void
    {
        $references = get_post_meta($attachmentId, $this->reverseMetaKey, true);
        if (!is_array($references)) {
            return;
        }

        unset($references[(string) $sourcePostId]);
        if ($references !== []) {
            update_post_meta($attachmentId, $this->reverseMetaKey, $references);
        } else {
            delete_post_meta($attachmentId, $this->reverseMetaKey);
        }
    }

    private function cliRebuild(array $assocArgs): void
    {
        global $wpdb;

        $batchSize = isset($assocArgs['batch-size']) ? max(10, (int) $assocArgs['batch-size']) : 250;
        $afterId = isset($assocArgs['after-id']) ? max(0, (int) $assocArgs['after-id']) : 0;
        $reset = !empty($assocArgs['reset']);

        if ($reset) {
            \WP_CLI::log('Clearing existing media reference indexes...');
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)",
                $this->reverseMetaKey,
                $this->forwardMetaKey
            ));
            delete_option($this->stateOption);
        }

        $processed = 0;
        $lastId = $afterId;
        while (true) {
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE ID > %d AND post_type <> 'attachment' AND post_status <> 'trash'
                 ORDER BY ID ASC LIMIT %d",
                $lastId,
                $batchSize
            ));
            if (!$ids) {
                break;
            }

            foreach ($ids as $postId) {
                $postId = (int) $postId;
                $this->refreshPost($postId);
                $lastId = $postId;
                $processed++;
            }

            update_option($this->stateOption, [
                'status' => 'building',
                'last_post_id' => $lastId,
                'processed' => $processed,
                'updated_at' => current_time('mysql'),
            ], false);
            \WP_CLI::log(sprintf('Indexed %d posts (last ID: %d)', $processed, $lastId));
        }

        update_option($this->stateOption, [
            'status' => 'complete',
            'last_post_id' => $lastId,
            'processed' => $processed,
            'updated_at' => current_time('mysql'),
            'version' => 1,
        ], false);

        \WP_CLI::success(sprintf('Media reference index rebuilt: %d posts.', $processed));
    }

    private function cliStatus(): void
    {
        $state = get_option($this->stateOption, []);
        if (!$state) {
            \WP_CLI::log('Media reference index has not been built yet.');
            return;
        }
        foreach ($state as $key => $value) {
            \WP_CLI::log($key . ': ' . (is_scalar($value) ? (string) $value : wp_json_encode($value)));
        }
    }
}
