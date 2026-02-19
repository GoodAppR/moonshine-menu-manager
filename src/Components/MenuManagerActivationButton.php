<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Components;

use MoonShine\CustomMenuManager\Pages\MenuManagerPage;
use MoonShine\Support\UriKey;
use MoonShine\UI\Components\MoonShineComponent;

final class MenuManagerActivationButton extends MoonShineComponent
{
    protected string $view = 'moonshine-menu-manager::activation-button';

    public function __construct()
    {
        parent::__construct();
    }

    protected function viewData(): array
    {
        $page = moonshine()->getPages()->findByClass(MenuManagerPage::class);

        $url = $page
            ? moonshineRouter()->getEndpoints()->toPage($page)
            : $this->buildFallbackUrl();

        return ['url' => $url];
    }

    private function buildFallbackUrl(): string
    {
        $prefix = config('moonshine.prefix', 'admin');
        $pagePrefix = config('moonshine.page_prefix', 'page');
        $uri = (new UriKey(MenuManagerPage::class))->generate();

        return url("/{$prefix}/{$pagePrefix}/{$uri}");
    }
}
