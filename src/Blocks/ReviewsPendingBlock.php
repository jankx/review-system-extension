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
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Đơn hàng chờ đánh giá', 'jankx') . '</h3>';

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$ordersTable} WHERE customer_id = %d AND status IN ('completed', 'done') ORDER BY created_at DESC LIMIT 20",
            $userId
        ));

        if (empty($orders)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Không có đơn hàng nào chờ đánh giá.', 'jankx') . '</p>';
        } else {
            $pendingOrders = [];
            foreach ($orders as $order) {
                $items = json_decode($order->items, true);
                if (!is_array($items)) {
                    continue;
                }

                $productIds = array_filter(array_map(function ($item) {
                    return (int) ($item['product_id'] ?? 0);
                }, $items));

                if (empty($productIds)) {
                    continue;
                }

                $hasReview = false;
                foreach ($productIds as $pid) {
                    $existing = get_comments([
                        'post_id' => $pid,
                        'user_id' => $userId,
                        'status' => 'approve',
                        'meta_key' => RatingRepository::COMMENT_RATING_KEY,
                        'count' => true,
                    ]);
                    if ($existing > 0) {
                        $hasReview = true;
                        break;
                    }
                }

                if (!$hasReview) {
                    $pendingOrders[] = $order;
                }
            }

            if (empty($pendingOrders)) {
                $output .= '<p class="jankx-empty-state">' . esc_html__('Tất cả đơn hàng đã được đánh giá.', 'jankx') . '</p>';
            } else {
                $output .= '<div class="jankx-order-list">';
                foreach ($pendingOrders as $order) {
                    $items = json_decode($order->items, true);
                    $firstItem = !empty($items) ? reset($items) : null;
                    $itemName = $firstItem['name'] ?? __('Đơn hàng', 'jankx');
                    $itemCount = count($items);

                    $output .= '<div class="jankx-order-item">';
                    $output .= '<div class="jankx-order-info">';
                    $output .= '<span class="jankx-order-title">' . esc_html($itemName);
                    if ($itemCount > 1) {
                        $output .= ' ' . sprintf(__('và %d sản phẩm khác', 'jankx'), $itemCount - 1);
                    }
                    $output .= '</span>';
                    $output .= '<span class="jankx-order-date">' . esc_html(date('d/m/Y', strtotime($order->created_at))) . '</span>';
                    $output .= '</div>';

                    $output .= '<div class="jankx-order-actions">';
                    $reviewUrl = add_query_arg([
                        'jankx_review_action' => 'form',
                        'order_id' => $order->id,
                    ], get_permalink(get_option('jankx_my_account_page_id')));
                    $output .= '<a href="' . esc_url($reviewUrl) . '" class="jankx-btn jankx-btn-primary">' . esc_html__('Viết đánh giá', 'jankx') . '</a>';
                    $output .= '</div>';
                    $output .= '</div>';
                }
                $output .= '</div>';
            }
        }

        $output .= '</div>';
        return $output;
    }
}
