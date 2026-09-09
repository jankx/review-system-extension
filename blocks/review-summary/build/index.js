(() => {
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var ToggleControl = wp.components.ToggleControl;
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;

    var blockJson = {
        name: 'jankx/review-summary',
        title: 'Review Summary',
        category: 'jankx',
        icon: 'star-filled',
        description: 'Hiển thị tổng quan đánh giá với biểu đồ phân bố điểm.',
        usesContext: ['postId', 'postType'],
        attributes: {
            showDistribution: { type: 'boolean', default: true },
            maxStars: { type: 'number', default: 5 }
        },
        supports: {
            html: false,
            align: ['wide', 'full'],
            spacing: { margin: true, padding: true }
        }
    };

    registerBlockType('jankx/review-summary', {
        ...blockJson,
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var stars = [];
            for (var i = 0; i < attributes.maxStars; i++) {
                stars.push(el('span', {
                    key: i,
                    className: 'review-system-star',
                    style: { fontSize: '18px', color: '#d1d5db' }
                }, '★'));
            }

            var distRows = [];
            for (var r = 5; r >= 1; r--) {
                distRows.push(el('div', { className: 'review-system-dist-row', key: r },
                    el('span', { className: 'review-system-dist-label' }, r + ' ★'),
                    el('div', { className: 'review-system-dist-bar' },
                        el('div', { className: 'review-system-dist-fill', style: { width: '0%' } })
                    ),
                    el('span', { className: 'review-system-dist-count' }, '0')
                ));
            }

            return el(Fragment, null,
                el(InspectorControls, null,
                    el(PanelBody, { title: 'Review Summary Settings' },
                        el(ToggleControl, {
                            label: 'Show Distribution',
                            checked: attributes.showDistribution,
                            onChange: function(val) { setAttributes({ showDistribution: val }); }
                        })
                    )
                ),
                el('div', { className: 'review-system-block-preview', style: { padding: '20px', background: '#f8fafc', borderRadius: '12px', border: '1px solid #e2e8f0' } },
                    el('div', { className: 'review-system-summary', style: { display: 'flex', gap: '24px' } },
                        el('div', { className: 'review-system-summary__main', style: { textAlign: 'center', minWidth: '120px' } },
                            el('div', { className: 'review-system-summary__score' },
                                el('span', { style: { fontSize: '36px', fontWeight: '700', color: '#1e293b' } }, '--'),
                                el('span', { style: { fontSize: '16px', color: '#94a3b8' } }, '/' + attributes.maxStars)
                            ),
                            el('div', { className: 'review-system-summary__stars', style: { margin: '8px 0' } }, stars),
                            el('div', { className: 'review-system-summary__count', style: { fontSize: '13px', color: '#64748b' } }, 'đánh giá')
                        ),
                        attributes.showDistribution && el('div', { className: 'review-system-summary__distribution', style: { flex: 1 } }, distRows)
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    });
})();
