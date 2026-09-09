<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;

class ReviewSummaryBlock
{
    public function render(array $attributes, $content = ''): string
    {
        $settings = new ReviewSettings();
        $service = new ReviewService($settings);

        $postId = $this->getPostId();
        if (!$postId) {
            return '';
        }

        $average = $service->getAverage($postId);
        $count = $service->getCount($postId);

        if ($count === 0) {
            return '<p>' . esc_html__('Chưa có đánh giá.', 'jankx') . '</p>';
        }

        $max = $service->getMaxRating();
        $distribution = $service->getRatingDistribution($postId);
        $showDistribution = $attributes['showDistribution'] ?? true;

        ob_start();
        ?>
        <div class="wp-block-jankx-review-summary">
            <div class="review-system-summary" role="img" aria-label="<?php printf(esc_attr__('Điểm trung bình %.1f trên %d từ %d đánh giá', 'jankx'), $average, $max, $count); ?>">
                <div class="review-system-summary__main">
                    <div class="review-system-summary__score">
                        <span class="review-system-summary__number"><?php echo esc_html(number_format($average, 1)); ?></span>
                        <span class="review-system-summary__max">/<?php echo esc_html($max); ?></span>
                    </div>
                    <div class="review-system-summary__stars">
                        <?php for ($i = 1; $i <= $max; $i++) : ?>
                            <span class="review-system-star <?php echo $i <= round($average) ? 'is-active' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="review-system-summary__count">
                        <?php printf(esc_html('%d đánh giá', 'jankx'), $count); ?>
                    </div>
                </div>

                <?php if ($showDistribution) : ?>
                <div class="review-system-summary__distribution">
                    <?php for ($i = $max; $i >= 1; $i--) :
                        $num = $distribution[$i] ?? 0;
                        $pct = $count > 0 ? round(($num / $count) * 100) : 0;
                    ?>
                    <div class="review-system-dist-row" data-rating="<?php echo esc_attr($i); ?>">
                        <span class="review-system-dist-label"><?php echo esc_html($i); ?> ★</span>
                        <div class="review-system-dist-bar">
                            <div class="review-system-dist-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                        </div>
                        <span class="review-system-dist-count"><?php echo esc_html($num); ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function getPostId(): int
    {
        if (is_singular()) {
            return get_the_ID();
        }
        $postId = get_queried_object_id();
        if ($postId) {
            return $postId;
        }
        global $post;
        return $post ? (int) $post->ID : 0;
    }
}
