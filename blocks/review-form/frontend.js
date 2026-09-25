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
                submit.textContent = submit.dataset.label || submit.textContent;
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
            message.className = 'jankx-review-form__message is-' + (type || 'error');
        }

        function setLoading(isLoading) {
            if (submit) {
                submit.disabled = isLoading;
                submit.textContent = isLoading ? i18n('submitting') : submit.dataset.label || submit.textContent;
            }
            if (spinner) {
                spinner.style.display = isLoading ? '' : 'none';
            }
        }

        function i18n(key) {
            return (jankxReviewForm && jankxReviewForm.i18n && jankxReviewForm.i18n[key]) || '';
        }

        function advanceAfterSuccess() {
            if (orderInput && orders.length > 0 && orderIndex >= 0 && orderIndex < orders.length - 1) {
                selectOrder(orderIndex + 1);
                resetForm();
                showMessage(i18n('nextOrder') || 'Cảm ơn bạn! Sẵn sàng đánh giá cho đơn hàng tiếp theo.', 'success');
                return;
            }
            completeAll();
            showMessage(i18n('success') || 'Cảm ơn bạn đã đánh giá!', 'success');
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
                        advanceAfterSuccess();
                        return;
                    }

                    if (result.status === 409 && orderInput) {
                        advanceAfterSuccess();
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