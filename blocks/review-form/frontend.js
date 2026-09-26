/* global jankxReviewForm, fetch */
(function () {
    'use strict';

    function init(form) {
        var body = form.querySelector('.jankx-review-form__body');
        if (!body) {
            return;
        }

        var stars = form.querySelectorAll('.jankx-review-form__star');
        var ratingText = form.querySelector('.jankx-review-form__rating-text');
        var textarea = form.querySelector('.jankx-review-form__textarea--review');
        var pros = form.querySelector('.jankx-review-form__textarea--pros');
        var cons = form.querySelector('.jankx-review-form__textarea--cons');
        var nameInput = form.querySelector('.jankx-review-form__input--name');
        var emailInput = form.querySelector('.jankx-review-form__input--email');
        var submit = form.querySelector('.jankx-review-form__button');
        var spinner = form.querySelector('.jankx-review-form__spinner');
        var message = form.querySelector('.jankx-review-form__message');
        var orderInput = form.querySelector('.jankx-review-form__order-input');
        var orderLabel = form.querySelector('.jankx-review-form__order-label');
        var mediaZone = form.querySelector('.comment-media-upload-zone');
        var selected = 0;
        var originallabel = submit ? (submit.dataset.label || submit.textContent) : '';

        var orders = (jankxReviewForm && Array.isArray(jankxReviewForm.orders)) ? jankxReviewForm.orders : [];
        var orderIndex = -1;

        if (orderInput && orderLabel && orders.length > 0) {
            var currentOrderId = parseInt(orderInput.value, 10) || parseInt(jankxReviewForm.defaultOrderId, 10) || 0;
            for (var i = 0; i < orders.length; i++) {
                if (parseInt(orders[i].id, 10) === currentOrderId) {
                    orderIndex = i;
                    break;
                }
            }
            if (orderIndex < 0) {
                orderIndex = 0;
            }
            orderInput.value = orders[orderIndex].id;
            orderLabel.textContent = orders[orderIndex].label;
        }

        function selectOrder(index) {
            if (!orderInput || !orderLabel || index < 0 || index >= orders.length) {
                return;
            }
            orderIndex = index;
            orderInput.value = orders[index].id;
            orderLabel.textContent = orders[index].label;
        }

        function collectMediaIds() {
            var ids = [];
            var inputs = form.querySelectorAll('input[name="comment_media_ids[]"]');
            Array.prototype.forEach.call(inputs, function (input) {
                var value = parseInt(input.value, 10);
                if (value > 0) {
                    ids.push(value);
                }
            });
            return ids;
        }

        function isMediaUploading() {
            return form.querySelectorAll('.comment-media-preview-item--uploading').length > 0;
        }

        function resetMedia() {
            Array.prototype.forEach.call(form.querySelectorAll('.comment-media-preview-item'), function (node) {
                node.parentNode.removeChild(node);
            });
            Array.prototype.forEach.call(form.querySelectorAll('input.comment-media-hidden-input'), function (node) {
                node.parentNode.removeChild(node);
            });
            if (mediaZone && typeof CustomEvent === 'function') {
                mediaZone.dispatchEvent(new CustomEvent('comment-media:reset'));
            }
        }

        function resetForm() {
            selected = 0;
            resetMedia();
            if (stars) {
                stars.forEach(function (star) {
                    star.classList.remove('is-active', 'is-hover');
                    star.removeAttribute('aria-disabled');
                });
            }
            if (ratingText) {
                ratingText.textContent = '';
            }
            [textarea, pros, cons].forEach(function (field) {
                if (field) {
                    field.disabled = false;
                    field.value = '';
                }
            });
            if (submit) {
                submit.disabled = false;
                submit.textContent = originallabel;
            }
        }

        function completeAll() {
            if (stars) {
                stars.forEach(function (star) {
                    star.setAttribute('aria-disabled', 'true');
                });
            }
            if (textarea) {
                textarea.disabled = true;
            }
            if (pros) {
                pros.disabled = true;
            }
            if (cons) {
                cons.disabled = true;
            }
            if (submit) {
                submit.disabled = true;
            }
        }

        function setRating(value) {
            selected = value;
            stars.forEach(function (star) {
                var v = parseInt(star.getAttribute('data-value'), 10);
                star.classList.toggle('is-active', v <= selected);
            });
            if (ratingText) {
                var max = parseInt(form.querySelector('.jankx-review-form__stars').getAttribute('data-max-rating'), 10) || 5;
                ratingText.textContent = selected ? selected + '/' + max : '';
            }
        }

        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                setRating(parseInt(star.getAttribute('data-value'), 10));
            });
            star.addEventListener('mouseenter', function () {
                var v = parseInt(star.getAttribute('data-value'), 10);
                stars.forEach(function (s) {
                    s.classList.toggle('is-hover', parseInt(s.getAttribute('data-value'), 10) <= v);
                });
            });
            star.addEventListener('mouseleave', function () {
                if (selected < 1) {
                    stars.forEach(function (s) {
                        s.classList.remove('is-hover');
                    });
                }
            });
        });

        function showMessage(text, type) {
            if (!message) {
                return;
            }
            message.textContent = text;
            if (!text) {
                message.className = 'jankx-review-form__message';
                return;
            }
            message.className = 'jankx-review-form__message is-' + (type || 'error');
        }

        function setLoading(isLoading) {
            if (submit) {
                submit.disabled = isLoading;
                submit.textContent = isLoading ? i18n('submitting') : originallabel;
            }
            if (spinner) {
                spinner.classList.toggle('is-active', isLoading);
            }
        }

        function formatDate(value) {
            if (!value) {
                return '';
            }
            var d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) {
                return String(value);
            }
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            var dd = String(d.getDate()).padStart(2, '0');
            return dd + '/' + mm + '/' + d.getFullYear();
        }

        function buildReviewItem(review) {
            var max = parseInt(form.querySelector('.jankx-review-form__stars').getAttribute('data-max-rating'), 10) || 5;
            var rating = parseInt(review.rating, 10) || 0;
            var stars = '';
            for (var i = 1; i <= max; i++) {
                stars += '<span class="review-system-star' + (i <= rating ? ' is-active' : '') + '">★</span>';
            }
            var avatar = review.avatar ? '<img class="review-system-review-avatar" src="' + review.avatar + '" alt="" />' : '';
            var extras = '';
            if ((review.pros && review.pros.length) || (review.cons && review.cons.length)) {
                extras += '<div class="review-system-comment-extras">';
                if (review.pros && review.pros.length) {
                    extras += '<div class="review-system-pros-list"><strong class="review-system-label review-system-label--pros">Điểm mạnh</strong><ul>';
                    review.pros.forEach(function (pro) {
                        extras += '<li>' + pro + '</li>';
                    });
                    extras += '</ul></div>';
                }
                if (review.cons && review.cons.length) {
                    extras += '<div class="review-system-cons-list"><strong class="review-system-label review-system-label--cons">Điểm yếu</strong><ul>';
                    review.cons.forEach(function (con) {
                        extras += '<li>' + con + '</li>';
                    });
                    extras += '</ul></div>';
                }
                extras += '</div>';
            }
            var item = document.createElement('li');
            item.className = 'comment jankx-appended-review';
            item.id = 'comment-' + (review.id || Math.floor(Date.now() / 1000));
            item.innerHTML =
                '<div class="wp-block-columns">' +
                '<div class="wp-block-column" style="flex-basis:40px">' + avatar + '</div>' +
                '<div class="wp-block-column">' +
                '<span class="wp-block-comment-author-name">' + (review.author || '') + '</span>' +
                '<div class="wp-block-group" style="margin-top:0;margin-bottom:0"><span class="wp-block-comment-date">' + formatDate(review.date) + '</span></div>' +
                '<div class="review-system-stars">' + stars + '<span class="review-system-rating-text">' + rating + '/' + max + '</span></div>' +
                '<div class="wp-block-comment-content">' + (review.content || '') + extras + '</div>' +
                '</div>' +
                '</div>';
            return item;
        }

        function appendReview(review) {
            if (!review) {
                return;
            }
            var list = form.ownerDocument.querySelector('.wp-block-comment-template');
            if (!list) {
                var wrapper = form.ownerDocument.querySelector('.wp-block-comments, #comments, .comments-area');
                if (wrapper) {
                    list = document.createElement('ol');
                    list.className = 'wp-block-comment-template';
                    var formEl = wrapper.querySelector('.wp-block-post-comments-form');
                    if (formEl) {
                        wrapper.insertBefore(list, formEl);
                    } else {
                        wrapper.appendChild(list);
                    }
                }
                if (!list) {
                    list = form.parentNode;
                }
            }
            if (list) {
                list.insertBefore(buildReviewItem(review), list.firstChild);
            }
        }

        function updateSummary(summary) {
            if (!summary) {
                return;
            }
            var host = form.ownerDocument;
            var summaryRoot = host.querySelector('.wp-block-jankx-review-summary');
            if (!summaryRoot) {
                return;
            }
            var numberEl = summaryRoot.querySelector('.review-system-summary__number');
            var countEl = summaryRoot.querySelector('.review-system-summary__count');

            if (countEl) {
                countEl.textContent = parseInt(summary.count, 10) + ' đánh giá';
            }
            if (summaryRoot.querySelector('.review-system-summary__number')) {
                if (numberEl) {
                    numberEl.textContent = Number(summary.average).toFixed(1);
                }
                var maxEl = summaryRoot.querySelector('.review-system-summary__max');
                var maxRating = maxEl ? parseInt((maxEl.textContent || '/5').replace('/', ''), 10) : 5;
                var activeStars = summaryRoot.querySelectorAll('.review-system-summary__stars .review-system-star');
                if (activeStars) {
                    Array.prototype.forEach.call(activeStars, function (star, i) {
                        star.classList.toggle('is-active', (i + 1) <= Math.round(Number(summary.average)));
                    });
                }
                var distRows = summaryRoot.querySelectorAll('.review-system-dist-row');
                if (distRows.length && summary.distribution) {
                    Array.prototype.forEach.call(distRows, function (row) {
                        var rating = parseInt(row.getAttribute('data-rating'), 10) || 0;
                        var num = (summary.distribution && summary.distribution[rating]) || 0;
                        var count = parseInt(summary.count, 10) || 0;
                        var pct = count > 0 ? Math.round(num / count * 100) : 0;
                        row.querySelector('.review-system-dist-fill').style.width = pct + '%';
                        row.querySelector('.review-system-dist-count').textContent = num;
                    });
                }
            } else {
                summaryRoot.innerHTML = '' +
                    '<div class="review-system-summary" role="img">' +
                    '<div class="review-system-summary__main">' +
                    '<div class="review-system-summary__score">' +
                    '<span class="review-system-summary__number">' + Number(summary.average).toFixed(1) + '</span>' +
                    '<span class="review-system-summary__max">/' + maxRating() + '</span>' +
                    '</div>' +
                    '<div class="review-system-summary__stars">' + buildSummaryStars(Number(summary.average)) + '</div>' +
                    '<div class="review-system-summary__count">' + parseInt(summary.count, 10) + ' đánh giá</div>' +
                    '</div>' +
                    '</div>';
            }
        }

        function maxRating() {
            return parseInt(form.querySelector('.jankx-review-form__stars').getAttribute('data-max-rating'), 10) || 5;
        }

        function buildSummaryStars(average) {
            var html = '';
            var max = maxRating();
            for (var i = 1; i <= max; i++) {
                html += '<span class="review-system-star' + (i <= Math.round(average) ? ' is-active' : '') + '">★</span>';
            }
            return html;
        }

        function completeAll() {
            form.classList.add('jankx-review-form--completed');
            var formBody = form.querySelector('.jankx-review-form__body');
            if (formBody) {
                formBody.style.display = 'none';
            }
            var successNotice = form.querySelector('.jankx-review-form__success-notice');
            if (!successNotice) {
                successNotice = document.createElement('div');
                successNotice.className = 'jankx-review-form__success-notice';
                form.appendChild(successNotice);
            }
            successNotice.style.display = 'block';
            successNotice.textContent = i18n('success') || 'Cảm ơn bạn đã đánh giá!';
        }

        function i18n(key) {
            return (jankxReviewForm && jankxReviewForm.i18n && jankxReviewForm.i18n[key]) || '';
        }

        function advanceAfterSuccess(review, summary) {
            appendReview(review);
            updateSummary(summary);

            if (orderInput && orders.length > 0 && orderIndex >= 0 && orderIndex < orders.length - 1) {
                selectOrder(orderIndex + 1);
                resetForm();
                showMessage(i18n('nextOrder') || 'Cảm ơn bạn! Sẵn sàng đánh giá cho đơn hàng tiếp theo.', 'success');
                return;
            }
            completeAll();
        }

        if (!submit) {
            return;
        }

        submit.addEventListener('click', function () {
            if (selected < 1) {
                showMessage(i18n('selectRating') || 'Vui lòng chọn số sao.');
                return;
            }

            if (isMediaUploading()) {
                showMessage(i18n('uploadingMedia') || 'Đang tải ảnh lên, vui lòng chờ...');
                return;
            }

            if (!jankxReviewForm || !jankxReviewForm.restUrl) {
                showMessage(i18n('error'));
                return;
            }

            setLoading(true);
            showMessage('');

            var payload = {
                post_id: jankxReviewForm.postId,
                rating: selected,
                review: textarea ? textarea.value : '',
                pros: pros ? pros.value : '',
                cons: cons ? cons.value : ''
            };

            if (orderInput) {
                payload.order_id = parseInt(orderInput.value, 10) || 0;
            }
            if (nameInput) {
                payload.author_name = nameInput.value;
            }
            if (emailInput) {
                payload.author_email = emailInput.value;
            }

            var mediaIds = collectMediaIds();
            if (mediaIds.length) {
                payload.media_ids = mediaIds;
            }

            fetch(jankxReviewForm.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': jankxReviewForm.nonce || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (result) {
                    setLoading(false);

                    if (result.ok && result.data && result.data.success) {
                        advanceAfterSuccess(result.data.review, result.data.summary);
                        return;
                    }

                    if (result.status === 409 && orderInput) {
                        advanceAfterSuccess(result.data && result.data.review, result.data && result.data.summary);
                        return;
                    }

                    var msg = (result.data && result.data.message) || i18n('error');
                    showMessage(msg);
                })
                .catch(function () {
                    setLoading(false);
                    showMessage(i18n('error'));
                });
        });
    }

    function boot() {
        var forms = document.querySelectorAll('.wp-block-jankx-review-form');
        forms.forEach ? forms.forEach(init) : Array.prototype.forEach.call(forms, init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();