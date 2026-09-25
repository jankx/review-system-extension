<?php

namespace Jankx\Extensions\ReviewSystem\Contracts;

use Jankx\Extensions\ReviewSystem\Models\Review;

/**
 * Contract for review persistence backed by the jankx_reviews table.
 *
 * Extensions and services depend on this interface instead of the concrete
 * repository so the storage strategy can be swapped freely.
 *
 * @package Jankx\Extensions\ReviewSystem\Contracts
 */
interface ReviewRepositoryInterface
{
    const STATUS_QUERY_APPROVED = 'approved';

    /**
     * Find a single review by its table id.
     */
    public function find(int $id): ?Review;

    /**
     * Find a single review linked to a WordPress comment id.
     */
    public function findByComment(int $commentId): ?Review;

    /**
     * Find the review a user left for a specific order + post.
     */
    public function findByOrderAndPost(int $orderId, int $postId): ?Review;

    /**
     * Return the order ids from $orderIds that already have a review for $postId.
     *
     * @param int[] $orderIds
     * @return int[]
     */
    public function findReviewedOrderIdsForPost(array $orderIds, int $postId): array;

    /**
     * Find reviews for a post with optional criteria.
     *
     * @return Review[]
     */
    public function findForPost(int $postId, array $criteria = []): array;

    /**
     * Count approved (or any status) reviews for a post.
     */
    public function countForPost(int $postId, string $status = self::STATUS_QUERY_APPROVED): int;

    /**
     * Average rating for a post.
     */
    public function averageForPost(int $postId, string $status = self::STATUS_QUERY_APPROVED): float;

    /**
     * Rating distribution (rating => count) for a post.
     */
    public function ratingDistribution(int $postId, string $status = self::STATUS_QUERY_APPROVED, int $max = 5): array;

    /**
     * Find the first existing review for a post by user id, author email or IP.
     */
    public function findExisting(int $postId, int $userId = 0, string $authorEmail = '', string $authorIp = ''): ?Review;

    /**
     * Insert a new review, return its table id (0 on failure).
     */
    public function insert(Review $review): int;

    /**
     * Update an existing review row.
     */
    public function update(Review $review): bool;

    /**
     * Update the status of a review.
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Delete a review row by id.
     */
    public function delete(int $id): bool;

    /**
     * Create or refresh a review row from an existing WordPress comment.
     * Returns null when the comment carries no valid rating.
     */
    public function upsertFromComment(int $commentId): ?Review;

    /**
     * Remove the review row linked to a comment id.
     */
    public function deleteByComment(int $commentId): bool;
}