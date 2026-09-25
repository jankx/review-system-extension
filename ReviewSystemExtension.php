<?php

namespace Jankx\Extensions\ReviewSystem;

use Jankx\Extensions\AbstractExtension;

class ReviewSystemExtension extends AbstractExtension
{
    protected static $instance;

    protected static $supportedPostTypes = [];

    protected $settings;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\ReviewSystem\\';
            $base_dir = __DIR__ . '/src/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        $this->settings = new Services\ReviewSettings();

        Styles::register();

        // Reviews API (jankx_reviews table): installer, REST endpoints.
        (new Database\ReviewDatabaseInstaller())->register();
        (new Rest\ReviewController($this->settings))->register();

        // Extensions declare review support through this channel.
        add_filter('jankx/review_system/supported_post_types', [$this, 'mergeSupportedPostTypes']);

        // Keep the jankx_reviews table in sync with WP comments.
        add_action('comment_post', [$this, 'syncReviewFromComment'], 20, 3);
        add_action('wp_set_comment_status', [$this, 'syncReviewCommentStatus'], 20, 2);
        add_action('deleted_comment', [$this, 'removeSyncedReview'], 20, 1);
        add_action('edit_comment', [$this, 'resyncReviewFromComment'], 20, 1);

        // Form đánh giá bây giờ là một block kéo-thả (jankx/review-form), KHÔNG
        // còn tự động chèn vào comment form nữa.
        add_filter('jankx/comment_rating/show_in_comment_form', '__return_false');

        $reviewDisplay = new Frontend\ReviewDisplay($this->settings);
        $reviewDisplay->register();

        $reviewSummary = new Frontend\ReviewSummary($this->settings);
        $reviewSummary->register();

        $reviewFilter = new Frontend\ReviewFilter($this->settings);
        $reviewFilter->register();

        if (is_admin()) {
            $settingsPage = new Admin\SettingsPage($this->settings);
            $settingsPage->register();
        }

        add_action('init', [$this, 'registerBlocks']);

        // Register MyAccount sub-page BEFORE syncSubPagePosts runs (priority 100).
        // Must use this hook like other extensions, NOT 'init' priority 110.
        add_action('jankx/my_account/register_sub_pages', [$this, 'registerAccountSubPage']);

        // Hook into star rating submissions to sync with review system.
        add_action('jankx/star_rating/submitted', [$this, 'on_star_rating_submitted'], 10, 4);
    }

    /**
     * Let a domain extension declare which post types support reviews.
     *
     * Usage from any extension bootstrap:
     *   ReviewSystemExtension::support_post_type('service');
     */
    public static function support_post_type(...$postTypes): void
    {
        foreach ($postTypes as $postType) {
            if (is_string($postType) && $postType !== '') {
                self::$supportedPostTypes[] = sanitize_text_field($postType);
            }
        }
        self::$supportedPostTypes = array_values(array_unique(self::$supportedPostTypes));
    }

    public static function get_supported_post_types(): array
    {
        return self::$supportedPostTypes;
    }

    public static function is_post_type_supported(string $postType): bool
    {
        return in_array($postType, self::get_supported_post_types(), true);
    }

    public function mergeSupportedPostTypes(array $postTypes): array
    {
        return array_values(array_unique(array_merge($postTypes, self::$supportedPostTypes)));
    }

    public function syncReviewFromComment(int $commentId, int $approved, array $commentData): void
    {
        $postId = (int) ($commentData['comment_post_ID'] ?? 0);
        if (!$postId || !$this->settings->isEnabled()) {
            return;
        }
        if (!$this->settings->isPostTypeSupported(get_post_type($postId))) {
            return;
        }

        (new Services\ReviewService($this->settings))->syncFromComment($commentId);
    }

    public function syncReviewCommentStatus(int $commentId, string $newStatus): void
    {
        if (!$this->isReviewComment($commentId) || !$this->settings->isEnabled()) {
            return;
        }

        (new Services\ReviewService($this->settings))->syncCommentStatus($commentId, $newStatus);
    }

    public function removeSyncedReview(int $commentId): void
    {
        if (!$this->settings->isEnabled()) {
            return;
        }

        (new Services\ReviewService($this->settings))->removeByComment($commentId);
    }

    public function resyncReviewFromComment(int $commentId): void
    {
        if (!$this->isReviewComment($commentId) || !$this->settings->isEnabled()) {
            return;
        }

        (new Services\ReviewService($this->settings))->syncFromComment($commentId);
    }

    protected function isReviewComment(int $commentId): bool
    {
        $comment = get_comment($commentId);
        if (!$comment) {
            return false;
        }

        return $comment->comment_type === 'review'
            || (int) get_comment_meta($commentId, 'jankx_comment_rating', true) > 0;
    }

    /**
     * Handle star rating submissions.
     *
     * When a user submits a star rating via the RatingSubmission API,
     * this method ensures the review-system's aggregate data stays in sync.
     *
     * @param int $commentId
     * @param int $postId
     * @param int $rating
     * @param \WP_REST_Request $request
     */
    public function on_star_rating_submitted(int $commentId, int $postId, int $rating, $request): void
    {
        // The RatingSubmission handler already saves via RatingRepository,
        // which updates jankx_rating_average, jankx_rating_count, and
        // syncs legacy meta (_tour_rating, _experience_rating, _place_rating).
        //
        // If review-system's ReviewSummary block or shortcode is used on
        // the page, it reads from jankx_rating_average/jankx_rating_count
        // so no additional sync is needed here.
        //
        // This hook exists for extensions to add custom logic, e.g.:
        // - Send notification emails
        // - Update custom analytics
        // - Trigger webhook integrations
        do_action('jankx/review_system/rating_synced', $commentId, $postId, $rating);
    }

    public function registerAccountSubPage(): void
    {
        if (!class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension')) {
            return;
        }

        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPageClass(new MyAccount\ReviewsSubPage());
    }

    public function registerBlocks(): void
    {
        $blocksDir = __DIR__ . '/blocks';
        if (!is_dir($blocksDir)) {
            return;
        }

        $reviewBlocks = [
            'jankx/account-tab-reviews' => Blocks\AccountTabReviewsBlock::class,
            'jankx/reviews-my-reviews' => Blocks\ReviewsMyReviewsBlock::class,
            'jankx/reviews-pending' => Blocks\ReviewsPendingBlock::class,
            'jankx/reviews-completed' => Blocks\ReviewsCompletedBlock::class,
            'jankx/review-form' => Blocks\ReviewFormBlock::class,
        ];

        foreach (glob($blocksDir . '/*', GLOB_ONLYDIR) as $blockDir) {
            if (!file_exists($blockDir . '/block.json')) {
                continue;
            }

            $blockJson = json_decode(file_get_contents($blockDir . '/block.json'), true);
            $blockName = $blockJson['name'] ?? '';

            if ($blockName && !\WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                $args = [];
                if (isset($reviewBlocks[$blockName])) {
                    $blockClass = $reviewBlocks[$blockName];
                    $block = new $blockClass($blockDir);
                    $block->setBlockPath($blockDir);
                    $block->boot();
                    $block->register();
                    continue;
                }

                if ($blockName === 'jankx/review-summary') {
                    $summaryBlock = new Blocks\ReviewSummaryBlock();
                    $args['render_callback'] = [$summaryBlock, 'render'];
                }
                register_block_type_from_metadata($blockDir, $args);
            }
        }
    }
}
