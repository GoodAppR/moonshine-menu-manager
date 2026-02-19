<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Traits;

use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Crud\Components\Layout\Locales;
use MoonShine\Crud\Components\Layout\Notifications;
use MoonShine\CustomMenuManager\Components\MenuManagerActivationButton;
use MoonShine\CustomMenuManager\Services\MenuConfigService;
use MoonShine\UI\Components\Breadcrumbs;
use MoonShine\UI\Components\Layout\Body;
use MoonShine\UI\Components\Layout\BottomBar;
use MoonShine\UI\Components\Layout\Burger;
use MoonShine\UI\Components\Layout\Content;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Components\Layout\Flash;
use MoonShine\UI\Components\Layout\Header;
use MoonShine\UI\Components\Layout\Html;
use MoonShine\UI\Components\Layout\Layout;
use MoonShine\UI\Components\Layout\Menu;
use MoonShine\UI\Components\Layout\Sidebar;
use MoonShine\UI\Components\Layout\ThemeSwitcher;
use MoonShine\UI\Components\Layout\TopBar;
use MoonShine\UI\Components\Layout\Wrapper;
use MoonShine\UI\Components\When;

trait CustomMenuManager
{
    public function isMenuManagerEnabled(): bool
    {
        return config('moonshine_menu_manager.enabled', true);
    }

    protected function shouldShowTopBar(): bool
    {
        if ($this->topBar) {
            return true;
        }

        if (! $this->isMenuManagerEnabled()) {
            return false;
        }

        $items = app(MenuConfigService::class)->getItemsForZone('topbar');

        return $items->isNotEmpty();
    }

    protected function shouldShowBottomBar(): bool
    {
        if ($this->mobileMode) {
            return true;
        }

        if ($this->bottomBar && $this->isMenuManagerEnabled()) {
            return app(MenuConfigService::class)->hasItemsInZone('bottom_bar');
        }

        if ($this->bottomBar) {
            return true;
        }

        return $this->isMenuManagerEnabled()
            && app(MenuConfigService::class)->hasItemsInZone('bottom_bar');
    }

    /** @return list<ComponentContract> */
    protected function sidebarTopSlot(): array
    {
        return $this->isMenuManagerEnabled()
            ? [MenuManagerActivationButton::make()]
            : [];
    }

    /** @return list<ComponentContract> */
    protected function topBarSlot(): array
    {
        return $this->isMenuManagerEnabled()
            ? [MenuManagerActivationButton::make()]
            : [];
    }

    protected function getSidebarComponent(): Sidebar
    {
        $menuItems = $this->isMenuManagerEnabled()
            ? app(MenuConfigService::class)->getItemsForZone('sidebar')
            : null;

        $sidebarFragments = [
            Fragment::make([
                ...$this->sidebarSlot(),
                Menu::make($menuItems),
            ])->class('menu menu--vertical')->name('sidebar-content'),
        ];

        if (! $this->shouldShowTopBar()) {
            array_unshift($sidebarFragments, Fragment::make([
                Div::make([
                    $this->getLogoComponent()->minimized(),
                ])->class('menu-logo'),
                Div::make([
                    When::make(
                        fn (): bool => $this->isUseNotifications(),
                        static fn (): array => [class_exists(\MoonShine\Rush\Components\RushNotifications::class) ? \MoonShine\Rush\Components\RushNotifications::make() : Notifications::make()],
                    ),
                    When::make(
                        fn (): bool => $this->hasThemes() && ! $this->isAlwaysDark(),
                        static fn (): array => [ThemeSwitcher::make()],
                    ),
                    ...$this->sidebarTopSlot(),
                ])->class('menu-actions'),
                Div::make(array_filter([
                    $this->mobileMode ? null : Burger::make()->sidebar(),
                ]))->class('menu-burger'),
            ])->class('menu-header')->name('sidebar-top'));
        }

        return Sidebar::make($sidebarFragments)->collapsed($this->secondBar === false);
    }

