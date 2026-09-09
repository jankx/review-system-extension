<?php

namespace Jankx\Extensions\ReviewSystem;

class Styles
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function enqueue(): void
    {
        $css = self::getCss();
        if ($css) {
            wp_add_inline_style('wp-blocks', $css);
        }
    }

    public static function getCss(): string
    {
        return <<<'CSS'
.review-system-summary {
    display: flex;
    gap: 24px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
}
.review-system-summary__main {
    text-align: center;
    min-width: 120px;
}
.review-system-summary__number {
    font-size: 36px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}
.review-system-summary__max {
    font-size: 16px;
    color: #94a3b8;
}
.review-system-summary__stars {
    margin: 8px 0;
}
.review-system-summary__count {
    font-size: 13px;
    color: #64748b;
}
.review-system-summary__distribution {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
    justify-content: center;
}
.review-system-dist-row {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    transition: background 0.15s;
}
.review-system-dist-row:hover {
    background: #e2e8f0;
}
.review-system-dist-label {
    font-size: 13px;
    color: #475569;
    min-width: 30px;
    text-align: right;
}
.review-system-dist-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}
.review-system-dist-fill {
    height: 100%;
    background: #f59e0b;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.review-system-dist-count {
    font-size: 12px;
    color: #94a3b8;
    min-width: 24px;
}
.review-system-star {
    color: #d1d5db;
    font-size: 18px;
    transition: color 0.15s;
}
.review-system-star.is-active {
    color: #f59e0b;
}
.review-system-filter {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    flex-wrap: wrap;
}
.review-system-filter__label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.review-system-filter__stars {
    display: flex;
    gap: 2px;
}
.review-system-filter__star {
    cursor: pointer;
    color: #d1d5db;
    font-size: 20px;
    transition: color 0.15s, transform 0.15s;
    background: none;
    border: none;
    padding: 2px;
}
.review-system-filter__star:hover,
.review-system-filter__star.is-active {
    color: #f59e0b;
    transform: scale(1.1);
}
.review-system-filter__star.is-selected {
    color: #d97706;
}
.review-system-filter__sort {
    margin-left: auto;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    color: #475569;
}
.review-system-filter__clear {
    font-size: 12px;
    color: #64748b;
    background: none;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 4px 10px;
    cursor: pointer;
}
.review-system-filter__clear:hover {
    background: #f1f5f9;
}
.review-system-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.review-system-review-item {
    padding: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: box-shadow 0.15s;
}
.review-system-review-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.review-system-review-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.review-system-review-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}
.review-system-review-author {
    font-weight: 600;
    color: #1e293b;
}
.review-system-review-date {
    font-size: 12px;
    color: #94a3b8;
}
.review-system-comment-extras {
    margin-top: 8px;
}
.review-system-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    margin-bottom: 4px;
}
.review-system-label--pros {
    color: #16a34a;
}
.review-system-label--cons {
    color: #dc2626;
}
.review-system-pros-list ul,
.review-system-cons-list ul {
    margin: 0;
    padding-left: 20px;
    font-size: 13px;
    color: #475569;
}
.review-system-pros-list li {
    color: #16a34a;
}
.review-system-cons-list li {
    color: #dc2626;
}
.review-system-pros-cons {
    margin-top: 12px;
}
.review-system-field {
    margin-bottom: 12px;
}
.review-system-field label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    margin-bottom: 4px;
}
.review-system-field .description {
    font-size: 12px;
    color: #94a3b8;
    margin: 0 0 4px;
}
@media (max-width: 600px) {
    .review-system-summary {
        flex-direction: column;
        text-align: center;
    }
    .review-system-filter {
        flex-direction: column;
        align-items: flex-start;
    }
    .review-system-filter__sort {
        margin-left: 0;
    }
}
CSS;
    }
}
