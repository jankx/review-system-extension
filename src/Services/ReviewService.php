<?php

namespace Jankx\Extensions\ReviewSystem\Services;

use Jankx\Extensions\CommentRating\Rating\RatingRepository;
use Jankx\Extensions\ReviewSystem\Contracts\ReviewRepositoryInterface;
use Jankx\Extensions\ReviewSystem\Models\Review;
use Jankx\Extensions\ReviewSystem\Repositories\DatabaseReviewRepository;

/**
 * Review use-case facade. Extensions call this service (or the REST API it
 * backs) to read, submit and moderate reviews for their post types.
 *
 * A review is persisted twice in parallel:
 *  - a WordPress comment (moderation surface, comment-rating aggregates)
 *  - a row in the jankx_reviews table (linked to the post and the comment)
 *
 * @package Jankx\Extensions\ReviewSystem\Services
 */
class ReviewService
{
    protected $settings;

    protected $repository;

    public function __construct(ReviewSettings $settings, ?ReviewRepositoryInterface $repository = null)
    {
        $this->settings = $settings;
        $this->repository = $repository ?: new DatabaseReviewRepository();
    }

    public function getRepository(): ReviewRepositoryInterface
    {
        return $this->repository;
    }

    // ------------------------------------------------------------------
    // Write path (comment + table kept in sync)
    // ------------------------------------------------------------------

    /**
     * Submit a brand new review. Creates the WP comment, stores rating and
     * pros/cons metadata, then mirrors the row into jankx_reviews.
     *
     * @param array $data post_id, rating, content, pros, cons, author_name,
     *                    author_email, user_id
     */
    public function submit(array $data): Review
    {
        $postId = (int) ($data['post_id'] ?? 0);
        $rating = max(1, min($this->getMaxRating(), (int) ($data['rating'] ?? 0)));
        $userId = (int) ($data['user_id'] ?? get_current_user_id());
        $orderId = (int) ($data['order_id'] ?? 0);

        $content = sanitize_textarea_field((string) ($data['content'] ?? ''));
        if ($content === '') {
            $content = $content ?: sprintf(__('Đánh giá %d/%d', 'jankx'), $rating, $this->getMaxRating());
        }
        $content = wp_kses_post($content);

        $pros = $this->normalizeLines($data['pros'] ?? []);
        $cons = $this->normalizeLines($data['cons'] ?? []);

        $authorEmail = sanitize_email((string) ($data['author_email'] ?? ''));
        if ($authorEmail === '' && $userId) {
            $userData = get_userdata($userId);
            if ($userData) {
                $authorEmail = sanitize_email($userData->user_email);
            }
        }
        $authorName = trim($data['author_name'] ?? '');
        if ($authorName === '') {
            $authorName = $userId ? wp_get_current_user()->display_name : __('Khách', 'jankx');
        }

        $commentId = wp_insert_comment([
            'comment_post_ID'      => $postId,
            'comment_content'      => $content,
            'comment_type'         => 'review',
            'comment_approved'     => '1',
            'user_id'              => $userId,
            'comment_author'       => sanitize_text_field($authorName),
            'comment_author_email' => $authorEmail,
            'comment_author_IP'    => sanitize_text_field((string) ($data['author_ip'] ?? '')),
        ]);

        if (!$commentId || is_wp_error($commentId)) {
            return new Review();
        }

        if ($orderId > 0) {
            update_comment_meta($commentId, ReviewSettings::META_ORDER, $orderId);
        }

        $this->attachMedia($commentId, $postId, $data['media_ids'] ?? []);

        $repository = new RatingRepository();
        $repository->save($commentId, $postId, $rating);

        if ($pros) {
            $this->savePros($commentId, $pros);
        }
        if ($cons) {
            $this->saveCons($commentId, $cons);
        }

        $review = $this->repository->upsertFromComment($commentId);
        if ($review && $orderId > 0 && $review->getOrderId() !== $orderId) {
            $review->setOrderId($orderId);
            $this->repository->update($review);
        }

        do_action('jankx/star_rating/submitted', $commentId, $postId, $rating, null);
        do_action('jankx/review_system/review_submitted', $review, $postId, $rating, $data);

        return $review ?: new Review();
    }

