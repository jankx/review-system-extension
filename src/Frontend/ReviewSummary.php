<?php

namespace Jankx\Extensions\ReviewSystem\Frontend;

use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;

class ReviewSummary
{
    protected $settings;

    public function __construct(ReviewSettings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_shortcode('review_summary', [$this, 'shortcode']);
    }

    public function shortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['post_id' => get_the_ID()], $atts);
        $postId = (int) $atts['post_id'];

        ob_start();
        $this->render($postId);
        return ob_get_clean();
    }

    public function render(int $postId): void
    {
        $service = new ReviewService($this->settings);
        $average = $service->getAverage($postId);
        $count = $service->getCount($postId);

        if ($count === 0) {
            return;
        }

        $max = $service->getMaxRating();
        $distribution = $service->getRatingDistribution($postId);
        $maxCount = max(array_values($distribution));
        ?>
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
        </div>
        <?php
    }

    protected function getPostId(): int
    {
        $postId = get_the_ID();
        if ($postId) {
            return $postId;
        }
        $postId = get_queried_object_id();
        if ($postId) {
            return $postId;
        }
        global $post;
        return $post ? (int) $post->ID : 0;
    }
}
