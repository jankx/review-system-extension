<?php

namespace Jankx\Extensions\ReviewSystem;

use Jankx\Extensions\AbstractExtension;

class ReviewSystemExtension extends AbstractExtension
{
    protected static $instance;

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
        $settings = new Services\ReviewSettings();

        Styles::register();

        $reviewForm = new Frontend\ReviewForm($settings);
        $reviewForm->register();

        $reviewDisplay = new Frontend\ReviewDisplay($settings);
        $reviewDisplay->register();

        $reviewSummary = new Frontend\ReviewSummary($settings);
        $reviewSummary->register();

        $reviewFilter = new Frontend\ReviewFilter($settings);
        $reviewFilter->register();

        if (is_admin()) {
            $settingsPage = new Admin\SettingsPage($settings);
            $settingsPage->register();
        }

        add_action('init', [$this, 'registerBlocks']);
        add_action('init', [$this, 'registerAccountSubPage'], 110);

        // Hook into star rating submissions to sync with review system.
        add_action('jankx/star_rating/submitted', [$this, 'on_star_rating_submitted'], 10, 4);
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
