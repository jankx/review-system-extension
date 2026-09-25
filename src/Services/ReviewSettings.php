<?php

namespace Jankx\Extensions\ReviewSystem\Services;

class ReviewSettings
{
    const OPTION_ENABLED = 'review_system_enabled';
    const OPTION_SHOW_PROS = 'review_system_show_pros';
    const OPTION_SHOW_CONS = 'review_system_show_cons';
    const OPTION_POST_TYPES = 'review_system_post_types';
    const OPTION_SORT_DEFAULT = 'review_system_sort_default';
    const OPTION_REQUIRE_PURCHASE = 'review_system_require_purchase';

    const META_PROS = '_jankx_review_pros';
    const META_CONS = '_jankx_review_cons';
    const META_ORDER = '_jankx_review_order';

    public function getOption(string $key, $default = null)
    {
        $themeMod = get_theme_mod($key);
        if ($themeMod !== false && !is_null($themeMod)) {
            return $themeMod;
        }

        $options = get_option('jankx_options', []);
        if (is_array($options) && array_key_exists($key, $options)) {
            return $options[$key];
        }
        return $default;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->getOption(self::OPTION_ENABLED, 1);
    }

    public function showPros(): bool
    {
        return false;
    }

    public function showCons(): bool
    {
        return false;
    }

    public function getPostTypes(): array
    {
        $saved = $this->getOption(self::OPTION_POST_TYPES, null);
        if ($saved === null || (is_array($saved) && empty($saved))) {
            $saved = ['tour', 'experience', 'place', 'product', 'service', 'post', 'page'];
        }
        if (!is_array($saved)) {
            $saved = [$saved];
        }
        $saved = array_values(array_filter(array_map('sanitize_text_field', $saved)));

        // Domain extensions register their post types via the
        // jankx/review_system/supported_post_types filter (ReviewSystemExtension::support_post_type).
        return apply_filters('jankx/review_system/supported_post_types', $saved);
    }

    public function getDefaultSort(): string
    {
        return sanitize_text_field($this->getOption(self::OPTION_SORT_DEFAULT, 'newest'));
    }

    /**
     * Chỉ hiển thị form đánh giá khi người dùng đã mua hàng thành công
     * (đơn hàng ở trạng thái completed / success).
     */
    public function requirePurchase(): bool
    {
        return (bool) $this->getOption(self::OPTION_REQUIRE_PURCHASE, 1);
    }

    public function isPostTypeSupported(string $postType): bool
    {
        return in_array($postType, $this->getPostTypes(), true);
    }

    public function getPostTypesForSelect(): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $list = [];
        foreach ($postTypes as $pt) {
            $label = $pt->labels->singular_name ?? $pt->label ?? $pt->name;
            $list[$pt->name] = sprintf('%s (%s)', $label, $pt->name);
        }
        return $list;
    }
}
