<?php

use App\Actions\Pos\OpenTableCheck;
use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Table;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
    Settings::flush();
});

it('keeps the card terminal off by default', function () {
    expect(Settings::cardTerminal())->toBeNull();

    $this->actingAs($this->staff)
        ->get(route('pos.index'))
        ->assertInertia(fn (Assert $page) => $page->where('card_terminal', null));
});

it('needs a token before the terminal is offered to the POS', function () {
    Settings::set('terminal.enabled', true);

    expect(Settings::cardTerminal())->toBeNull();
});

it('passes the terminal agent connection to the cashier and table check screens', function () {
    Settings::setMany([
        'terminal.enabled' => true,
        'terminal.agent_url' => 'http://localhost:8765/',
        'terminal.token' => 'terminal-token-123456',
    ]);
    $table = Table::factory()->create();
    app(OpenTableCheck::class)->handle($table, $this->staff);

    $expected = ['url' => 'http://localhost:8765', 'token' => 'terminal-token-123456'];

    $this->actingAs($this->staff)
        ->get(route('pos.index'))
        ->assertInertia(fn (Assert $page) => $page->where('card_terminal', $expected));

    $this->get(route('pos.tables.check', $table))
        ->assertInertia(fn (Assert $page) => $page->where('card_terminal', $expected));
});

it('saves the terminal settings from the admin page with a generated token', function () {
    $this->actingAs($this->staff);

    $component = Livewire::test(SettingsPage::class);
    $generatedToken = $component->get('data.terminal.token');

    $component
        ->set('data.terminal.enabled', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($generatedToken)->toHaveLength(32)
        ->and(Settings::cardTerminal())->toBe(['url' => 'http://localhost:8765', 'token' => $generatedToken]);
});
