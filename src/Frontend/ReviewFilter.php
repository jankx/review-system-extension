<?php

namespace Jankx\Extensions\ReviewSystem\Frontend;

use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;

class ReviewFilter
{
    protected $settings;

    public function __construct(ReviewSettings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_footer', [$this, 'renderFilterScript'], 20);
    }

    public function renderFilterScript(): void
    {
        if (!is_singular()) {
            return;
        }

        $postId = get_the_ID();
        if (!$postId || !$this->settings->isPostTypeSupported(get_post_type($postId))) {
            return;
        }

        echo '<script type="text/javascript">' . $this->getFilterScript() . '</script>';
    }

    protected function getFilterScript(): string
    {
        $max = 5;
        if (class_exists('\Jankx\Extensions\CommentRating\Admin\Settings')) {
            $max = \Jankx\Extensions\CommentRating\Admin\Settings::getMaxRating();
        }

        return <<<JS
(function() {
    const container = document.querySelector('.review-system-filter');
    if (!container) return;

    const stars = container.querySelectorAll('.review-system-filter__star');
    const sortSelect = container.querySelector('.review-system-filter__sort');
    const clearBtn = container.querySelector('.review-system-filter__clear');
    let activeRating = 0;

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const val = parseInt(this.dataset.rating);
            activeRating = (activeRating === val) ? 0 : val;
            updateStars();
            filterReviews();
        });
    });

    if (sortSelect) {
        sortSelect.addEventListener('change', filterReviews);
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            activeRating = 0;
            if (sortSelect) sortSelect.value = '{$this->settings->getDefaultSort()}';
            updateStars();
            filterReviews();
        });
    }

    function updateStars() {
        stars.forEach(s => {
            const val = parseInt(s.dataset.rating);
            s.classList.toggle('is-active', val <= activeRating);
            s.classList.toggle('is-selected', val === activeRating);
        });
    }

    function filterReviews() {
        const reviews = document.querySelectorAll('.review-system-review-item');
        const sort = sortSelect ? sortSelect.value : 'newest';

        reviews.forEach(r => {
            const rating = parseInt(r.dataset.rating || '0');
            const show = activeRating === 0 || rating === activeRating;
            r.style.display = show ? '' : 'none';
        });

        const list = document.querySelector('.review-system-list');
        if (!list) return;

        const items = Array.from(list.querySelectorAll('.review-system-review-item'));
        items.sort((a, b) => {
            const ra = parseInt(a.dataset.rating || '0');
            const rb = parseInt(b.dataset.rating || '0');
            const da = new Date(a.dataset.date || '0');
            const db = new Date(b.dataset.date || '0');

            switch (sort) {
                case 'highest': return rb - ra;
                case 'lowest': return ra - rb;
                case 'oldest': return da - db;
                case 'newest':
                default: return db - da;
            }
        });

        items.forEach(item => list.appendChild(item));
    }
})();
JS;
    }
}
