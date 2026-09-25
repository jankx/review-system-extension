<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Block;
use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;
use Jankx\Extensions\CommentRating\Rating\RatingRepository;

class ReviewsMyReviewsBlock extends Block
{
    use RendersCommentMediaTrait;

    protected $blockId = 'jankx/reviews-my-reviews';

    public function render($attributes, $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $userId = get_current_user_id();
        $settings = new ReviewSettings();
        $service = new ReviewService($settings);

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-reviews-section jankx-reviews-my-reviews',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h3 class="jankx-section-title">' . esc_html__('Đánh giá của bạn', 'jankx') . '</h3>';

        $comments = get_comments([
            'user_id' => $userId,
            'status' => 'approve',
            'meta_key' => RatingRepository::COMMENT_RATING_KEY,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
            'number' => 50,
        ]);

        if (empty($comments)) {
            $output .= '<p class="jankx-empty-state">' . esc_html__('Bạn chưa viết đánh giá nào.', 'jankx') . '</p>';
        } else {
            $output .= '<div class="jankx-review-list">';
            foreach ($comments as $comment) {
                $rating = (int) get_comment_meta($comment->comment_ID, RatingRepository::COMMENT_RATING_KEY, true);
                $postTitle = get_the_title($comment->comment_post_ID);
                $postUrl = get_permalink($comment->comment_post_ID);
                $max = $service->getMaxRating();

                $output .= '<div class="jankx-review-item">';
                $output .= '<div class="jankx-review-header">';
                $output .= '<div class="jankx-review-stars">';
                for ($i = 1; $i <= $max; $i++) {
                    $output .= '<span class="jankx-star ' . ($i <= $rating ? 'is-active' : '') . '">★</span>';
                }
                $output .= '<span class="jankx-review-rating-text">' . esc_html($rating . '/' . $max) . '</span>';
                $output .= '</div>';
                $output .= '<span class="jankx-review-date">' . esc_html(date('d/m/Y', strtotime($comment->comment_date))) . '</span>';
                $output .= '</div>';

                $output .= '<div class="jankx-review-body">';
                $output .= '<p class="jankx-review-content">' . esc_html($comment->comment_content) . '</p>';
                $output .= $this->renderCommentMedia($comment);
                if (!empty($postTitle)) {
                    $output .= '<p class="jankx-review-meta">' . esc_html(__('Đánh giá cho:', 'jankx')) . ' <a href="' . esc_url($postUrl) . '">' . esc_html($postTitle) . '</a></p>';
                }

                $orderId = (int) get_comment_meta($comment->comment_ID, ReviewSettings::META_ORDER, true);
                if ($orderId > 0 && class_exists('\Jankx\Extensions\ReviewSystem\Services\PurchaseService')) {
                    $order = (new \Jankx\Extensions\ReviewSystem\Services\PurchaseService())->getOrderForUser($userId, $orderId);
                    if ($order) {
                        $output .= '<p class="jankx-review-meta jankx-review-meta--order">' . esc_html(__('Đơn hàng:', 'jankx')) . ' <strong>#' . esc_html($order->order_number ?: $order->id) . '</strong></p>';
                    }
                }
                $output .= '</div>';

                $pros = $service->getPros($comment->comment_ID);
                $cons = $service->getCons($comment->comment_ID);
                if (!empty($pros) || !empty($cons)) {
                    $output .= '<div class="jankx-review-extras">';
                    if (!empty($pros)) {
                        $output .= '<div class="jankx-review-pros"><strong>' . esc_html(__('Điểm mạnh:', 'jankx')) . '</strong> ' . esc_html(implode(', ', $pros)) . '</div>';
                    }
                    if (!empty($cons)) {
                        $output .= '<div class="jankx-review-cons"><strong>' . esc_html(__('Điểm yếu:', 'jankx')) . '</strong> ' . esc_html(implode(', ', $cons)) . '</div>';
                    }
                    $output .= '</div>';
                }

                $output .= '</div>';
            }
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }
}
