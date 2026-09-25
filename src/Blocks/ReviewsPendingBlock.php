<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Block;
use Jankx\Extensions\ReviewSystem\Repositories\DatabaseReviewRepository;

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
        $repository = new DatabaseReviewRepository();

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-reviews-section jankx-reviews-pending',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Sản phẩm chờ đánh giá', 'jankx') . '</h3>';

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$ordersTable} WHERE customer_id = %d AND status IN ('completed', 'done') ORDER BY created_at DESC, id DESC",
            $userId
        ));

        if (empty($orders)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa có đơn hàng nào.', 'jankx') . '</p>';
            $output .= '</div>';
            return $output;
        }

        // Mỗi dòng = một (đơn hàng, sản phẩm) chưa được đánh giá.
        $pendingItems = [];

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

                // Đã đánh giá cho đơn hàng này rồi?
                if ($repository->findByOrderAndPost((int) $order->id, $productId) !== null) {
                    continue;
                }

                $pendingItems[] = [
                    'order'      => $order,
                    'product_id' => $productId,
                    'name'       => $item['name'] ?? get_the_title($productId),
                    'type'       => $item['product_type'] ?? get_post_type($productId),
                    'quantity'   => (int) ($item['quantity'] ?? 1),
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

                // Link tới form đánh giá trên trang sản phẩm, preselect đúng đơn hàng.
                $reviewUrl = trailingslashit($productUrl) . '?order_id=' . (int) $item['order']->id . '#reviewform';

                $output .= '<div class="jankx-review-item-card">';
                $output .= '<div class="jankx-review-item-info">';

                $output .= '<a href="' . esc_url($productUrl) . '" class="jankx-review-item-name">';
                $output .= esc_html($item['name']);
                $output .= '</a>';

                $output .= '<div class="jankx-review-item-meta">';
                if ($productType) {
                    $output .= '<span class="jankx-review-item-type">' . esc_html($productType) . '</span>';
                }
                if ($price) {
                    $output .= '<span class="jankx-review-item-price">' . esc_html($price) . '</span>';
                }
                $output .= '<span class="jankx-review-item-order">' . sprintf(__('Đơn hàng #%s', 'jankx'), esc_html($item['order']->order_number ?: $item['order']->id)) . '</span>';
                $output .= '<span class="jankx-review-item-date">' . esc_html(date('d/m/Y', strtotime($item['order']->created_at))) . '</span>';
                $output .= '</div>';

                $output .= '</div>';

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