<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Cargo\Actions\UpdateStorageTank;
use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\TankCapacityExceededException;
use App\Filament\Resources\CargoMovements\Pages\CreateCargoMovement;
use App\Filament\Resources\CargoMovements\Pages\ListCargoMovements;
use App\Filament\Resources\StorageTanks\Pages\EditStorageTank;
use App\Filament\Resources\StorageTanks\Pages\ListStorageTanks;
use App\Filament\Widgets\CargoStatsOverview;
use App\Filament\Widgets\RecentRejections;
use App\Filament\Widgets\TankLevels;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_dashboard_and_resource_pages_load(): void
    {
        foreach (['/admin', '/admin/storage-tanks', '/admin/storage-tanks/create', '/admin/cargo-movements', '/admin/cargo-movements/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_home_page_redirects_to_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_the_tank_options_follow_the_selected_commodity(): void
    {
        $cpo = StorageTank::factory()->create(['code' => 'CPO-9', 'commodity' => Commodity::CrudePalmOil]);
        $pfad = StorageTank::factory()->create(['code' => 'PFAD-9', 'commodity' => Commodity::PalmFattyAcidDistillate]);

        Livewire::test(CreateCargoMovement::class)
            ->fillForm(['commodity' => 'PFAD'])
            ->assertSee('PFAD-9')
            ->assertDontSee('CPO-9')
            ->fillForm(['commodity' => 'CPO', 'storage_tank_id' => $cpo->id])
            ->assertSee('CPO-9')
            ->assertDontSee('PFAD-9');

        $this->assertNotNull($pfad->id);
    }

    public function test_changing_the_commodity_clears_the_selected_tank(): void
    {
        $tank = StorageTank::factory()->create(['commodity' => Commodity::CrudePalmOil]);

        Livewire::test(CreateCargoMovement::class)
            ->fillForm(['commodity' => 'CPO'])
            ->fillForm(['storage_tank_id' => $tank->id])
            ->fillForm(['commodity' => 'PFAD'])
            ->assertSchemaStateSet(['storage_tank_id' => null]);
    }

    public function test_the_preview_shows_the_net_quantity_and_resulting_balance(): void
    {
        $tank = StorageTank::factory()->holding(1_000)->create();

        Livewire::test(CreateCargoMovement::class)
            ->fillForm(['commodity' => 'CPO'])
            ->fillForm(['storage_tank_id' => $tank->id, 'gross_litres' => 100_000])
            ->assertSee('Transit loss (0.5%)')
            ->assertSee('500 L')
            ->assertSee('99,500 L')
            ->assertSee('100,500 L');
    }

    public function test_the_preview_warns_about_an_overflow_before_submission(): void
    {
        $tank = StorageTank::factory()->create(['capacity_litres' => 1_000]);

        Livewire::test(CreateCargoMovement::class)
            ->fillForm(['commodity' => 'CPO'])
            ->fillForm(['storage_tank_id' => $tank->id, 'gross_litres' => 100_000])
            ->assertSee('This movement would be rejected')
            ->assertSee('cannot hold 99,500 L');

        $this->assertSame(0, CargoMovement::query()->count());
    }

    public function test_the_form_labels_and_loss_preview_follow_the_direction(): void
    {
        $tank = StorageTank::factory()->holding(1_000)->create();

        Livewire::test(CreateCargoMovement::class)
            ->fillForm(['commodity' => 'CPO'])
            ->fillForm(['storage_tank_id' => $tank->id, 'gross_litres' => 100, 'direction' => MovementDirection::Outbound->value])
            ->assertSee('Truck registration')
            ->assertSee('Leaves the tank')
            ->assertDontSee('Transit loss (0.5%)');
    }

    public function test_submitting_the_form_settles_through_the_pipeline(): void
    {
        $tank = StorageTank::factory()->create();

        Livewire::test(CreateCargoMovement::class)
            ->fillForm($this->formData($tank, 100_000))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('VSL-TEST applied');

        $movement = CargoMovement::query()->where('external_reference', 'VSL-TEST')->firstOrFail();

        $this->assertSame(MovementStatus::Applied, $movement->status);
        $this->assertSame(99_500, $tank->fresh()->current_volume_litres);
    }

    public function test_an_overflow_submission_is_recorded_as_rejected_and_reported(): void
    {
        $tank = StorageTank::factory()->create(['capacity_litres' => 1_000]);

        Livewire::test(CreateCargoMovement::class)
            ->fillForm($this->formData($tank, 100_000))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('VSL-TEST was rejected');

        $this->assertSame(0, $tank->fresh()->current_volume_litres);
    }

    public function test_a_duplicate_reference_is_refused_by_the_form(): void
    {
        $tank = StorageTank::factory()->create();
        CargoMovement::factory()->create(['external_reference' => 'VSL-TEST', 'storage_tank_id' => $tank->id]);

        Livewire::test(CreateCargoMovement::class)
            ->fillForm($this->formData($tank, 100_000))
            ->call('create')
            ->assertHasFormErrors(['external_reference' => 'unique']);
    }

    public function test_the_retry_action_applies_a_rejected_movement_once_stock_exists(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);
        Livewire::test(CreateCargoMovement::class)
            ->fillForm([...$this->formData($tank, 500), 'direction' => 'outbound'])
            ->call('create');
        $delivery = CargoMovement::query()->where('external_reference', 'VSL-TEST')->firstOrFail();
        $this->assertSame(MovementStatus::Rejected, $delivery->status);
        $tank->current_volume_litres = 1_000;
        $tank->save();

        Livewire::test(ListCargoMovements::class)
            ->callAction(TestAction::make('retry')->table($delivery))
            ->assertNotified('VSL-TEST applied');

        $this->assertSame(500, $tank->fresh()->current_volume_litres);
    }

    public function test_the_retry_action_is_hidden_for_applied_movements(): void
    {
        $tank = StorageTank::factory()->create();
        Livewire::test(CreateCargoMovement::class)->fillForm($this->formData($tank, 100))->call('create');
        $movement = CargoMovement::query()->where('external_reference', 'VSL-TEST')->firstOrFail();

        Livewire::test(ListCargoMovements::class)
            ->assertActionHidden(TestAction::make('retry')->table($movement));
    }

    public function test_a_tank_with_a_balance_cannot_be_shrunk_below_it_or_change_commodity(): void
    {
        $tank = StorageTank::factory()->holding(500)->create(['capacity_litres' => 1_000]);

        Livewire::test(EditStorageTank::class, ['record' => $tank->getRouteKey()])
            ->assertFormFieldDisabled('commodity')
            ->fillForm(['capacity_litres' => 499])
            ->call('save')
            ->assertHasFormErrors(['capacity_litres']);

        $this->assertSame(1_000, $tank->fresh()->capacity_litres);
    }

    public function test_a_form_opened_before_a_balance_change_validates_against_the_live_balance(): void
    {
        $tank = StorageTank::factory()->create(['capacity_litres' => 1_000]);
        $page = Livewire::test(EditStorageTank::class, ['record' => $tank->getRouteKey()])
            ->fillForm(['capacity_litres' => 400]);

        $current = StorageTank::query()->findOrFail($tank->id);
        $current->current_volume_litres = 500;
        $current->save();

        $page->call('save')->assertHasFormErrors(['capacity_litres']);

        $this->assertSame(1_000, $tank->fresh()->capacity_litres);
    }

    public function test_a_save_from_a_stale_copy_checks_the_live_balance(): void
    {
        $staleCopy = StorageTank::factory()->create(['capacity_litres' => 1_000]);

        $current = StorageTank::query()->findOrFail($staleCopy->id);
        $current->current_volume_litres = 500;
        $current->save();

        try {
            app(UpdateStorageTank::class)->handle($staleCopy, ['capacity_litres' => 400]);
            $this->fail('Expected the update to be refused.');
        } catch (TankCapacityExceededException) {
            $this->assertSame(1_000, $current->fresh()->capacity_litres);
        }
    }

    public function test_the_widgets_show_live_figures(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'CPO-9', 'capacity_litres' => 100_000]);
        Livewire::test(CreateCargoMovement::class)->fillForm($this->formData($tank, 90_000))->call('create');

        Livewire::test(CargoStatsOverview::class)->assertSee('89,550 L')->assertSee('450 L');
        Livewire::test(TankLevels::class)->assertSee('CPO-9')->assertSee('89.6%');
        Livewire::test(RecentRejections::class)->assertSee('No rejected movements');
    }

    public function test_the_tank_list_shows_balance_and_available_room(): void
    {
        $tank = StorageTank::factory()->holding(250)->create(['capacity_litres' => 1_000]);

        Livewire::test(ListStorageTanks::class)
            ->assertCanSeeTableRecords([$tank])
            ->assertSee('250 L')
            ->assertSee('750 L');
    }

    /** @return array<string, mixed> */
    private function formData(StorageTank $tank, int $litres): array
    {
        return [
            'direction' => 'inbound',
            'commodity' => $tank->commodity->value,
            'storage_tank_id' => $tank->id,
            'counterparty' => 'MV Test',
            'gross_litres' => $litres,
            'occurred_at' => '2026-11-01 08:00',
            'external_reference' => 'VSL-TEST',
        ];
    }
}
