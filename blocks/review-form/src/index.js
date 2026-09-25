import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    RangeControl,
    ToggleControl,
} from '@wordpress/components';

registerBlockType('jankx/review-form', {
    edit: function (props) {
        const { attributes, setAttributes, context } = props;
        const postId = (context && context.postId) || attributes.postId || 0;

        const maxRating = Math.max(3, Math.min(10, attributes.maxRating || 5));

        const stars = [];
        for (let i = 1; i <= maxRating; i++) {
            stars.push(<span className="jankx-review-form__star is-active" key={i}>★</span>);
        }

        let reviewField = null;
        if (attributes.showReviewField) {
            reviewField = (
                <div className="jankx-review-form__field">
                    <textarea
                        className="jankx-review-form__textarea jankx-review-form__textarea--review"
                        placeholder={__('Viết nhận xét của bạn...', 'jankx')}
                        rows={4}
                    />
                </div>
            );
        }

        let prosCons = null;
        if (attributes.showProsCons) {
            prosCons = (
                <div className="jankx-review-form__pros-cons">
                    <div className="jankx-review-form__field">
                        <label className="jankx-review-form__label jankx-review-form__label--pros">
                            {__('Điểm mạnh', 'jankx')}
                        </label>
                        <textarea
                            className="jankx-review-form__textarea jankx-review-form__textarea--pros"
                            placeholder={__('Mỗi dòng một ý...', 'jankx')}
                            rows={3}
                        />
                    </div>
                    <div className="jankx-review-form__field">
                        <label className="jankx-review-form__label jankx-review-form__label--cons">
                            {__('Điểm yếu', 'jankx')}
                        </label>
                        <textarea
                            className="jankx-review-form__textarea jankx-review-form__textarea--cons"
                            placeholder={__('Mỗi dòng một ý...', 'jankx')}
                            rows={3}
                        />
                    </div>
                </div>
            );
        }

        let note = null;
        if (attributes.requirePurchase) {
            note = (
                <p className="jankx-review-form__editor-note">
                    {__('Sẽ hiển thị khi người dùng đã mua sản phẩm thành công.', 'jankx')}
                </p>
            );
        }
        void postId;

        return (
            <Fragment>
                <InspectorControls>
                    <PanelBody title={__('Cài đặt form đánh giá', 'jankx')}>
                        <TextControl
                            label={__('Tiêu đề', 'jankx')}
                            value={attributes.title}
                            placeholder={__('Đánh giá của bạn', 'jankx')}
                            onChange={(val) => setAttributes({ title: val })}
                        />
                        <TextControl
                            label={__('Text nút gửi', 'jankx')}
                            value={attributes.submitText}
                            placeholder={__('Gửi đánh giá', 'jankx')}
                            onChange={(val) => setAttributes({ submitText: val })}
                        />
                        <RangeControl
                            label={__('Số sao tối đa', 'jankx')}
                            value={maxRating}
                            min={3}
                            max={10}
                            onChange={(val) => setAttributes({ maxRating: val })}
                        />
                        <ToggleControl
                            label={__('Hiện ô nhận xét', 'jankx')}
                            checked={attributes.showReviewField}
                            onChange={(val) => setAttributes({ showReviewField: val })}
                        />
                        <ToggleControl
                            label={__('Hiện Điểm mạnh / Điểm yếu', 'jankx')}
                            checked={attributes.showProsCons}
                            onChange={(val) => setAttributes({ showProsCons: val })}
                        />
                        <ToggleControl
                            label={__('Yêu cầu đã mua hàng thành công', 'jankx')}
                            help={__('Chỉ hiển thị form khi người dùng có đơn hàng completed cho sản phẩm này. (Rỗng = theo cài đặt hệ thống)', 'jankx')}
                            checked={attributes.requirePurchase || false}
                            onChange={(val) => setAttributes({ requirePurchase: val })}
                        />
                        <ToggleControl
                            label={__('Hiện thông báo đăng nhập', 'jankx')}
                            checked={attributes.showLoginPrompt}
                            onChange={(val) => setAttributes({ showLoginPrompt: val })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div className="jankx-review-form-preview">
                    <h3 className="jankx-review-form__title">
                        {attributes.title || __('Đánh giá của bạn', 'jankx')}
                    </h3>
                    <div className="jankx-review-form__body">
                        <div className="jankx-review-form__stars">{stars}</div>
                        {reviewField}
                        {prosCons}
                        {note}
                        <div className="jankx-review-form__submit">
                            <button className="jankx-review-form__button" type="button">
                                {attributes.submitText || __('Gửi đánh giá', 'jankx')}
                            </button>
                        </div>
                    </div>
                </div>
            </Fragment>
        );
    },
    save: function () {
        return null;
    },
});