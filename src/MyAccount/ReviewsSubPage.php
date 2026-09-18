<?php

namespace Jankx\Extensions\ReviewSystem\MyAccount;

use Jankx\Extensions\MyAccount\SubPage\AbstractSubPage;

class ReviewsSubPage extends AbstractSubPage
{
    public function getSlug(): string
    {
        return 'reviews';
    }

    public function getLabel(): string
    {
        return __('Đánh giá của bạn', 'jankx');
    }

    public function getIcon(): string
    {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
    }

    public function getPriority(): int
    {
        return 35;
    }

    public function getExtension(): ?string
    {
        return 'review-system';
    }

    public function getContent(): string
    {
        return '<!-- wp:jankx/account-tab-reviews -->'
            . '<!-- wp:jankx/reviews-my-reviews /-->'
            . '<!-- wp:jankx/reviews-pending /-->'
            . '<!-- wp:jankx/reviews-completed /-->'
            . '<!-- /wp:jankx/account-tab-reviews -->';
    }
}
