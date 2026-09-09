<?php

namespace Jankx\Extensions\ReviewSystem\Services;

use Jankx\Extensions\CommentRating\Rating\RatingRepository;

class ReviewService
{
    protected $settings;

    public function __construct(ReviewSettings $settings)
    {
        $this->settings = $settings;
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

    public function getRatingDistribution(int $postId): array
    {
        $repository = new RatingRepository();
        $values = $repository->getValues($postId);
        $max = $this->getMaxRating();

        $dist = [];
        for ($i = 1; $i <= $max; $i++) {
            $dist[$i] = 0;
        }

        foreach ($values as $rating) {
            $r = (int) $rating;
            if ($r >= 1 && $r <= $max) {
                $dist[$r]++;
            }
        }

        return $dist;
    }

    public function getAverage(int $postId): float
    {
        $repository = new RatingRepository();
        return (float) $repository->getAverage($postId);
    }

    public function getCount(int $postId): int
    {
        $repository = new RatingRepository();
        return (int) $repository->getCount($postId);
    }

    public function getMaxRating(): int
    {
        if (class_exists('\Jankx\Extensions\CommentRating\Admin\Settings')) {
            return \Jankx\Extensions\CommentRating\Admin\Settings::getMaxRating();
        }
        return 5;
    }

    public function getReviews(int $postId, array $args = []): array
    {
        $defaults = [
            'status' => 'approve',
            'post_id' => $postId,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
            'number' => 20,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $comments = get_comments($args);

        $reviews = [];
        foreach ($comments as $comment) {
            $rating = (int) get_comment_meta($comment->comment_ID, 'jankx_comment_rating', true);
            if ($rating < 1) {
                continue;
            }

            $review = [
                'id' => $comment->comment_ID,
                'rating' => $rating,
                'pros' => $this->getPros($comment->comment_ID),
                'cons' => $this->getCons($comment->comment_ID),
                'author' => $comment->comment_author,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
                'avatar' => get_avatar_url($comment->comment_author_email, ['size' => 48]),
            ];
            $reviews[] = $review;
        }

        return $reviews;
    }

    public function getSortedReviews(int $postId, string $sortBy = 'newest'): array
    {
        switch ($sortBy) {
            case 'highest':
                $comments = get_comments([
                    'post_id' => $postId,
                    'status' => 'approve',
                    'meta_key' => 'jankx_comment_rating',
                    'orderby' => 'meta_value_num',
                    'order' => 'DESC',
                    'number' => 100,
                ]);
                break;
            case 'lowest':
                $comments = get_comments([
                    'post_id' => $postId,
                    'status' => 'approve',
                    'meta_key' => 'jankx_comment_rating',
                    'orderby' => 'meta_value_num',
                    'order' => 'ASC',
                    'number' => 100,
                ]);
                break;
            case 'oldest':
                $comments = get_comments([
                    'post_id' => $postId,
                    'status' => 'approve',
                    'orderby' => 'comment_date_gmt',
                    'order' => 'ASC',
                    'number' => 100,
                ]);
                break;
            case 'newest':
            default:
                $comments = get_comments([
                    'post_id' => $postId,
                    'status' => 'approve',
                    'orderby' => 'comment_date_gmt',
                    'order' => 'DESC',
                    'number' => 100,
                ]);
                break;
        }

        $reviews = [];
        foreach ($comments as $comment) {
            $rating = (int) get_comment_meta($comment->comment_ID, 'jankx_comment_rating', true);
            if ($rating < 1) {
                continue;
            }
            $reviews[] = [
                'id' => $comment->comment_ID,
                'rating' => $rating,
                'pros' => $this->getPros($comment->comment_ID),
                'cons' => $this->getCons($comment->comment_ID),
                'author' => $comment->comment_author,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
                'avatar' => get_avatar_url($comment->comment_author_email, ['size' => 48]),
            ];
        }

        return $reviews;
    }

    public function getFilteredReviews(int $postId, array $filters = [], string $sortBy = 'newest'): array
    {
        $args = [
            'post_id' => $postId,
            'status' => 'approve',
            'number' => 100,
        ];

        if (!empty($filters['rating'])) {
            $rating = (int) $filters['rating'];
            $args['meta_key'] = 'jankx_comment_rating';
            $args['meta_value'] = $rating;
        }

        switch ($sortBy) {
            case 'highest':
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                if (empty($args['meta_key'])) {
                    $args['meta_key'] = 'jankx_comment_rating';
                }
                break;
            case 'lowest':
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                if (empty($args['meta_key'])) {
                    $args['meta_key'] = 'jankx_comment_rating';
                }
                break;
            case 'oldest':
                $args['orderby'] = 'comment_date_gmt';
                $args['order'] = 'ASC';
                break;
            case 'newest':
            default:
                $args['orderby'] = 'comment_date_gmt';
                $args['order'] = 'DESC';
                break;
        }

        $comments = get_comments($args);

        $reviews = [];
        foreach ($comments as $comment) {
            $rating = (int) get_comment_meta($comment->comment_ID, 'jankx_comment_rating', true);
            if ($rating < 1) {
                continue;
            }
            $reviews[] = [
                'id' => $comment->comment_ID,
                'rating' => $rating,
                'pros' => $this->getPros($comment->comment_ID),
                'cons' => $this->getCons($comment->comment_ID),
                'author' => $comment->comment_author,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
                'avatar' => get_avatar_url($comment->comment_author_email, ['size' => 48]),
            ];
        }

        return $reviews;
    }
}
