<?php

namespace Jankx\Extensions\ReviewSystem\Frontend;

use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;

class ReviewDisplay
{
    protected $settings;

    public function __construct(ReviewSettings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('comment_text', [$this, 'showProsCons'], 15, 2);
    }

    public function showProsCons(string $text, $comment): void
    {
        if (!$comment instanceof \WP_Comment) {
            return;
        }

        if (!$this->settings->isEnabled()) {
            return;
        }

        $postId = $comment->comment_post_ID;
        if (!$postId || !$this->settings->isPostTypeSupported(get_post_type($postId))) {
            return;
        }

        $rating = (int) get_comment_meta($comment->comment_ID, 'jankx_comment_rating', true);
        if ($rating < 1) {
            return;
        }

        $service = new ReviewService($this->settings);
        $pros = $service->getPros($comment->comment_ID);
        $cons = $service->getCons($comment->comment_ID);

        if (empty($pros) && empty($cons)) {
            return;
        }

        $max = $this->settings->isEnabled() && class_exists('\Jankx\Extensions\CommentRating\Admin\Settings')
            ? \Jankx\Extensions\CommentRating\Admin\Settings::getMaxRating()
            : 5;

        ?>
        <div class="review-system-comment-extras">
            <div class="review-system-stars" aria-label="<?php printf(esc_attr__('%d/%d sao', 'jankx'), $rating, $max); ?>">
                <?php for ($i = 1; $i <= $max; $i++) : ?>
                    <span class="review-system-star <?php echo $i <= $rating ? 'is-active' : ''; ?>">★</span>
                <?php endfor; ?>
                <span class="review-system-rating-text"><?php printf(esc_html('%d/%d', 'jankx'), $rating, $max); ?></span>
            </div>

            <?php if (!empty($pros)) : ?>
            <div class="review-system-pros-list">
                <strong class="review-system-label review-system-label--pros">
                    <span class="dashicons dashicons-thumbs-up"></span>
                    <?php esc_html_e('Điểm mạnh', 'jankx'); ?>
                </strong>
                <ul>
                    <?php foreach ($pros as $pro) : ?>
                        <li><?php echo esc_html($pro); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($cons)) : ?>
            <div class="review-system-cons-list">
                <strong class="review-system-label review-system-label--cons">
                    <span class="dashicons dashicons-thumbs-down"></span>
                    <?php esc_html_e('Điểm yếu', 'jankx'); ?>
                </strong>
                <ul>
                    <?php foreach ($cons as $con) : ?>
                        <li><?php echo esc_html($con); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
