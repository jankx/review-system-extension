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
        var selected = 0;

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
                stars.forEach(function (s) {
                    s.classList.remove('is-hover');
                });
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

        if (!submit) {
            return;
        }

        submit.addEventListener('click', function () {
            if (selected < 1) {
                showMessage(i18n('selectRating') || 'Vui lòng chọn số sao.');
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

            if (nameInput) {
                payload.author_name = nameInput.value;
            }
            if (emailInput) {
                payload.author_email = emailInput.value;
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
                        showMessage(result.data.message || i18n('success'), 'success');
                        stars.forEach(function (star) {
                            star.setAttribute('aria-disabled', 'true');
                        });
                        if (textarea) {
                            textarea.disabled = true;
                        }
                        if (submit) {
                            submit.disabled = true;
                        }
                    } else {
                        var msg = (result.data && result.data.message) || i18n('error');
                        if (result.status === 403 || result.status === 409) {
                            msg = result.data.message || i18n('alreadyRated');
                        }
                        showMessage(msg);
                    }
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