<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Block;
use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;
use Jankx\Extensions\CommentRating\Rating\RatingRepository;

class ReviewsPendingBlock extends Block
{
    protected $blockId = 'jankx/reviews-pending';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        global $wpdb;
        $userId = get_current_user_id();
        $ordersTable = $wpdb->prefix . 'jankx_orders';

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-reviews-section jankx-reviews-pending',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Sản phẩm chờ đánh giá', 'jankx') . '</h3>';

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$ordersTable} WHERE customer_id = %d AND status IN ('completed', 'done') ORDER BY created_at DESC",
            $userId
        ));

        if (empty($orders)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa có đơn hàng nào.', 'jankx') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // Collect all pending items across all orders
        $pendingItems = [];

        foreach ($orders as $order) {
            $items = json_decode($order->items, true);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if (!$productId) {
                    continue;
                }

                // Check if user already reviewed this product
                $existing = get_comments([
                    'post_id' => $productId,
                    'user_id' => $userId,
                    'status'  => 'approve',
                    'meta_key' => RatingRepository::COMMENT_RATING_KEY,
                    'count'   => true,
                ]);

                if ($existing > 0) {
                    continue; // Already reviewed
                }

                $pendingItems[] = [
                    'order'     => $order,
                    'product_id' => $productId,
                    'name'      => $item['name'] ?? get_the_title($productId),
                    'type'      => $item['product_type'] ?? get_post_type($productId),
                    'quantity'  => (int) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                ];
            }
        }

        if (empty($pendingItems)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Tất cả sản phẩm đã được đánh giá.', 'jankx') . '</p>';
        } else {
            $output .= '<div class="jankx-review-items-list">';

            foreach ($pendingItems as $item) {
                $productUrl = get_permalink($item['product_id']);
                $productType = $this->getTypeLabel($item['type']);
                $price = $item['unit_price'] > 0
                    ? number_format($item['unit_price'], 0, ',', '.') . 'đ'
                    : '';

                // Use the new review-form block URL pattern
                $reviewUrl = add_query_arg([
                    'jankx_review_action' => 'form',
                    'order_id'            => $item['order']->id,
                    'product_id'          => $item['product_id'],
                ], get_permalink(get_option('jankx_my_account_page_id')));

                $output .= '<div class="jankx-review-item-card">';
                $output .= '<div class="jankx-review-item-info">';

                // Product name as link
                $output .= '<a href="' . esc_url($productUrl) . '" class="jankx-review-item-name">';
                $output .= esc_html($item['name']);
                $output .= '</a>';

                // Meta info
                $output .= '<div class="jankx-review-item-meta">';
                if ($productType) {
                    $output .= '<span class="jankx-review-item-type">' . esc_html($productType) . '</span>';
                }
                if ($price) {
                    $output .= '<span class="jankx-review-item-price">' . esc_html($price) . '</span>';
                }
                $output .= '<span class="jankx-review-item-order">' . sprintf(__('Đơn hàng #%s', 'jankx'), esc_html($item['order']->id)) . '</span>';
                $output .= '<span class="jankx-review-item-date">' . esc_html(date('d/m/Y', strtotime($item['order']->created_at))) . '</span>';
                $output .= '</div>';

                $output .= '</div>';

                // Review button
                $output .= '<div class="jankx-review-item-actions">';
                $output .= '<a href="' . esc_url($reviewUrl) . '" class="jankx-btn jankx-btn-primary">';
                $output .= '<span class="dashicons dashicons-edit"></span> ' . esc_html__('Viết đánh giá', 'jankx');
                $output .= '</a>';
                $output .= '</div>';

                $output .= '</div>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    protected function getTypeLabel(string $type): string
    {
        $labels = [
            'tour'       => __('Tour', 'jankx'),
            'experience' => __('Trải nghiệm', 'jankx'),
            'place'      => __('Địa điểm', 'jankx'),
            'product'    => __('Sản phẩm', 'jankx'),
            'service'    => __('Dịch vụ', 'jankx'),
        ];

        return $labels[$type] ?? ucfirst($type);
    }
}
