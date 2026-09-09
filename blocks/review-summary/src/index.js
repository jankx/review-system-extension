wp.blocks.registerBlockType('jankx/review-summary', {
    ...wp.blocks.getBlockType('jankx/review-summary'),
    edit: function(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var context = props.context;

        var postId = context.postId;

        return wp.element.createElement(wp.element.Fragment, null,
            wp.element.createElement(wp.blockEditor.InspectorControls, null,
                wp.element.createElement(wp.components.PanelBody, { title: 'Review Summary Settings' },
                    wp.element.createElement(wp.components.ToggleControl, {
                        label: 'Show Distribution',
                        checked: attributes.showDistribution,
                        onChange: function(val) { setAttributes({ showDistribution: val }); }
                    })
                )
            ),
            wp.element.createElement('div', { className: 'review-system-block-preview' },
                wp.element.createElement('div', { className: 'review-system-summary' },
                    wp.element.createElement('div', { className: 'review-system-summary__main' },
                        wp.element.createElement('div', { className: 'review-system-summary__score' },
                            wp.element.createElement('span', { className: 'review-system-summary__number' }, '--'),
                            wp.element.createElement('span', { className: 'review-system-summary__max' }, '/' + attributes.maxStars)
                        ),
                        wp.element.createElement('div', { className: 'review-system-summary__stars' },
                            '★'.repeat(attributes.maxStars)
                        ),
                        wp.element.createElement('div', { className: 'review-system-summary__count' }, 'đánh giá')
                    ),
                    attributes.showDistribution && wp.element.createElement('div', { className: 'review-system-summary__distribution' },
                        [5,4,3,2,1].map(function(i) {
                            return wp.element.createElement('div', { className: 'review-system-dist-row', key: i },
                                wp.element.createElement('span', { className: 'review-system-dist-label' }, i + ' ★'),
                                wp.element.createElement('div', { className: 'review-system-dist-bar' },
                                    wp.element.createElement('div', { className: 'review-system-dist-fill', style: { width: '0%' } })
                                ),
                                wp.element.createElement('span', { className: 'review-system-dist-count' }, '0')
                            );
                        })
                    )
                )
            )
        );
    },
    save: function() {
        return null;
    }
});
