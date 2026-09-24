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
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.review-system-field {
    padding: 18px 20px;
    border-radius: 14px;
    border: 1.5px solid;
    transition: box-shadow .2s ease, border-color .2s ease;
}
.review-system-pros {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border-color: #bbf7d0;
}
.review-system-pros:focus-within {
    border-color: #4ade80;
    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.15);
}
.review-system-cons {
    background: linear-gradient(135deg, #fff1f2 0%, #fef2f2 100%);
    border-color: #fecdd3;
}
.review-system-cons:focus-within {
    border-color: #f87171;
    box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.15);
}
.review-system-field label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    width: fit-content;
}
.review-system-pros label {
    background: #dcfce7;
    color: #15803d;
}
.review-system-cons label {
    background: #ffe4e6;
    color: #be123c;
}
.review-system-field .description {
    font-size: 12px;
    color: #94a3b8;
    margin: 0 0 10px;
    line-height: 1.5;
}
.review-system-field textarea {
    background: rgba(255,255,255,0.7) !important;
    border: 1.5px solid transparent !important;
    border-radius: 10px !important;
    font-size: 14px !important;
    line-height: 1.6 !important;
    resize: vertical !important;
    transition: border-color .2s ease, background .2s ease !important;
    width: 100% !important;
    box-sizing: border-box !important;
    padding: 10px 14px !important;
    color: #1e293b !important;
}
.review-system-pros textarea:focus {
    border-color: #4ade80 !important;
    background: #fff !important;
    outline: none !important;
    box-shadow: none !important;
}
.review-system-cons textarea:focus {
    border-color: #f87171 !important;
    background: #fff !important;
    outline: none !important;
    box-shadow: none !important;
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
    .jankx-review-item-card {
        flex-direction: column;
    }
    .jankx-review-item-actions {
        width: 100%;
    }
    .jankx-review-item-actions .jankx-btn {
        width: 100%;
        text-align: center;
    }
}

/* Review Items List (MyAccount) */
.jankx-review-items-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.jankx-review-item-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: box-shadow 0.15s, border-color 0.15s;
}

.jankx-review-item-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border-color: #cbd5e1;
}

.jankx-review-item-card--reviewed {
    flex-direction: column;
    align-items: stretch;
}

.jankx-review-item-info {
    flex: 1;
    min-width: 0;
}

.jankx-review-item-name {
    display: block;
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
    text-decoration: none;
    margin-bottom: 4px;
    line-height: 1.4;
}

.jankx-review-item-name:hover {
    color: #007cba;
    text-decoration: underline;
}

.jankx-review-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 13px;
    color: #64748b;
}

.jankx-review-item-type {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    background: #f1f5f9;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
}

.jankx-review-item-price {
    font-weight: 500;
    color: #059669;
}

.jankx-review-item-order {
    color: #94a3b8;
}

.jankx-review-item-date {
    color: #94a3b8;
}

.jankx-review-item-actions {
    flex-shrink: 0;
}

.jankx-review-item-actions .jankx-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 14px;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.2s;
}

.jankx-review-item-actions .jankx-btn-primary {
    background: #007cba;
    color: #fff;
}

.jankx-review-item-actions .jankx-btn-primary:hover {
    background: #005a87;
}

.jankx-review-item-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 16px;
}

/* Reviewed items */
.jankx-review-item-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.jankx-review-stars {
    display: flex;
    gap: 2px;
}

.jankx-review-stars .jankx-star {
    color: #d1d5db;
    font-size: 18px;
    transition: color 0.15s;
}

.jankx-review-stars .jankx-star.is-active {
    color: #f59e0b;
}

.jankx-review-rating-text {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.jankx-review-item-content {
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}

.jankx-review-text {
    font-size: 14px;
    color: #334155;
    line-height: 1.6;
    margin: 0 0 12px;
}

.jankx-review-pros,
.jankx-review-cons {
    margin-bottom: 8px;
}

.jankx-review-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 4px;
}

.jankx-review-label--pros {
    color: #16a34a;
}

.jankx-review-label--cons {
    color: #dc2626;
}

.jankx-review-pros ul,
.jankx-review-cons ul {
    margin: 0;
    padding-left: 20px;
    font-size: 13px;
    color: #475569;
}

.jankx-review-pros li {
    color: #16a34a;
}

.jankx-review-cons li {
    color: #dc2626;
}

/* Section titles */
.jankx-section-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.jankx-empty-state {
    text-align: center;
    padding: 32px 16px;
    color: #94a3b8;
    font-size: 14px;
}
CSS;
    }
}
