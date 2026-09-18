<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Block;
use Jankx\Extensions\CommentRating\Rating\RatingRepository;

class ReviewsCompletedBlock extends Block
{
    protected $blockId = 'jankx/reviews-completed';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        global $wpdb;
        $userId = get_current_user_id();
        $ordersTable = $wpdb->prefix . 'jankx_orders';

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-reviews-section jankx-reviews-completed',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Đơn đã đánh giá', 'jankx') . '</h3>';

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$ordersTable} WHERE customer_id = %d AND status IN ('completed', 'done') ORDER BY created_at DESC LIMIT 20",
            $userId
        ));

        if (empty($orders)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa có đơn hàng nào.', 'jankx') . '</p>';
        } else {
            $reviewedOrders = [];
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

                $reviewedProducts = [];
                foreach ($productIds as $pid) {
                    $comments = get_comments([
                        'post_id' => $pid,
                        'user_id' => $userId,
                        'status' => 'approve',
                        'meta_key' => RatingRepository::COMMENT_RATING_KEY,
                        'number' => 1,
                    ]);
                    if (!empty($comments)) {
                        $reviewedProducts[$pid] = $comments[0];
                    }
                }

                if (!empty($reviewedProducts)) {
                    $reviewedOrders[] = [
                        'order' => $order,
                        'reviews' => $reviewedProducts,
                    ];
                }
            }

            if (empty($reviewedOrders)) {
                $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa đánh giá đơn hàng nào.', 'jankx') . '</p>';
            } else {
                $output .= '<div class="jankx-review-list">';
                foreach ($reviewedOrders as $entry) {
                    $order = $entry['order'];
                    $reviews = $entry['reviews'];
                    $items = json_decode($order->items, true);
                    $firstItem = !empty($items) ? reset($items) : null;
                    $itemName = $firstItem['name'] ?? __('Đơn hàng', 'jankx');

                    foreach ($reviews as $pid => $comment) {
                        $rating = (int) get_comment_meta($comment->comment_ID, RatingRepository::COMMENT_RATING_KEY, true);
                        $postTitle = get_the_title($pid);

                        $output .= '<div class="jankx-review-item">';
                        $output .= '<div class="jankx-review-header">';
                        $output .= '<div class="jankx-review-stars">';
                        for ($i = 1; $i <= 5; $i++) {
                            $output .= '<span class="jankx-star ' . ($i <= $rating ? 'is-active' : '') . '">★</span>';
                        }
                        $output .= '</div>';
                        $output .= '<span class="jankx-review-date">' . esc_html(date('d/m/Y', strtotime($comment->comment_date))) . '</span>';
                        $output .= '</div>';

                        $output .= '<div class="jankx-review-body">';
                        $output .= '<p class="jankx-review-content">' . esc_html($comment->comment_content) . '</p>';
                        $output .= '<p class="jankx-review-meta">' . esc_html($postTitle) . '</p>';
                        $output .= '</div>';
                        $output .= '</div>';
                    }
                }
                $output .= '</div>';
            }
        }

        $output .= '</div>';
        return $output;
    }
}