    protected function attachMedia(int $commentId, int $postId, $mediaIds): void
    {
        $mediaIds = array_values(array_filter(array_map('absint', (array) $mediaIds)));
        if (!$mediaIds) {
            return;
        }

        $metaKey = 'jankx_media_attachment_ids';
        $maxFiles = 0;

        if (class_exists('\Jankx\Extensions\CommentMedia\CommentMediaExtension')) {
            $extension = \Jankx\Extensions\CommentMedia\CommentMediaExtension::get_instance();
            if ($extension) {
                $metaKey = $extension::COMMENT_META_KEY;
                $maxFiles = $extension->getMaxFiles();
            }
        }

        if ($maxFiles > 0) {
            $mediaIds = array_slice($mediaIds, 0, $maxFiles);
        }

        $attachmentIds = [];
        foreach ($mediaIds as $attachmentId) {
            if (get_post_type($attachmentId) !== 'attachment') {
                continue;
            }
            if ((string) get_post_meta($attachmentId, '_is_comment_media', true) !== '1') {
                continue;
            }
            $attachmentIds[] = $attachmentId;
        }

        if (!$attachmentIds) {
            return;
        }

        update_comment_meta($commentId, $metaKey, $attachmentIds);

        foreach ($attachmentIds as $attachmentId) {
            update_post_meta($attachmentId, '_comment_media_orphan', '0');
            update_post_meta($attachmentId, '_comment_media_comment_id', $commentId);
            update_post_meta($attachmentId, '_comment_media_post_id', $postId);
        }
    }

    /**
     * Mirror an existing review comment into the table (comment form path).
     */
    public function syncFromComment(int $commentId): ?Review
    {
        $review = $this->repository->upsertFromComment($commentId);
        if ($review) {
            do_action('jankx/review_system/review_synced', $review, $commentId);
        }
        return $review;
    }

    /**
     * Reflect a comment status change onto its review row.
     */
    public function syncCommentStatus(int $commentId, string $newStatus): void
    {
        $this->repository->upsertFromComment($commentId);
        $this->recomputeAggregateFromComment($commentId);
    }

    /**
     * Remove the review row when its comment is deleted.
     */
    public function removeByComment(int $commentId): void
    {
        $postId = (int) get_comment_meta($commentId, RatingRepository::COMMENT_POST_KEY, true);
        if (!$postId) {
            $comment = get_comment($commentId);
            $postId = $comment ? (int) $comment->comment_post_ID : 0;
        }

        $this->repository->deleteByComment($commentId);

        if ($postId) {
            $this->recomputeAggregate($postId);
        }
    }

    public function recomputeAggregate(int $postId): void
    {
        (new RatingRepository())->recomputeAggregate($postId);
    }

    protected function recomputeAggregateFromComment(int $commentId): void
    {
        $postId = (int) get_comment_meta($commentId, RatingRepository::COMMENT_POST_KEY, true);
        if (!$postId) {
            $comment = get_comment($commentId);
            $postId = $comment ? (int) $comment->comment_post_ID : 0;
        }
        if ($postId) {
            $this->recomputeAggregate($postId);
        }
    }

    public function savePros(int $commentId, array $pros): void
    {
        $cleaned = array_map('sanitize_text_field', array_filter($pros));
        update_comment_meta($commentId, ReviewSettings::META_PROS, $cleaned);
    }

    public function saveCons(int $commentId, array $cons): void
    {
        $cleaned = array_map('sanitize_text_field', array_filter($cons));
        update_comment_meta($commentId, ReviewSettings::META_CONS, $cleaned);
    }

    public function getPros(int $commentId): array
    {
        $pros = get_comment_meta($commentId, ReviewSettings::META_PROS, true);
        return is_array($pros) ? $pros : [];
    }

    public function getCons(int $commentId): array
    {
        $cons = get_comment_meta($commentId, ReviewSettings::META_CONS, true);
        return is_array($cons) ? $cons : [];
    }

