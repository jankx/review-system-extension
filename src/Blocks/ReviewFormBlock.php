<?php

namespace Jankx\Extensions\ReviewSystem\Blocks;

use Jankx\Extensions\ReviewSystem\Block;
use Jankx\Extensions\ReviewSystem\ReviewSystemExtension;
use Jankx\Extensions\ReviewSystem\Services\PurchaseService;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;
use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;

/**
 * Block "Đánh giá của bạn" (jankx/review-form).
 *
 * Form đánh giá kéo-thả vào layout. Gửi review qua REST API
 * POST /wp-json/jankx/v1/reviews. Chỉ hiển thị khi người dùng đăng nhập và
 * (khi bật cấu hình) đã mua sản phẩm thành công — đơn hàng phải ở trạng thái
 * completed (tương thích base-ecommerce).
 *
 * @package Jankx\Extensions\ReviewSystem\Blocks
 */
class ReviewFormBlock extends Block
{
    protected $blockId = 'jankx/review-form';

    protected $settings;

    protected $service;

    public function __construct($blockPath = null)
    {
        parent::__construct($blockPath);
        $this->settings = new ReviewSettings();
        $this->service = new ReviewService($this->settings);
    }

    public function render($attributes, $content = '', $block = null)
    {
        if (!$this->settings->isEnabled()) {
            return '';
        }

        $postId = $this->resolvePostId($attributes, $block);
        if (!$postId) {
            return '';
        }

        $postType = get_post_type($postId);
        if (!$postType || !$this->settings->isPostTypeSupported($postType)) {
            return '';
        }

        $maxRating = $this->resolveMaxRating($attributes);
        $requirePurchase = $this->resolvePurchaseRequirement($attributes);
        $userId = get_current_user_id();

        // Người dùng đã đánh giá bài viết này.
        if ($this->currentUserReviewed($postId)) {
            return $this->renderAlreadyReviewed($postId, $requirePurchase);
        }

        // Yêu cầu mua hàng: chỉ hiển thị form khi đã mua thành công.
        if ($requirePurchase) {
            if (!$userId) {
                return $this->renderLoginPrompt($postId);
            }

            $purchaseService = new PurchaseService();
            if (!$purchaseService->canReview($userId, $postId, true)) {
                return $this->renderPurchaseRequired($postId);
            }
        }

        return $this->renderForm($postId, $maxRating, $requirePurchase, $attributes);
    }

