<?php

namespace App\Livewire\Admin;

use App\Models\Communication;
use App\Models\Product;
use App\Models\Team;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TeamsCrud extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public string $lifecycleFilter = '';

    public string $activityFilter = '';

    public ?int $editingId = null;

    public string $current_name = '';

    public string $captain_name = '';

    public string $phone = '';

    public string $email = '';

    public ?int $favorite_product_id = null;

    // --- История коммуникаций (Модуль 7) ---
    public ?int $historyTeamId = null;

    // --- Подключение Telegram ---
    public ?int $telegramTeamId = null;

    #[Computed]
    public function teams()
    {
        return Team::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('current_name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('captain_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->lifecycleFilter !== '', fn ($q) => $q->where('lifecycle_stage', $this->lifecycleFilter))
            ->when($this->activityFilter !== '', fn ($q) => $q->where('activity_status', $this->activityFilter))
            ->orderByDesc('last_game_at')
            ->paginate(20);
    }

    #[Computed]
    public function products()
    {
        return Product::orderBy('name')->get();
    }

    #[Computed]
    public function historyForOpenTeam()
    {
        if (! $this->historyTeamId) {
            return collect();
        }

        return Communication::where('team_id', $this->historyTeamId)
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->get();
    }

    #[Computed]
    public function telegramTeamForModal(): ?Team
    {
        return $this->telegramTeamId ? Team::find($this->telegramTeamId) : null;
    }

    public function lifecycleColor(?string $stage): string
    {
        return match ($stage) {
            'новая' => 'zinc',
            'развивающаяся' => 'blue',
            'постоянная' => 'teal',
            'ядро сообщества' => 'purple',
            default => 'zinc',
        };
    }

    public function activityColor(?string $status): string
    {
        return match ($status) {
            'активная' => 'green',
            'остывающая' => 'amber',
            'спящая' => 'zinc',
            'потерянная' => 'red',
            default => 'zinc',
        };
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLifecycleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActivityFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->modal('team-form')->show();
    }

    public function edit(int $id): void
    {
        $team = Team::findOrFail($id);

        $this->editingId = $team->id;
        $this->current_name = $team->current_name;
        $this->captain_name = $team->captain_name ?? '';
        $this->phone = $team->phone;
        $this->email = $team->email ?? '';
        $this->favorite_product_id = $team->favorite_product_id;

        $this->modal('team-form')->show();
    }

    public function save(): void
    {
        $normalizedPhone = preg_replace('/\D/', '', $this->phone);

        $this->validate([
            'current_name' => 'required|string|max:255',
            'captain_name' => 'nullable|string|max:255',
            'phone' => 'required|string',
            'email' => 'nullable|email|max:255',
            'favorite_product_id' => 'nullable|integer|exists:products,id',
        ]);

        $phoneExistsQuery = Team::where('phone', $normalizedPhone);
        if ($this->editingId) {
            $phoneExistsQuery->where('id', '!=', $this->editingId);
        }
        if ($phoneExistsQuery->exists()) {
            $this->addError('phone', 'Команда с таким телефоном уже существует.');

            return;
        }

        $data = [
            'current_name' => $this->current_name,
            'captain_name' => $this->captain_name ?: null,
            'phone' => $normalizedPhone,
            'email' => $this->email ?: null,
            'favorite_product_id' => $this->favorite_product_id,
        ];

        if ($this->editingId) {
            Team::findOrFail($this->editingId)->update($data);
        } else {
            Team::create($data);
        }

        $this->modal('team-form')->close();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Team::findOrFail($id)->delete();
    }

    // --- История коммуникаций (Модуль 7) ---

    public function viewHistory(int $id): void
    {
        $this->historyTeamId = $id;
        $this->modal('team-history')->show();
    }

    // --- Подключение Telegram ---

    public function openTelegram(int $id): void
    {
        $this->telegramTeamId = $id;
        $this->modal('team-telegram')->show();
    }

    public function unlinkTelegram(): void
    {
        Team::whereKey($this->telegramTeamId)->update([
            'telegram_chat_id' => null,
            'telegram_linked_at' => null,
        ]);

        unset($this->telegramTeamForModal);
    }

    public function closeModal(): void
    {
        $this->modal('team-form')->close();
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->current_name = '';
        $this->captain_name = '';
        $this->phone = '';
        $this->email = '';
        $this->favorite_product_id = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.admin.teams-crud')
            ->layoutData(['title' => 'Команды']);
    }
}
