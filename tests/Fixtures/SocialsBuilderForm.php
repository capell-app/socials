<?php

declare(strict_types=1);

namespace Capell\Socials\Tests\Fixtures;

use Capell\Admin\Filament\Components\Forms\Editor\ContentBuilder;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** @property Schema $form */
final class SocialsBuilderForm extends Component implements HasForms
{
    use InteractsWithForms;

    public Site $site;

    public ?Page $page = null;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed> */
    public array $saved = [];

    /** @param array<string, mixed> $state */
    public function mount(int $siteId, array $state = [], ?int $pageId = null): void
    {
        $this->site = Site::query()->findOrFail($siteId);
        $this->page = $pageId === null ? null : Page::query()->findOrFail($pageId);
        $this->form->fill(['blocks' => [['type' => 'socials', 'data' => ['mode' => 'follow', 'label_style' => 'inherit', 'link_target' => 'inherit', 'alignment' => 'start', ...$state]]]]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->model($this->page ?? $this->site)->statePath('data')->components([
            ContentBuilder::make('blocks'),
        ]);
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): View
    {
        return view()->file(__DIR__ . '/socials-builder-form.blade.php');
    }
}
