(function (wp) {
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var __experimentalUnitControl = wp.components.__experimentalUnitControl;

    var hasUnitControl = typeof wp.components.__experimentalUnitControl === 'function';

    registerBlockType('jankx/review-form', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var postId = (props.context && props.context.postId) || attributes.postId || 0;

            var maxRating = Math.max(3, Math.min(10, attributes.maxRating || 5));

            var stars = [];
            for (var i = 1; i <= maxRating; i++) {
                stars.push(el('span', { className: 'jankx-review-form__star is-active', key: i }, '★'));
            }

            var fieldClasses = 'jankx-review-form__textarea jankx-review-form__textarea--review';
            var reviewField = attributes.showReviewField
                ? el('div', { className: 'jankx-review-form__field' },
                    el('textarea', { className: fieldClasses, placeholder: __('Viết nhận xét của bạn...', 'jankx'), rows: 4 }))
                : null;

            var prosCons = null;
            if (attributes.showProsCons) {
                prosCons = el('div', { className: 'jankx-review-form__pros-cons' },
                    el('div', { className: 'jankx-review-form__field' },
                        el('label', { className: 'jankx-review-form__label jankx-review-form__label--pros' }, __('Điểm mạnh', 'jankx')),
                        el('textarea', { className: 'jankx-review-form__textarea jankx-review-form__textarea--pros', placeholder: __('Mỗi dòng một ý...', 'jankx'), rows: 3 })),
                    el('div', { className: 'jankx-review-form__field' },
                        el('label', { className: 'jankx-review-form__label jankx-review-form__label--cons' }, __('Điểm yếu', 'jankx')),
                        el('textarea', { className: 'jankx-review-form__textarea jankx-review-form__textarea--cons', placeholder: __('Mỗi dòng một ý...', 'jankx'), rows: 3 }))
                );
            }

            var notice = null;
            if (attributes.requirePurchase) {
                notice = el('p', { className: 'jankx-review-form__editor-note' },
                    __('Sẽ hiển thị khi người dùng đã mua sản phẩm thành công.', 'jankx'));
            }

            return el(wp.element.Fragment, null,
                el(InspectorControls, null,
                    el(wp.components.PanelBody, { title: __('Cài đặt form đánh giá', 'jankx') },
                        el(wp.components.TextControl, {
                            label: __('Tiêu đề', 'jankx'),
                            value: attributes.title,
                            placeholder: __('Đánh giá của bạn', 'jankx'),
                            onChange: function (val) { setAttributes({ title: val }); }
                        }),
                        el(wp.components.TextControl, {
                            label: __('Text nút gửi', 'jankx'),
                            value: attributes.submitText,
                            placeholder: __('Gửi đánh giá', 'jankx'),
                            onChange: function (val) { setAttributes({ submitText: val }); }
                        }),
                        el(wp.components.RangeControl, {
                            label: __('Số sao tối đa', 'jankx'),
                            value: maxRating,
                            min: 3,
                            max: 10,
                            onChange: function (val) { setAttributes({ maxRating: val }); }
                        }),
                        el(wp.components.ToggleControl, {
                            label: __('Hiện ô nhận xét', 'jankx'),
                            checked: attributes.showReviewField,
                            onChange: function (val) { setAttributes({ showReviewField: val }); }
                        }),
                        el(wp.components.ToggleControl, {
                            label: __('Hiện Điểm mạnh / Điểm yếu', 'jankx'),
                            checked: attributes.showProsCons,
                            onChange: function (val) { setAttributes({ showProsCons: val }); }
                        }),
                        el(wp.components.ToggleControl, {
                            label: __('Yêu cầu đã mua hàng thành công', 'jankx'),
                            help: __('Chỉ hiển thị form khi người dùng có đơn hàng completed cho sản phẩm này. (Rỗng = theo cài đặt hệ thống)', 'jankx'),
                            checked: attributes.requirePurchase || false,
                            onChange: function (val) { setAttributes({ requirePurchase: val }); }
                        }),
                        el(wp.components.ToggleControl, {
                            label: __('Hiện thông báo đăng nhập', 'jankx'),
                            checked: attributes.showLoginPrompt,
                            onChange: function (val) { setAttributes({ showLoginPrompt: val }); }
                        })
                    )
                ),
                el('div', { className: 'jankx-review-form-preview' },
                    el('h3', { className: 'jankx-review-form__title' },
                        attributes.title || __('Đánh giá của bạn', 'jankx')),
                    el('div', { className: 'jankx-review-form__body' },
                        el('div', { className: 'jankx-review-form__stars' }, stars),
                        reviewField,
                        prosCons,
                        notice,
                        el('div', { className: 'jankx-review-form__submit' },
                            el('button', { className: 'jankx-review-form__button', type: 'button' },
                                attributes.submitText || __('Gửi đánh giá', 'jankx'))
                        )
                    )
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp);