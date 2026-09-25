<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Block;
use Jankx\Extensions\ReviewSystem\Repositories\DatabaseReviewRepository;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;
use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;

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
        $repository = new DatabaseReviewRepository();
        $service = new ReviewService(new ReviewSettings());
        $max = $service->getMaxRating();

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-reviews-section jankx-reviews-completed',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Sản phẩm đã đánh giá', 'jankx') . '</h3>';

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$ordersTable} WHERE customer_id = %d AND status IN ('completed', 'done') ORDER BY created_at DESC, id DESC",
            $userId
        ));

        if (empty($orders)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa có đơn hàng nào.', 'jankx') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // Mỗi dòng = một (đơn hàng, sản phẩm) đã được đánh giá.
        $reviewedItems = [];

        foreach ($orders as $order) {
            $items = json_decode($order->items, true);
            if (!is_array($items)) {
                continue;
            }

            $seen = [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if (!$productId || isset($seen[$productId])) {
                    continue;
                }
                $seen[$productId] = true;

                $review = $repository->findByOrderAndPost((int) $order->id, $productId);
                if (!$review) {
                    continue;
                }

                $comment = $review->getCommentId() > 0 ? get_comment($review->getCommentId()) : null;

                $reviewedItems[] = [
                    'order'      => $order,
                    'product_id' => $productId,
                    'name'       => $item['name'] ?? get_the_title($productId),
                    'type'       => $item['product_type'] ?? get_post_type($productId),
                    'review'     => $review,
                    'comment'    => $comment,
                    'rating'     => $review->getRating(),
                ];
            }
        }

        if (empty($reviewedItems)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa đánh giá sản phẩm nào.', 'jankx') . '</p>';
        } else {
            $output .= '<div class="jankx-review-items-list jankx-review-items-list--completed">';

            foreach ($reviewedItems as $item) {
                $productUrl = get_permalink($item['product_id']);
                $productType = $this->getTypeLabel($item['type']);

                $output .= '<div class="jankx-review-item-card jankx-review-item-card--reviewed">';

                $output .= '<div class="jankx-review-item-rating">';
                $output .= '<div class="jankx-review-stars">';
                for ($i = 1; $i <= $max; $i++) {
                    $output .= '<span class="jankx-star ' . ($i <= $item['rating'] ? 'is-active' : '') . '">★</span>';
                }
                $output .= '</div>';
                $output .= '<span class="jankx-review-rating-text">' . esc_html($item['rating'] . '/' . $max) . '</span>';
                $output .= '</div>';

                $output .= '<div class="jankx-review-item-info">';
                $output .= '<a href="' . esc_url($productUrl) . '" class="jankx-review-item-name">';
                $output .= esc_html($item['name']);
                $output .= '</a>';
                $output .= '<div class="jankx-review-item-meta">';
                if ($productType) {
                    $output .= '<span class="jankx-review-item-type">' . esc_html($productType) . '</span>';
                }
                $output .= '<span class="jankx-review-item-order">' . sprintf(__('Đơn hàng #%s', 'jankx'), esc_html($item['order']->order_number ?: $item['order']->id)) . '</span>';
                $output .= '<span class="jankx-review-item-date">' . esc_html(date('d/m/Y', strtotime($item['review']->getCreatedAt() ?: $item['order']->created_at))) . '</span>';
                $output .= '</div>';
                $output .= '</div>';

                $content = $item['comment'] ? $item['comment']->comment_content : $item['review']->getContent();
                $pros = $item['comment'] ? $service->getPros($item['comment']->comment_ID) : $item['review']->getPros();
                $cons = $item['comment'] ? $service->getCons($item['comment']->comment_ID) : $item['review']->getCons();

                $output .= '<div class="jankx-review-item-content">';
                if (!empty($content)) {
                    $output .= '<p class="jankx-review-text">' . esc_html($content) . '</p>';
                }
                if (!empty($pros)) {
                    $output .= '<div class="jankx-review-pros">';
                    $output .= '<span class="jankx-review-label jankx-review-label--pros">' . esc_html__('Điểm mạnh:', 'jankx') . '</span>';
                    $output .= '<ul>';
                    foreach ($pros as $pro) {
                        $output .= '<li>' . esc_html($pro) . '</li>';
                    }
                    $output .= '</ul>';
                    $output .= '</div>';
                }
                if (!empty($cons)) {
                    $output .= '<div class="jankx-review-cons">';
                    $output .= '<span class="jankx-review-label jankx-review-label--cons">' . esc_html__('Điểm yếu:', 'jankx') . '</span>';
                    $output .= '<ul>';
                    foreach ($cons as $con) {
                        $output .= '<li>' . esc_html($con) . '</li>';
                    }
                    $output .= '</ul>';
                    $output .= '</div>';
                }
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