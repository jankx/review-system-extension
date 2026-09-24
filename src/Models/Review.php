<?php

namespace Jankx\Extensions\ReviewSystem\Models;

/**
 * Review entity mapped to the jankx_reviews table.
 *
 * @package Jankx\Extensions\ReviewSystem\Models
 */
class Review
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_SPAM     = 'spam';
    const STATUS_TRASH    = 'trash';

    protected $id = 0;
    protected $commentId = 0;
    protected $postId = 0;
    protected $userId = 0;
    protected $rating = 0;
    protected $content = '';
    protected $pros = [];
    protected $cons = [];
    protected $authorName = '';
    protected $authorEmail = '';
    protected $authorIp = '';
    protected $status = self::STATUS_PENDING;
    protected $createdAt;
    protected $updatedAt;

    public function __construct(array $data = [])
    {
        $this->fromArray($data);
    }

    public function fromArray(array $data): self
    {
        if (isset($data['id'])) {
            $this->id = (int) $data['id'];
        }
        if (isset($data['comment_id'])) {
            $this->commentId = (int) $data['comment_id'];
        }
        if (isset($data['post_id'])) {
            $this->postId = (int) $data['post_id'];
        }
        if (isset($data['user_id'])) {
            $this->userId = (int) $data['user_id'];
        }
        if (isset($data['rating'])) {
            $this->rating = (int) $data['rating'];
        }
        if (isset($data['content'])) {
            $this->content = (string) $data['content'];
        }
        if (isset($data['pros'])) {
            $this->pros = is_array($data['pros']) ? $data['pros'] : $this->decodeList($data['pros']);
        }
        if (isset($data['cons'])) {
            $this->cons = is_array($data['cons']) ? $data['cons'] : $this->decodeList($data['cons']);
        }
        if (isset($data['author_name'])) {
            $this->authorName = (string) $data['author_name'];
        }
        if (isset($data['author_email'])) {
            $this->authorEmail = (string) $data['author_email'];
        }
        if (isset($data['author_ip'])) {
            $this->authorIp = (string) $data['author_ip'];
        }
        if (isset($data['status'])) {
            $this->status = (string) $data['status'];
        }
        if (isset($data['created_at'])) {
            $this->createdAt = $data['created_at'];
        }
        if (isset($data['updated_at'])) {
            $this->updatedAt = $data['updated_at'];
        }

        return $this;
    }

    public static function fromRow(?array $row): ?self
    {
        if (empty($row)) {
            return null;
        }
        return new self($row);
    }

    protected function decodeList($value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values(array_filter(array_map('sanitize_text_field', $decoded))) : [];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCommentId(): int
    {
        return $this->commentId;
    }

    public function setCommentId(int $commentId): self
    {
        $this->commentId = $commentId;
        return $this;
    }

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function setPostId(int $postId): self
    {
        $this->postId = $postId;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getPros(): array
    {
        return $this->pros;
    }

    public function setPros(array $pros): self
    {
        $this->pros = array_values(array_filter(array_map('sanitize_text_field', $pros)));
        return $this;
    }

    public function getCons(): array
    {
        return $this->cons;
    }

    public function setCons(array $cons): self
    {
        $this->cons = array_values(array_filter(array_map('sanitize_text_field', $cons)));
        return $this;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): self
    {
        $this->authorName = $authorName;
        return $this;
    }

    public function getAuthorEmail(): string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(string $authorEmail): self
    {
        $this->authorEmail = $authorEmail;
        return $this;
    }

    public function getAuthorIp(): string
    {
        return $this->authorIp;
    }

    public function setAuthorIp(string $authorIp): self
    {
        $this->authorIp = $authorIp;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt($updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'comment_id'   => $this->commentId,
            'post_id'      => $this->postId,
            'user_id'      => $this->userId,
            'rating'       => $this->rating,
            'content'      => $this->content,
            'pros'         => $this->pros,
            'cons'         => $this->cons,
            'author_name'  => $this->authorName,
            'author_email' => $this->authorEmail,
            'author_ip'    => $this->authorIp,
            'status'       => $this->status,
            'created_at'   => $this->createdAt,
            'updated_at'   => $this->updatedAt,
        ];
    }

    public function toDataArray(): array
    {
        return [
            'id'     => $this->id,
            'rating' => $this->rating,
            'pros'   => $this->pros,
            'cons'   => $this->cons,
            'author' => $this->authorName,
            'content' => $this->content,
            'date'   => $this->createdAt,
            'avatar' => $this->getAvatarUrl(),
        ];
    }

    protected function getAvatarUrl(): string
    {
        if (!$this->authorEmail) {
            return '';
        }
        return get_avatar_url($this->authorEmail, ['size' => 48]);
    }
}