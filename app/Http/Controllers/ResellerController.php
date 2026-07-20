<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesModelAbilities;
use App\Http\Requests\StoreResellerRequest;
use App\Http\Requests\UpdateResellerRequest;
use App\Models\Reseller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    use AuthorizesRequests, ProvidesModelAbilities;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Reseller::class);

        $resellers = Reseller::query()
            ->withCount('customers')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        return Inertia::render('Resellers/Index', [
            'tree' => $this->buildTree($resellers, null),
            'stats' => $this->stats(),
            'can' => $this->abilities($request, Reseller::class),
        ]);
    }

    /**
     * Summary metrics for the index header cards.
     *
     * @return array{total: int, active: int, topLevel: int}
     */
    private function stats(): array
    {
        return [
            'total' => Reseller::count(),
            'active' => Reseller::has('customers')->orHas('transactions')->count(),
            'topLevel' => Reseller::whereNull('parent_id')->count(),
        ];
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Reseller::class);

        return Inertia::render('Resellers/Create', [
            'parentOptions' => Reseller::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreResellerRequest $request): RedirectResponse
    {
        Reseller::create($request->validated());

        return redirect()->route('resellers.index')
            ->with('success', 'Reseller berhasil ditambahkan.');
    }

    public function edit(Request $request, Reseller $reseller): Response
    {
        $this->authorize('update', $reseller);

        // A reseller may not be re-parented under itself or its own descendants.
        $forbidden = array_merge([$reseller->id], $reseller->descendantIds());

        return Inertia::render('Resellers/Edit', [
            'reseller' => [
                'id' => $reseller->id,
                'name' => $reseller->name,
                'parent_id' => $reseller->parent_id,
            ],
            'parentOptions' => Reseller::whereNotIn('id', $forbidden)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateResellerRequest $request, Reseller $reseller): RedirectResponse
    {
        $reseller->update($request->validated());

        return redirect()->route('resellers.index')
            ->with('success', 'Reseller berhasil diperbarui.');
    }

    public function destroy(Request $request, Reseller $reseller): RedirectResponse
    {
        $this->authorize('delete', $reseller);

        if ($reseller->customers()->exists() || $reseller->transactions()->exists()) {
            return back()->with('error', 'Reseller tidak dapat dihapus karena masih memiliki customer atau transaksi. Pindahkan data tersebut terlebih dahulu.');
        }

        // Direct children are re-parented to the top level (schema uses nullOnDelete).
        $reseller->delete();

        return redirect()->route('resellers.index')
            ->with('success', 'Reseller berhasil dihapus.');
    }

    /**
     * Recursively build a nested tree from the flat reseller collection.
     *
     * @param  Collection<int, Reseller>  $resellers
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $resellers, ?int $parentId): array
    {
        return $resellers
            ->where('parent_id', $parentId)
            ->map(fn (Reseller $reseller) => [
                'id' => $reseller->id,
                'name' => $reseller->name,
                'parent_id' => $reseller->parent_id,
                'customers_count' => $reseller->customers_count,
                'children' => $this->buildTree($resellers, $reseller->id),
            ])
            ->values()
            ->all();
    }
}
