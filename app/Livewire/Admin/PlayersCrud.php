<?php

namespace App\Livewire\Admin;

use App\Models\Player;
use App\Models\Team;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PlayersCrud extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public ?int $editingId = null;
    public string $name = '';
    public string $phone = '';

    // Привязка игрока к командам
    public array $selectedTeamIds = [];
    public string $teamSearch = '';

    #[Computed]
    public function players()
    {
        return Player::query()
            ->withCount('teams')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);
    }

    #[Computed]
    public function availableTeams()
    {
        return Team::query()
            ->when($this->teamSearch !== '', fn ($q) => $q->where('current_name', 'like', "%{$this->teamSearch}%"))
            ->orderBy('current_name')
            ->limit(30)
            ->get();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->modal('player-form')->show();
    }

    public function edit(int $id): void
    {
        $player = Player::with('teams')->findOrFail($id);

        $this->editingId = $player->id;
        $this->name = $player->name ?? '';
        $this->phone = $player->phone ?? '';
        $this->selectedTeamIds = $player->teams->pluck('id')->toArray();

        $this->resetErrorBag();
        $this->modal('player-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:32',
        ]);

        $normalizedPhone = $this->phone !== '' ? preg_replace('/\D/', '', $this->phone) : null;

        if ($normalizedPhone) {
            $exists = Player::where('phone', $normalizedPhone)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->exists();

            if ($exists) {
                $this->addError('phone', 'Игрок с таким телефоном уже существует.');
                return;
            }
        }

        $data = [
            'name' => $this->name ?: null,
            'phone' => $normalizedPhone,
        ];

        if ($this->editingId) {
            $player = Player::findOrFail($this->editingId);
            $player->update($data);
        } else {
            $player = Player::create($data);
        }

        $player->teams()->sync($this->selectedTeamIds);

        $this->modal('player-form')->close();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Player::findOrFail($id)->delete();
    }

    public function toggleTeam(int $teamId): void
    {
        if (in_array($teamId, $this->selectedTeamIds, true)) {
            $this->selectedTeamIds = array_values(array_diff($this->selectedTeamIds, [$teamId]));
        } else {
            $this->selectedTeamIds[] = $teamId;
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->phone = '';
        $this->selectedTeamIds = [];
        $this->teamSearch = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.admin.players-crud')
            ->layoutData(['title' => 'Игроки']);
    }
}
