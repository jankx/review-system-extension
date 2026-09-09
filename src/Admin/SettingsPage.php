<?php

namespace Jankx\Extensions\ReviewSystem\Admin;

use Jankx\Extensions\ReviewSystem\Services\ReviewSettings;
use Jankx\Dashboard\Factories\FieldFactory;
use Jankx\Dashboard\Elements\Page;
use Jankx\Dashboard\Elements\Section;
use Jankx\Adapter\Options\Framework as OptionFramework;

class SettingsPage
{
    const PAGE_ID = 'review_system';

    protected $settings;
    protected $injected = false;

    public function __construct(ReviewSettings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'injectPage'], 1);
    }

    public function injectPage(): void
    {
        if ($this->injected) {
            return;
        }
        $this->injected = true;

        $framework = $this->getFramework();
        if (!$framework) {
            return;
        }

        foreach ($framework->pages as $existing) {
            if (($existing->getId() ?? '') === self::PAGE_ID) {
                return;
            }
        }

        $page = new Page(__('Review System', 'jankx'), [], 'dashicons-before dashicons-star-filled');
        $page->setId(self::PAGE_ID);
        $page->setDescription(__('Configure review system for comments', 'jankx'));
        $page->setPriority(47);

        $section = new Section(__('General', 'jankx'), []);
        $section->setId(self::PAGE_ID . '_general');

        $section->addField(FieldFactory::create(
            ReviewSettings::OPTION_ENABLED,
            __('Enable Review System', 'jankx'),
            'switch',
            [
                'on' => __('On', 'jankx'),
                'off' => __('Off', 'jankx'),
                'value' => $this->settings->getOption(ReviewSettings::OPTION_ENABLED, 1),
                'default' => 1,
                'description' => __('Enable review system on supported post types', 'jankx'),
            ]
        ));

        $section->addField(FieldFactory::create(
            ReviewSettings::OPTION_SHOW_PROS,
            __('Show Pros Field', 'jankx'),
            'switch',
            [
                'on' => __('On', 'jankx'),
                'off' => __('Off', 'jankx'),
                'value' => $this->settings->getOption(ReviewSettings::OPTION_SHOW_PROS, 1),
                'default' => 1,
                'description' => __('Show "Pros" field in comment form', 'jankx'),
            ]
        ));

        $section->addField(FieldFactory::create(
            ReviewSettings::OPTION_SHOW_CONS,
            __('Show Cons Field', 'jankx'),
            'switch',
            [
                'on' => __('On', 'jankx'),
                'off' => __('Off', 'jankx'),
                'value' => $this->settings->getOption(ReviewSettings::OPTION_SHOW_CONS, 1),
                'default' => 1,
                'description' => __('Show "Cons" field in comment form', 'jankx'),
            ]
        ));

        $section->addField(FieldFactory::create(
            ReviewSettings::OPTION_POST_TYPES,
            __('Supported Post Types', 'jankx'),
            'checkbox',
            [
                'options' => $this->settings->getPostTypesForSelect(),
                'value' => $this->settings->getPostTypes(),
                'default' => ['tour', 'experience', 'place', 'product', 'post', 'page'],
                'layout' => 'vertical',
                'description' => __('Select post types that support reviews', 'jankx'),
            ]
        ));

        $section->addField(FieldFactory::create(
            ReviewSettings::OPTION_SORT_DEFAULT,
            __('Default Sort', 'jankx'),
            'select',
            [
                'options' => [
                    'newest' => __('Newest First', 'jankx'),
                    'oldest' => __('Oldest First', 'jankx'),
                    'highest' => __('Highest Rating', 'jankx'),
                    'lowest' => __('Lowest Rating', 'jankx'),
                ],
                'value' => $this->settings->getDefaultSort(),
                'default' => 'newest',
                'description' => __('Default sort order for reviews', 'jankx'),
            ]
        ));

        $page->addSection($section);
        $framework->addPage($page);
    }

    protected function getFramework()
    {
        try {
            $adapter = OptionFramework::getActiveFramework();
            if ($adapter && method_exists($adapter, 'getFramework')) {
                return $adapter->getFramework();
            }
        } catch (\Exception $e) {
        }
        return null;
    }
}
