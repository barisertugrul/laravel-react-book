<?php

namespace App\Http\Controllers;

use App\Http\Requests\CardRequest;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canCreate = Gate::inspect('create', Card::class);

        return Inertia::render('cards/index', [
            'cards' => $user->cards()->latest()->get(),
            'plan' => $user->plan->toArray(),
            'canCreate' => $canCreate->allowed(),
            'limitMessage' => $canCreate->message(),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $response = Gate::inspect('create', Card::class);

        if ($response->denied()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $response->message()]);

            return to_route('cards.index');
        }

        return Inertia::render('cards/create', [
            'plan' => $request->user()->plan->toArray(),
        ]);
    }

    public function store(CardRequest $request): RedirectResponse
    {
        Gate::authorize('create', Card::class);

        // Kart ve bağlantıları birlikte kaydedilir; biri başarısız olursa hiçbiri kalmaz.
        DB::transaction(function () use ($request) {
            $card = $request->user()->cards()->create($request->cardData());
            $card->links()->createMany($request->links());
            $this->handleAvatar($request, $card);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kartınız oluşturuldu.']);

        return to_route('cards.index');
    }

    public function edit(Request $request, Card $card): Response
    {
        Gate::authorize('update', $card);

        return Inertia::render('cards/edit', [
            'card' => $card->load('links'),
            'plan' => $request->user()->plan->toArray(),
        ]);
    }

    public function update(CardRequest $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        DB::transaction(function () use ($request, $card) {
            $card->update($request->cardData());
            // En basit eşitleme yöntemi: eski bağlantıları sil, gelenleri yeniden ekle.
            $card->links()->delete();
            $card->links()->createMany($request->links());
            $this->handleAvatar($request, $card);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Değişiklikler kaydedildi.']);

        return to_route('cards.index');
    }

    public function destroy(Card $card): RedirectResponse
    {
        Gate::authorize('delete', $card);

        if ($card->avatar_path) {
            Storage::disk('public')->delete($card->avatar_path);
        }
        $card->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kart silindi.']);

        return to_route('cards.index');
    }

    private function handleAvatar(CardRequest $request, Card $card): void
    {
        if (! $request->hasFile('avatar') && ! $request->boolean('remove_avatar')) {
            return;
        }

        if ($card->avatar_path) {
            Storage::disk('public')->delete($card->avatar_path);
        }

        $card->update([
            'avatar_path' => $request->hasFile('avatar')
                ? $request->file('avatar')->store('avatars', 'public')
                : null,
        ]);
    }
}
