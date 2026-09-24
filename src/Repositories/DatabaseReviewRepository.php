<?php

namespace Jankx\Extensions\ReviewSystem\Repositories;

use Jankx\Extensions\ReviewSystem\Contracts\ReviewRepositoryInterface;
use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;

/**
 * WordPress database implementation of the review repository backed by the
 * jankx_reviews table. A review row is always linked to a WP comment so the
 * two storages stay in sync (comments are still the moderation surface).
 *
 * @package Jankx\Extensions\ReviewSystem\Repositories
 */
class DatabaseReviewRepository implements ReviewRepositoryInterface
{
    const TABLE_NAME = 'jankx_reviews';

    const RATING_META_KEY = 'jankx_comment_rating';

    public function find(int $id): ?\Jankx\Extensions\ReviewSystem\Models\Review
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tableName()} WHERE id = %d", $id),
            ARRAY_A
        );

        return \Jankx\Extensions\ReviewSystem\Models\Review::fromRow($row ?: null);
    }

    public function findByComment(int $commentId): ?\Jankx\Extensions\ReviewSystem\Models\Review
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tableName()} WHERE comment_id = %d LIMIT 1", $commentId),
            ARRAY_A
        );

        return \Jankx\Extensions\ReviewSystem\Models\Review::fromRow($row ?: null);
    }

    public function findForPost(int $postId, array $criteria = []): array
    {
        global $wpdb;

        $defaults = [
            'status'   => self::STATUS_QUERY_APPROVED,
            'rating'   => 0,
            'order_by' => 'created_at',
            'order'    => 'DESC',
            'limit'    => 20,
            'offset'   => 0,
        ];
        $criteria = wp_parse_args($criteria, $defaults);

        $where = $wpdb->prepare('post_id = %d', $postId);

        if (isset($criteria['status']) && $criteria['status'] !== 'any' && $criteria['status'] !== '') {
            $where .= $wpdb->prepare(' AND status = %s', $criteria['status']);
        }

        if (!empty($criteria['rating'])) {
            $where .= $wpdb->prepare(' AND rating = %d', (int) $criteria['rating']);
        }

        $orderBy = in_array($criteria['order_by'], ['created_at', 'rating', 'id', 'updated_at'], true)
            ? $criteria['order_by']
            : 'created_at';

        $order = strtoupper($criteria['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, (int) $criteria['limit']);
        $offset = max(0, (int) $criteria['offset']);

        $sql = "SELECT * FROM {$this->tableName()}
                WHERE {$where}
                ORDER BY {$orderBy} {$order}
                LIMIT {$limit} OFFSET {$offset}";

        $rows = $wpdb->get_results($sql, ARRAY_A);

        $reviews = [];
        foreach ($rows as $row) {
            $review = \Jankx\Extensions\ReviewSystem\Models\Review::fromRow($row);
            if ($review) {
                $reviews[] = $review;
            }
        }

        return $reviews;
    }

    public function countForPost(int $postId, string $status = self::STATUS_QUERY_APPROVED): int
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->tableName()} WHERE post_id = %d";
        $where = $wpdb->prepare($sql, $postId);

        if ($status !== 'any' && $status !== '') {
            $where = $wpdb->prepare("SELECT COUNT(*) FROM {$this->tableName()} WHERE post_id = %d AND status = %s", $postId, $status);
        }

        return (int) $wpdb->get_var($where);
    }

    public function averageForPost(int $postId, string $status = self::STATUS_QUERY_APPROVED): float
    {
        global $wpdb;

        $sql = "SELECT AVG(rating) FROM {$this->tableName()} WHERE post_id = %d";
        if ($status !== 'any' && $status !== '') {
            $sql .= " AND status = %s";
        }

        $query = $status !== 'any' && $status !== ''
            ? $wpdb->prepare($sql, $postId, $status)
            : $wpdb->prepare($sql, $postId);

        $average = $wpdb->get_var($query);

        return $average !== null ? round((float) $average, 1) : 0.0;
    }

    public function ratingDistribution(int $postId, string $status = self::STATUS_QUERY_APPROVED, int $max = 5): array
    {
        global $wpdb;

        $sql = "SELECT rating, COUNT(*) AS total
                FROM {$this->tableName()}
                WHERE post_id = %d";
        if ($status !== 'any' && $status !== '') {
            $sql .= " AND status = %s";
        }
        $sql .= " GROUP BY rating";

        $query = $status !== 'any' && $status !== ''
            ? $wpdb->prepare($sql, $postId, $status)
            : $wpdb->prepare($sql, $postId);

        $rows = $wpdb->get_results($query, ARRAY_A);

        $dist = [];
        for ($i = 1; $i <= (int) $max; $i++) {
            $dist[$i] = 0;
        }

        foreach ($rows as $row) {
            $rating = (int) $row['rating'];
            if (isset($dist[$rating])) {
                $dist[$rating] = (int) $row['total'];
            }
        }

        return $dist;
    }

    public function findExisting(int $postId, int $userId = 0, string $authorEmail = '', string $authorIp = ''): ?\Jankx\Extensions\ReviewSystem\Models\Review
    {
        global $wpdb;

        $conditions = [];
        $params = [$postId];

        if ($userId > 0) {
            $conditions[] = 'user_id = %d';
            $params[] = $userId;
        }

        if ($authorEmail !== '') {
            $conditions[] = 'author_email = %s';
            $params[] = $authorEmail;
        }

        if ($authorIp !== '') {
            $conditions[] = 'author_ip = %s';
            $params[] = $authorIp;
        }

        if (empty($conditions)) {
            return null;
        }

        $where = 'post_id = %d AND (' . implode(' OR ', $conditions) . ')';
        $sql = "SELECT * FROM {$this->tableName()} WHERE {$where} LIMIT 1";

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return \Jankx\Extensions\ReviewSystem\Models\Review::fromRow($row ?: null);
    }

    public function insert(\Jankx\Extensions\ReviewSystem\Models\Review $review): int
    {
        global $wpdb;

        $result = $wpdb->insert($this->tableName(), $this->buildInsertData($review), $this->buildFormats());
        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update(\Jankx\Extensions\ReviewSystem\Models\Review $review): bool
    {
        if ($review->getId() < 1) {
            return false;
        }

        global $wpdb;

        $data = $this->buildInsertData($review);
        unset($data['comment_id']);

        return (bool) $wpdb->update(
            $this->tableName(),
            $data,
            ['id' => $review->getId()],
            $this->buildUpdateFormats()
        );
    }

    public function updateStatus(int $id, string $status): bool
    {
        if ($id < 1 || !$status) {
            return false;
        }

        global $wpdb;

        return (bool) $wpdb->update(
            $this->tableName(),
            [
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete($this->tableName(), ['id' => (int) $id], ['%d']);
    }

    public function upsertFromComment(int $commentId): ?\Jankx\Extensions\ReviewSystem\Models\Review
    {
        $comment = get_comment($commentId);
        if (!$comment) {
            return null;
        }

        $rating = (int) get_comment_meta($commentId, self::RATING_META_KEY, true);
        if ($rating < 1) {
            return null;
        }

        $postId = (int) $comment->comment_post_ID;

        $pros = get_comment_meta($commentId, ReviewSettings::META_PROS, true);
        $cons = get_comment_meta($commentId, ReviewSettings::META_CONS, true);

        $review = $this->findByComment($commentId);
        if (!$review) {
            $review = new \Jankx\Extensions\ReviewSystem\Models\Review();
            $review->setCommentId($commentId);
            $review->setCreatedAt($comment->comment_date_gmt ?: current_time('mysql'));
        }

        $review
            ->setPostId($postId)
            ->setUserId((int) $comment->user_id)
            ->setRating($rating)
            ->setContent($comment->comment_content)
            ->setPros(is_array($pros) ? $pros : [])
            ->setCons(is_array($cons) ? $cons : [])
            ->setAuthorName($comment->comment_author)
            ->setAuthorEmail($comment->comment_author_email)
            ->setAuthorIp($comment->comment_author_IP ?? '')
            ->setStatus($this->mapCommentStatus($comment->comment_approved))
            ->setUpdatedAt(current_time('mysql'));

        if ($review->getId() > 0) {
            $this->update($review);
            return $review;
        }

        $reviewId = $this->insert($review);
        if ($reviewId < 1) {
            return null;
        }

        $review->setId($reviewId);

        return $review;
    }

    public function deleteByComment(int $commentId): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete($this->tableName(), ['comment_id' => (int) $commentId], ['%d']);
    }

    protected function buildInsertData(\Jankx\Extensions\ReviewSystem\Models\Review $review): array
    {
        $now = current_time('mysql');

        return [
            'comment_id'   => $review->getCommentId(),
            'post_id'      => $review->getPostId(),
            'user_id'      => $review->getUserId(),
            'rating'       => $review->getRating(),
            'content'      => $review->getContent(),
            'pros'         => wp_json_encode(array_values($review->getPros())),
            'cons'         => wp_json_encode(array_values($review->getCons())),
            'author_name'  => $review->getAuthorName(),
            'author_email' => $review->getAuthorEmail(),
            'author_ip'    => $review->getAuthorIp(),
            'status'       => $review->getStatus(),
            'created_at'   => $review->getCreatedAt() ?: $now,
            'updated_at'   => $review->getUpdatedAt() ?: $now,
        ];
    }

    protected function buildFormats(): array
    {
        return ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
    }

    protected function buildUpdateFormats(): array
    {
        return ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
    }

    protected function mapCommentStatus(string $approved): string
    {
        switch ($approved) {
            case '1':
            case 'approve':
                return \Jankx\Extensions\ReviewSystem\Models\Review::STATUS_APPROVED;
            case 'spam':
                return \Jankx\Extensions\ReviewSystem\Models\Review::STATUS_SPAM;
            case 'trash':
            case 'post-trashed':
                return \Jankx\Extensions\ReviewSystem\Models\Review::STATUS_TRASH;
            case '0':
            case 'hold':
            default:
                return \Jankx\Extensions\ReviewSystem\Models\Review::STATUS_PENDING;
        }
    }

    protected function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
}