    // ------------------------------------------------------------------
    // Read path (table backed, falls back to legacy comment aggregates)
    // ------------------------------------------------------------------

    public function getSummary(int $postId): array
    {
        return [
            'average'      => $this->getAverage($postId),
            'count'        => $this->getCount($postId),
            'distribution' => $this->getRatingDistribution($postId),
            'max_rating'   => $this->getMaxRating(),
        ];
    }

    public function getAverage(int $postId): float
    {
        if ($this->repository->countForPost($postId, Review::STATUS_APPROVED) === 0) {
            $legacy = new RatingRepository();
            if ($legacy->getCount($postId) > 0) {
                return (float) $legacy->getAverage($postId);
            }
        }

        return $this->repository->averageForPost($postId, Review::STATUS_APPROVED);
    }

    public function getCount(int $postId): int
    {
        $count = $this->repository->countForPost($postId, Review::STATUS_APPROVED);
        if ($count > 0) {
            return $count;
        }

        $legacy = new RatingRepository();
        return $legacy->getCount($postId);
    }

    public function getRatingDistribution(int $postId): array
    {
        if ($this->repository->countForPost($postId, Review::STATUS_APPROVED) === 0) {
            $legacy = new RatingRepository();
            if ($legacy->getCount($postId) > 0) {
                return $this->distributionFromValues($legacy->getValues($postId));
            }
        }

        return $this->repository->ratingDistribution($postId, Review::STATUS_APPROVED, $this->getMaxRating());
    }

    public function getMaxRating(): int
    {
        if (class_exists('\Jankx\Extensions\CommentRating\Admin\Settings')) {
            return \Jankx\Extensions\CommentRating\Admin\Settings::getMaxRating();
        }
        return 5;
    }

    /**
     * @return array[] Array of review data arrays.
     */
    public function getReviews(int $postId, array $args = []): array
    {
        $defaults = [
            'status'   => Review::STATUS_APPROVED,
            'order_by' => 'created_at',
            'order'    => 'DESC',
            'number'   => 20,
            'offset'   => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $reviews = $this->repository->findForPost($postId, [
            'status'   => $args['status'],
            'rating'   => (int) ($args['rating'] ?? 0),
            'order_by' => $args['order_by'],
            'order'    => $args['order'],
            'limit'    => (int) $args['number'],
            'offset'   => (int) $args['offset'],
        ]);

        return array_map(function (Review $review) {
            return $review->toDataArray();
        }, $reviews);
    }

    public function getSortedReviews(int $postId, string $sortBy = 'newest'): array
    {
        return $this->getReviews($postId, $this->sortCriteria($sortBy));
    }

    public function getFilteredReviews(int $postId, array $filters = [], string $sortBy = 'newest'): array
    {
        $criteria = $this->sortCriteria($sortBy);
        if (!empty($filters['rating'])) {
            $criteria['rating'] = (int) $filters['rating'];
        }

        return $this->getReviews($postId, $criteria);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function sortCriteria(string $sortBy): array
    {
        switch ($sortBy) {
            case 'highest':
                return ['order_by' => 'rating', 'order' => 'DESC'];
            case 'lowest':
                return ['order_by' => 'rating', 'order' => 'ASC'];
            case 'oldest':
                return ['order_by' => 'created_at', 'order' => 'ASC'];
            case 'newest':
            default:
                return ['order_by' => 'created_at', 'order' => 'DESC'];
        }
    }

    protected function normalizeLines($input): array
    {
        if (is_string($input)) {
            $input = explode("\n", $input);
        }
        if (!is_array($input)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($line) {
            return sanitize_text_field(trim((string) $line));
        }, $input)));
    }

    protected function distributionFromValues(array $values): array
    {
        $dist = [];
        $max = $this->getMaxRating();
        for ($i = 1; $i <= $max; $i++) {
            $dist[$i] = 0;
        }
        foreach ($values as $rating) {
            $r = (int) $rating;
            if (isset($dist[$r])) {
                $dist[$r]++;
            }
        }
        return $dist;
    }
}