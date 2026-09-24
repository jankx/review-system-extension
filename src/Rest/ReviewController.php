<?php

namespace Jankx\Extensions\ReviewSystem\Rest;

use Jankx\Extensions\ReviewSystem\Contracts\ReviewRepositoryInterface;
use Jankx\Extensions\ReviewSystem\Models\Review;
use Jankx\Extensions\ReviewSystem\Services\ReviewService;
use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;

/**
 * REST API for the review system. Designed as a thin controller that
 * delegates every use case to the ReviewService (which owns the business
 * rules and the table/comment synchronisation).
 *
 * Endpoints:
 *  - GET    /wp-json/jankx/v1/reviews            -> list + summary
 *  - GET    /wp-json/jankx/v1/reviews/summary    -> aggregate summary
 *  - POST   /wp-json/jankx/v1/reviews            -> submit a review
 *  - PUT    /wp-json/jankx/v1/reviews/{id}       -> moderate status
 *  - DELETE /wp-json/jankx/v1/reviews/{id}       -> delete a review
 *
 * @package Jankx\Extensions\ReviewSystem\Rest
 */
class ReviewController
{
    const REST_NAMESPACE = 'jankx/v1';
    const REST_BASE = '/reviews';

    protected $settings;

    protected $service;

    public function __construct(ReviewSettings $settings, ?ReviewRepositoryInterface $repository = null)
    {
        $this->settings = $settings;
        $this->service = new ReviewService($settings, $repository);
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_BASE, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => '__return_true',
                'args'                => $this->getCollectionArgs(),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createItem'],
                'permission_callback' => [$this, 'createPermission'],
                'args'                => $this->getCreateArgs(),
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getSummary'],
            'permission_callback' => '__return_true',
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateItem'],
                'permission_callback' => [$this, 'managePermission'],
                'args'                => [
                    'status' => [
                        'required' => true,
                        'type'     => 'string',
                        'enum'     => [Review::STATUS_PENDING, Review::STATUS_APPROVED, Review::STATUS_SPAM, Review::STATUS_TRASH],
                    ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'managePermission'],
            ],
        ]);
    }

    /**
     * GET /reviews?post_id=&page=&per_page=&sort=&rating=&status=
     */
    public function getItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $post = get_post($postId);
        if (!$post) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Bài viết không tồn tại.', 'jankx'),
            ], 404);
        }

        $criteria = $this->resolveSort((string) $request->get_param('sort'));
        $criteria['rating'] = (int) $request->get_param('rating');
        $criteria['status'] = sanitize_text_field((string) $request->get_param('status')) ?: Review::STATUS_APPROVED;
        $criteria['offset'] = max(0, ((int) $request->get_param('page') - 1) * (int) $request->get_param('per_page'));

        $reviews = $this->service->getReviews($postId, $criteria);
        $summary = $this->service->getSummary($postId);

        return new \WP_REST_Response([
            'success' => true,
            'reviews' => $reviews,
            'total'   => $summary['count'],
            'summary' => $summary,
        ]);
    }

    /**
     * GET /reviews/summary?post_id=
     */
    public function getSummary(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        if (!get_post($postId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Bài viết không tồn tại.', 'jankx'),
            ], 404);
        }

        $summary = $this->service->getSummary($postId);

        return new \WP_REST_Response([
            'success'                  => true,
            'summary'                  => $summary,
            'post_type_supported'      => $this->settings->isPostTypeSupported(get_post_type($postId)),
            'current_user_reviewed'    => $this->hasUserReviewed($postId),
        ]);
    }

    /**
     * POST /reviews
     */
    public function createItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $post = get_post($postId);
        if (!$post) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Bài viết không tồn tại.', 'jankx'),
            ], 404);
        }

        if (!$this->settings->isPostTypeSupported($post->post_type)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Post type này không hỗ trợ đánh giá.', 'jankx'),
            ], 403);
        }

        $rating = max(1, min($this->service->getMaxRating(), (int) $request->get_param('rating')));

        $userId = get_current_user_id();
        $authorEmail = sanitize_email((string) $request->get_param('author_email'));
        $authorIp = $this->getClientIp();

        $hasReviewed = $this->hasUserReviewed($postId, $userId, $authorEmail, $userId > 0 ? '' : $authorIp);
        if ($hasReviewed) {
            return new \WP_REST_Response([
                'success'  => false,
                'message'  => __('Bạn đã đánh giá bài viết này rồi.', 'jankx'),
                'summary'  => $this->service->getSummary($postId),
            ], 409);
        }

        $review = $this->service->submit([
            'post_id'      => $postId,
            'rating'       => $rating,
            'content'      => (string) $request->get_param('review'),
            'pros'         => (string) $request->get_param('pros'),
            'cons'         => (string) $request->get_param('cons'),
            'author_name'  => (string) $request->get_param('author_name'),
            'author_email' => $authorEmail,
            'author_ip'    => $authorIp,
            'user_id'      => $userId,
        ]);

        if ($review->getId() < 1) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không thể lưu đánh giá. Vui lòng thử lại.', 'jankx'),
            ], 500);
        }

        $summary = $this->service->getSummary($postId);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Đánh giá đã được gửi thành công!', 'jankx'),
            'review'  => $review->toDataArray(),
            'rating'  => [
                'average'    => $summary['average'],
                'count'      => $summary['count'],
                'user_rating' => $rating,
                'comment_id'  => $review->getCommentId(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * PUT /reviews/{id}?status=
     */
    public function updateItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $review = $this->service->getRepository()->find((int) $request['id']);
        if (!$review) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy đánh giá.', 'jankx'),
            ], 404);
        }

        $status = sanitize_text_field((string) $request->get_param('status'));
        $this->service->getRepository()->updateStatus($review->getId(), $status);

        if ($review->getCommentId() > 0) {
            wp_set_comment_status($review->getCommentId(), $this->toCommentStatus($status), true);
        }

        $this->service->recomputeAggregate($review->getPostId());
        $review = $this->service->getRepository()->find($review->getId());

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Đã cập nhật trạng thái đánh giá.', 'jankx'),
            'review'  => $review ? $review->toDataArray() : [],
        ]);
    }

    /**
     * DELETE /reviews/{id}
     */
    public function deleteItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $review = $this->service->getRepository()->find((int) $request['id']);
        if (!$review) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy đánh giá.', 'jankx'),
            ], 404);
        }

        $this->service->getRepository()->delete($review->getId());

        if ($review->getCommentId() > 0) {
            wp_delete_comment($review->getCommentId(), true);
        } else {
            $this->service->recomputeAggregate($review->getPostId());
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Đã xoá đánh giá.', 'jankx'),
        ]);
    }

    public function createPermission(): bool
    {
        return (bool) apply_filters('jankx/review_system/rest_can_submit', true);
    }

    public function managePermission(): bool
    {
        return current_user_can('moderate_comments');
    }

    protected function hasUserReviewed(int $postId, int $userId = 0, string $authorEmail = '', string $authorIp = ''): bool
    {
        $review = $this->service->getRepository()->findExisting($postId, (int) $userId, $authorEmail, $authorIp);
        return $review !== null;
    }

    protected function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        }

        return sanitize_text_field($ip);
    }

    protected function resolveSort(string $sort): array
    {
        if ($sort === '') {
            $sort = $this->settings->getDefaultSort();
        }

        switch ($sort) {
            case 'highest':
                return ['order_by' => 'rating', 'order' => 'DESC', 'number' => 20];
            case 'lowest':
                return ['order_by' => 'rating', 'order' => 'ASC', 'number' => 20];
            case 'oldest':
                return ['order_by' => 'created_at', 'order' => 'ASC', 'number' => 20];
            case 'newest':
            default:
                return ['order_by' => 'created_at', 'order' => 'DESC', 'number' => 20];
        }
    }

    protected function toCommentStatus(string $reviewStatus): string
    {
        switch ($reviewStatus) {
            case Review::STATUS_APPROVED:
                return 'approve';
            case Review::STATUS_SPAM:
                return 'spam';
            case Review::STATUS_TRASH:
                return 'trash';
            case Review::STATUS_PENDING:
            default:
                return 'hold';
        }
    }

    protected function getCollectionArgs(): array
    {
        return [
            'post_id'  => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'page'     => [
                'default'           => 1,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default'           => 20,
                'type'              => 'integer',
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'sort'     => [
                'default' => 'newest',
                'type'    => 'string',
                'enum'    => ['newest', 'oldest', 'highest', 'lowest'],
            ],
            'rating'   => [
                'default'           => 0,
                'type'              => 'integer',
                'minimum'           => 0,
                'maximum'           => 10,
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'default' => Review::STATUS_APPROVED,
                'type'    => 'string',
                'enum'    => [Review::STATUS_APPROVED, Review::STATUS_PENDING, Review::STATUS_SPAM, Review::STATUS_TRASH, 'any'],
            ],
        ];
    }

    protected function getCreateArgs(): array
    {
        return [
            'post_id'      => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'rating'       => [
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 1,
                'maximum'           => 10,
                'sanitize_callback' => 'absint',
            ],
            'review'       => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ],
            'pros'         => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'cons'         => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'author_name'  => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'author_email' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
        ];
    }
}