    protected function getHeaderComponent(): Header
    {
        $homeLabel = $this->getCore()->getTranslator()->get('moonshine::ui.home');

        if ($homeLabel === 'moonshine::ui.home') {
            $homeLabel = 'Home';
        }

        return Header::make([
            Div::make(array_filter([
                $this->mobileMode || ! $this->sidebar ? null : Burger::make(),
            ]))->class('menu-burger'),
            Breadcrumbs::make(
                $this->getPage()->getBreadcrumbs(),
            )->prepend(
                $this->getHomeUrl(),
                label: $homeLabel,
            ),
            $this->getSearchComponent(),
            When::make(
                fn (): bool => $this->hasThemes() && ! $this->isAlwaysDark() && ($this->mobileMode || (! $this->sidebar && ! $this->shouldShowTopBar())),
                static fn (): array => [ThemeSwitcher::make()],
            ),
            Locales::make(),
            When::make(
                fn (): bool => $this->isProfileEnabled() && ! $this->shouldShowTopBar(),
                fn (): array => [
                    Fragment::make([
                        $this->getProfileComponent(),
                    ])->name('profile'),
                ],
            ),
            When::make(
                fn (): bool => $this->isUseNotifications() && ($this->mobileMode || ! $this->sidebar),
                static fn (): array => [Notifications::make()],
            ),
        ]);
    }

    protected function getTopBarComponent(): TopBar
    {
        $menuItems = $this->isMenuManagerEnabled()
            ? app(MenuConfigService::class)->getItemsForZone('topbar')
            : null;

        return TopBar::make([
            Fragment::make([
                $this->getLogoComponent()->minimized(),
            ])->class('menu-logo')->name('topbar-logo'),

            Fragment::make([
                Menu::make($menuItems)->top(),
            ])->class('menu menu--horizontal')->name('topbar-menu'),

            Fragment::make([
                ...$this->topBarSlot(),
                When::make(
                    fn (): bool => $this->isProfileEnabled(),
                    fn (): array => [$this->getProfileComponent()],
                ),
                Div::make()->class('menu-divider menu-divider--vertical'),
                When::make(
                    fn (): bool => $this->hasThemes() && ! $this->isAlwaysDark(),
                    static fn (): array => [ThemeSwitcher::make()],
                ),
                Div::make(array_filter([
                    $this->mobileMode ? null : Burger::make()->topbar(),
                ]))->class('menu-burger'),
            ])->class('menu-actions')->name('topbar-actions'),
        ]);
    }

    protected function getBottomBarComponent(): BottomBar
    {
        $configService = app(MenuConfigService::class);
        $menuItems = $this->isMenuManagerEnabled()
            ? $configService->getItemsForZone('bottom_bar')
            : null;

        $bottomBar = BottomBar::make([
            Fragment::make([
                Menu::make($menuItems)->top(),
            ])->class('menu menu--horizontal')->name('bottombar-menu'),
        ]);

        if ($this->isMenuManagerEnabled() && $configService->isBottomBarAlwaysVisible()) {
            $bottomBar->alwaysVisible();
        }

        return $bottomBar;
    }

    /**
     * Переопределяет build() AppLayout: TopBar и BottomBar показываются
     * при наличии пунктов в соответствующих зонах (shouldShowTopBar / shouldShowBottomBar).
     */
    public function build(): Layout
    {
        $contentCentered = property_exists($this, 'contentCentered') && $this->contentCentered;
        $contentSimpled = property_exists($this, 'contentSimpled') && $this->contentSimpled;

        return Layout::make([
            Html::make([
                $this->getHeadComponent(),
                Body::make([
                    Wrapper::make([
                        When::make(
                            fn (): bool => $this->shouldShowTopBar(),
                            fn (): array => [$this->getTopBarComponent()],
                        ),
                        When::make(
                            fn (): bool => $this->sidebar,
                            fn (): array => [$this->getSidebarComponent()],
                        ),
                        When::make(
                            fn (): bool => $this->secondBar,
                            fn (): array => [$this->getSecondBarComponent()],
                        ),
                        When::make(
                            fn (): bool => $this->mobileMode || $this->shouldShowBottomBar(),
                            fn (): array => [$this->getBottomBarComponent()],
                        ),
                        Div::make([
                            Fragment::make([
                                Flash::make(),
                                $this->getHeaderComponent(),
                                Content::make($this->getContentComponents()),
                                $this->getFooterComponent(),
                            ])->class(['layout-page', 'layout-page-simple' => $contentSimpled])
                                ->name(\MoonShine\Laravel\Layouts\BaseLayout::CONTENT_FRAGMENT_NAME),
                        ])->class(['layout-main', 'layout-main-centered' => $contentCentered])
                            ->customAttributes(['id' => \MoonShine\Laravel\Layouts\BaseLayout::CONTENT_ID]),
                    ]),
                ]),
            ])
                ->customAttributes(['lang' => $this->getHeadLang()])
                ->withAlpineJs()
                ->when(
                    $this->hasThemes() || $this->isAlwaysDark(),
                    fn (Html $html): Html => $html->withThemes($this->isAlwaysDark())
                ),
        ]);
    }
}