    protected function renderForm(int $postId, int $maxRating, bool $requirePurchase, array $attributes): string
    {
        $this->enqueueAssets($postId, $maxRating, $requirePurchase);

        $title = (string) ($attributes['title'] ?? '');
        if ($title === '') {
            $title = __('Đánh giá của bạn', 'jankx');
        }
        $submitText = (string) ($attributes['submitText'] ?? '');
        if ($submitText === '') {
            $submitText = __('Gửi đánh giá', 'jankx');
        }

        $showReviewField = (bool) ($attributes['showReviewField'] ?? true);
        $showProsCons = (bool) ($attributes['showProsCons'] ?? ($this->settings->showPros() || $this->settings->showCons()));
        $showLoginPrompt = (bool) ($attributes['showLoginPrompt'] ?? true);

        $isLoggedIn = is_user_logged_in();
        $currentUser = wp_get_current_user();
        $uniqueId = 'review-form-' . $postId . '-' . wp_generate_password(6, false);

        $wrapperAttrs = get_block_wrapper_attributes([
            'id'    => 'reviewform',
            'class' => 'wp-block-jankx-review-form jankx-review-form',
        ]);

        ob_start();
        ?>
        <div <?php echo $wrapperAttrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-form-id="<?php echo esc_attr($uniqueId); ?>">
            <?php if ($title) : ?>
                <h3 class="jankx-review-form__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <div class="jankx-review-form__body" data-form-id="<?php echo esc_attr($uniqueId); ?>">
                <?php if (!$isLoggedIn && $showLoginPrompt) : ?>
                    <div class="jankx-review-form__guest-notice">
                        <p>
                            <?php esc_html_e('Bạn chưa đăng nhập?', 'jankx'); ?>
                            <a href="<?php echo esc_url(wp_login_url(get_permalink($postId))); ?>">
                                <?php esc_html_e('Đăng nhập', 'jankx'); ?>
                            </a>
                            <?php esc_html_e('để đánh giá.', 'jankx'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($isLoggedIn) : ?>
                    <div class="jankx-review-form__user-info">
                        <span class="jankx-review-form__avatar"><?php echo get_avatar($currentUser->ID, 32); ?></span>
                        <span class="jankx-review-form__username"><?php echo esc_html($currentUser->display_name); ?></span>
                    </div>
                <?php endif; ?>

                <div class="jankx-review-form__stars" data-max-rating="<?php echo esc_attr($maxRating); ?>">
                    <?php for ($i = 1; $i <= $maxRating; $i++) : ?>
                        <button type="button"
                            class="jankx-review-form__star"
                            data-value="<?php echo esc_attr($i); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('%d sao', 'jankx'), $i)); ?>"
                            title="<?php echo esc_attr(sprintf(__('%d sao', 'jankx'), $i)); ?>">★</button>
                    <?php endfor; ?>
                    <span class="jankx-review-form__rating-text"></span>
                </div>

                <?php if ($showReviewField) : ?>
                    <div class="jankx-review-form__field">
                        <textarea
                            class="jankx-review-form__textarea jankx-review-form__textarea--review"
                            placeholder="<?php esc_attr_e('Viết nhận xét của bạn...', 'jankx'); ?>"
                            rows="4"></textarea>
                    </div>
                <?php endif; ?>

                <?php if ($showProsCons) : ?>
                    <div class="jankx-review-form__pros-cons">
                        <div class="jankx-review-form__field">
                            <label class="jankx-review-form__label jankx-review-form__label--pros" for="<?php echo esc_attr($uniqueId); ?>-pros">
                                <?php esc_html_e('Điểm mạnh', 'jankx'); ?>
                            </label>
                            <textarea
                                id="<?php echo esc_attr($uniqueId); ?>-pros"
                                class="jankx-review-form__textarea jankx-review-form__textarea--pros"
                                placeholder="<?php esc_attr_e('Mỗi dòng một ý...', 'jankx'); ?>"
                                rows="3"></textarea>
                        </div>
                        <div class="jankx-review-form__field">
                            <label class="jankx-review-form__label jankx-review-form__label--cons" for="<?php echo esc_attr($uniqueId); ?>-cons">
                                <?php esc_html_e('Điểm yếu', 'jankx'); ?>
                            </label>
                            <textarea
                                id="<?php echo esc_attr($uniqueId); ?>-cons"
                                class="jankx-review-form__textarea jankx-review-form__textarea--cons"
                                placeholder="<?php esc_attr_e('Mỗi dòng một ý...', 'jankx'); ?>"
                                rows="3"></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isLoggedIn && !$requirePurchase) : ?>
                    <div class="jankx-review-form__guest-fields">
                        <div class="jankx-review-form__field">
                            <input
                                type="text"
                                class="jankx-review-form__input jankx-review-form__input--name"
                                placeholder="<?php esc_attr_e('Họ tên (tùy chọn)', 'jankx'); ?>" />
                        </div>
                        <div class="jankx-review-form__field">
                            <input
                                type="email"
                                class="jankx-review-form__input jankx-review-form__input--email"
                                placeholder="<?php esc_attr_e('Email (tùy chọn)', 'jankx'); ?>" />
                        </div>
                    </div>
                <?php endif; ?>

                <div class="jankx-review-form__submit">
                    <button class="jankx-review-form__button" type="button"><?php echo esc_html($submitText); ?></button>
                    <span class="jankx-review-form__spinner" style="display:none;">
                        <span class="spinner is-active"></span>
                    </span>
                </div>

                <div class="jankx-review-form__message" style="display:none;"></div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    protected function renderLoginPrompt(int $postId): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'id'    => 'reviewform',
            'class' => 'wp-block-jankx-review-form jankx-review-form jankx-review-form--login',
        ]);

        return sprintf(
            '<div %s><div class="jankx-review-form__notice">%s</div></div>',
            $wrapperAttrs,
            sprintf(
                /* translators: %s: login link. */
                __('Bạn cần <a href="%s">đăng nhập</a> và mua sản phẩm thành công để viết đánh giá.', 'jankx'),
                esc_url(wp_login_url(get_permalink($postId)))
            )
        );
    }

    protected function renderPurchaseRequired(int $postId): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'id'    => 'reviewform',
            'class' => 'wp-block-jankx-review-form jankx-review-form jankx-review-form--purchase-required',
        ]);

        $html = sprintf(
            '<div %s><div class="jankx-review-form__notice">%s</div></div>',
            $wrapperAttrs,
            esc_html__('Bạn chỉ có thể viết đánh giá sau khi mua sản phẩm này thành công.', 'jankx')
        );

        return apply_filters('jankx/review_system/purchase_required_notice', $html, $postId);
    }

    protected function renderAlreadyReviewed(int $postId, bool $requirePurchase): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'id'    => 'reviewform',
            'class' => 'wp-block-jankx-review-form jankx-review-form jankx-review-form--already-reviewed',
        ]);

        $message = __('Cảm ơn bạn! Bạn đã đánh giá sản phẩm này.', 'jankx');
        if (class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension')) {
            $accountPageId = (int) get_option('jankx_my_account_page_id', 0);
            if ($accountPageId) {
                $reviewsUrl = trailingslashit((string) get_permalink($accountPageId)) . 'reviews/';
                $message = sprintf(
                    __('Cảm ơn bạn! Bạn đã đánh giá sản phẩm này. Xem tại <a href="%s">Đánh giá của bạn</a>.', 'jankx'),
                    esc_url($reviewsUrl)
                );
            }
        }

        return sprintf(
            '<div %s><div class="jankx-review-form__notice jankx-review-form__notice--success">%s</div></div>',
            $wrapperAttrs,
            $message
        );
    }

    protected function currentUserReviewed(int $postId): bool
    {
        $userId = get_current_user_id();
        if ($userId < 1) {
            return false;
        }

        return $this->service->getRepository()->findExisting($postId, $userId) !== null;
    }

    protected function enqueueAssets(int $postId, int $maxRating, bool $requirePurchase): void
    {
        $extension = ReviewSystemExtension::get_instance();
        if (!$extension) {
            return;
        }

        $scriptUrl = $extension->get_extension_url() . '/blocks/review-form/frontend.js';
        $scriptPath = $extension->get_extension_path() . '/blocks/review-form/frontend.js';

        wp_enqueue_script(
            'jankx-review-form-frontend',
            $scriptUrl,
            [],
            file_exists($scriptPath) ? filemtime($scriptPath) : '1.0.0',
            true
        );

        $currentUser = wp_get_current_user();
        $isLoggedIn = is_user_logged_in();

        wp_localize_script('jankx-review-form-frontend', 'jankxReviewForm', [
            'postId'          => $postId,
            'maxRating'       => $maxRating,
            'requirePurchase' => $requirePurchase,
            'isLoggedIn'      => $isLoggedIn,
            'restUrl'         => esc_url_raw(rest_url('jankx/v1/reviews')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'userName'        => $isLoggedIn ? $currentUser->display_name : '',
            'userEmail'       => $isLoggedIn ? $currentUser->user_email : '',
            'i18n'            => [
                'selectRating'    => __('Vui lòng chọn số sao.', 'jankx'),
                'submitting'      => __('Đang gửi...', 'jankx'),
                'success'         => __('Cảm ơn bạn đã đánh giá!', 'jankx'),
                'error'           => __('Có lỗi xảy ra, vui lòng thử lại.', 'jankx'),
                'loginRequired'   => __('Vui lòng đăng nhập để đánh giá.', 'jankx'),
                'purchaseRequired'=> __('Bạn cần mua sản phẩm thành công để đánh giá.', 'jankx'),
                'alreadyRated'    => __('Bạn đã đánh giá sản phẩm này rồi.', 'jankx'),
            ],
        ]);
    }

    protected function resolvePostId(array $attributes, $block): int
    {
        $postId = (int) ($attributes['postId'] ?? 0);
        if ($postId > 0) {
            return $postId;
        }

        if (is_object($block) && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }

        $postId = get_the_ID();
        if ($postId) {
            return (int) $postId;
        }

        $queried = get_queried_object_id();
        return $queried ? (int) $queried : 0;
    }

    protected function resolveMaxRating(array $attributes): int
    {
        $maxRating = (int) ($attributes['maxRating'] ?? 0);
        if ($maxRating >= 3 && $maxRating <= 10) {
            return $maxRating;
        }

        return $this->service->getMaxRating();
    }

    protected function resolvePurchaseRequirement(array $attributes): bool
    {
        if (array_key_exists('requirePurchase', $attributes)) {
            return (bool) $attributes['requirePurchase'];
        }

        return $this->settings->requirePurchase();
    }
}