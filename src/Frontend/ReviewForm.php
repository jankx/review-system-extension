<?php

namespace Jankx\Extensions\ReviewSystem\Frontend;

use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;

class ReviewForm
{
    protected $settings;

    public function __construct(ReviewSettings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('comment_form_logged_in_after', [$this, 'renderProsConsFields']);
        add_action('comment_form_after_fields', [$this, 'renderProsConsFields']);
        add_action('comment_post', [$this, 'saveProsCons'], 10, 3);
    }

    public function renderProsConsFields(): void
    {
        if (!$this->settings->isEnabled()) {
            return;
        }

        $postId = $this->getPostId();
        if (!$postId || !$this->settings->isPostTypeSupported(get_post_type($postId))) {
            return;
        }

        $showPros = $this->settings->showPros();
        $showCons = $this->settings->showCons();

        if (!$showPros && !$showCons) {
            return;
        }
        ?>
        <div class="review-system-pros-cons">
            <?php if ($showPros) : ?>
            <div class="review-system-field review-system-pros">
                <label for="review_pros">
                    <span class="dashicons dashicons-thumbs-up" style="color:#16a34a;"></span>
                    <?php esc_html_e('Điểm mạnh (Pros)', 'jankx'); ?>
                </label>
                <p class="description"><?php esc_html_e('Mỗi dòng một điểm mạnh. Để trống nếu không có.', 'jankx'); ?></p>
                <textarea id="review_pros" name="review_pros" rows="3" class="large-text"
                    placeholder="<?php esc_attr_e("Chất lượng tốt\nPhục vụ nhiệt tình\nGiá hợp lý", 'jankx'); ?>"
                ></textarea>
            </div>
            <?php endif; ?>

            <?php if ($showCons) : ?>
            <div class="review-system-field review-system-cons">
                <label for="review_cons">
                    <span class="dashicons dashicons-thumbs-down" style="color:#dc2626;"></span>
                    <?php esc_html_e('Điểm yếu (Cons)', 'jankx'); ?>
                </label>
                <p class="description"><?php esc_html_e('Mỗi dòng một điểm yếu. Để trống nếu không có.', 'jankx'); ?></p>
                <textarea id="review_cons" name="review_cons" rows="3" class="large-text"
                    placeholder="<?php esc_attr_e("Chờ đợi lâu\nPhòng hơi nhỏ", 'jankx'); ?>"
                ></textarea>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function saveProsCons(int $commentId, int $approved, array $commentData): void
    {
        if (!$this->settings->isEnabled()) {
            return;
        }

        $postId = (int) ($commentData['comment_post_ID'] ?? 0);
        if (!$postId || !$this->settings->isPostTypeSupported(get_post_type($postId))) {
            return;
        }

        $service = new \Jankx\Extensions\ReviewSystem\Services\ReviewService($this->settings);

        if ($this->settings->showPros() && isset($_POST['review_pros'])) {
            $pros = array_filter(array_map('trim', explode("\n", sanitize_textarea_field(wp_unslash($_POST['review_pros'])))));
            $service->savePros($commentId, $pros);
        }

        if ($this->settings->showCons() && isset($_POST['review_cons'])) {
            $cons = array_filter(array_map('trim', explode("\n", sanitize_textarea_field(wp_unslash($_POST['review_cons'])))));
            $service->saveCons($commentId, $cons);
        }
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
