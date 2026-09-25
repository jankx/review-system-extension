<?php

namespace Jankx\Extensions\ReviewSystem\Database;

use Jankx\Extensions\ReviewSystem\Contracts\ReviewRepositoryInterface;
use Jankx\Extensions\ReviewSystem\Repositories\DatabaseReviewRepository;

/**
 * Creates and upgrades the jankx_reviews table and backfills it from
 * existing review comments (comment_type = review + rating meta).
 *
 * @package Jankx\Extensions\ReviewSystem\Database
 */
class ReviewDatabaseInstaller
{
    const DB_VERSION = '1.1.0';

    const OPTION_VERSION = 'jankx_reviews_db_version';

    protected $repository;

    public function __construct(?ReviewRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?: new DatabaseReviewRepository();
    }

    public function register(): void
    {
        add_action('init', [$this, 'maybeInstall'], 5);
    }

    public function maybeInstall(): void
    {
        $installed = get_option(self::OPTION_VERSION);

        if ($installed !== false && version_compare($installed, self::DB_VERSION, '>=')) {
            return;
        }

        $this->createTable();
        $this->backfill();

        if ($installed !== self::DB_VERSION) {
            update_option(self::OPTION_VERSION, self::DB_VERSION);
        }
    }

    protected function createTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            rating tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
            content text,
            pros text,
            cons text,
            author_name varchar(100) NOT NULL DEFAULT '',
            author_email varchar(100) NOT NULL DEFAULT '',
            author_ip varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY comment_id (comment_id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY rating (rating),
            KEY created_at (created_at),
            KEY post_status (post_id, status),
            KEY author_ip (author_ip)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    protected function backfill(): void
    {
        $comments = get_comments([
            'type'   => 'review',
            'status' => 'all',
            'number' => 0,
        ]);

        if (!$comments) {
            return;
        }

        foreach ($comments as $comment) {
            $this->repository->upsertFromComment((int) $comment->comment_ID);
        }
    }

    protected function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jankx_reviews';
    }
}