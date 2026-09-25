<?php

namespace Jankx\Extensions\ReviewSystem\Services;

/**
 * Xác thực lịch sử mua hàng thành công.
 *
 * Tương thích tuyệt đối với extension base-ecommerce: một đơn hàng được coi
 * là "đã mua thành công" khi có status = completed (Order::STATUS_COMPLETED,
 * kèm legacy 'done' cho các đơn hàng được nhập từ hệ thống cũ).
 *
 * @package Jankx\Extensions\ReviewSystem\Services
 */
class PurchaseService
{
    const ORDER_TABLE = 'jankx_orders';
    const ORDER_POSTS_TABLE = 'jankx_order_posts';

    /**
     * Các trạng thái đơn hàng được tính là mua hàng thành công.
     */
    protected $completedStatuses = ['completed', 'done'];

    public function __construct()
    {
        if (class_exists('\Jankx\Extensions\Ecommerce\Order\Order')) {
            $completed = \Jankx\Extensions\Ecommerce\Order\Order::STATUS_COMPLETED;
            $this->completedStatuses = array_values(array_unique([$completed, 'done']));
        }

        $this->completedStatuses = (array) apply_filters(
            'jankx/review_system/completed_order_statuses',
            $this->completedStatuses
        );
    }

    public function isEcommerceActive(): bool
    {
        return class_exists('\Jankx\Extensions\Ecommerce\EcommerceExtension')
            && function_exists('wpdb')
            && $this->orderTableExists();
    }

    /**
     * Người dùng $userId đã mua thành công sản phẩm $productId hay chưa.
     */
    public function hasCompletedPurchase(int $userId, int $productId): bool
    {
        if ($userId < 1 || $productId < 1) {
            return false;
        }

        $userId  = (int) apply_filters('jankx/review_system/purchase_user_id', $userId, $productId);
        $productId = (int) $productId;

        if (!$this->isEcommerceActive()) {
            return (bool) apply_filters('jankx/review_system/has_completed_purchase', false, $userId, $productId);
        }

        global $wpdb;
        $ordersTable = $wpdb->prefix . self::ORDER_TABLE;
        $orderPostsTable = $wpdb->prefix . self::ORDER_POSTS_TABLE;
        $statuses = $this->completedStatuses ?: ['completed'];

        // 1) Khớp qua bảng liên kết jankx_order_posts (nhanh, đúng chuẩn ecommerce).
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = "SELECT COUNT(*)
                FROM {$ordersTable} o
                INNER JOIN {$orderPostsTable} p ON p.order_id = o.id
                WHERE o.customer_id = %d
                  AND o.status IN ({$placeholders})
                  AND p.post_id = %d";
        $values = array_merge([$userId], $statuses, [$productId]);
        $count = (int) $wpdb->get_var($wpdb->prepare($sql, ...$values));

        if ($count > 0) {
            return (bool) apply_filters('jankx/review_system/has_completed_purchase', true, $userId, $productId);
        }

        // 2) Fallback: quét items JSON khi đơn hàng chưa có bản ghi order_posts.
        $itemsSql = $wpdb->prepare(
            "SELECT items FROM {$ordersTable}
             WHERE customer_id = %d AND status IN ({$placeholders})
             ORDER BY created_at DESC LIMIT 200",
            array_merge([$userId], $statuses)
        );
        $rows = $wpdb->get_col($itemsSql);

        foreach ($rows as $itemsJson) {
            $items = json_decode((string) $itemsJson, true);
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if ((int) ($item['product_id'] ?? 0) === $productId) {
                    return (bool) apply_filters('jankx/review_system/has_completed_purchase', true, $userId, $productId);
                }
            }
        }

        return (bool) apply_filters('jankx/review_system/has_completed_purchase', false, $userId, $productId);
    }

    /**
     * Tổ hợp điều kiện: đã đăng nhập + (không bắt buộc mua hàng | đã mua thành công).
     */
    public function canReview(int $userId, int $productId, bool $requirePurchase): bool
    {
        if ($userId < 1) {
            return false;
        }

        if (!$requirePurchase) {
            return true;
        }

        return $this->hasCompletedPurchase($userId, $productId);
    }

    protected function orderTableExists(): bool
    {
        global $wpdb;
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cached = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . self::ORDER_TABLE
            )
        );

        return $cached;
    }
}