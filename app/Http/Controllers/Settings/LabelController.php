<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLabelOverridesRequest;
use App\Models\AuditLog;
use App\Models\LabelOverride;
use App\Support\TeamRoleLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L3-C — the white-label naming panel (/settings/labels, gated by
 * setting.labels: admin only). Renames the DISPLAY label of the overridable
 * role slugs; slugs, permissions, and enforcement never change. The admin slug
 * is not offered (D2) and a forged submit for it fails request validation.
 */
class LabelController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionName::SettingLabels->value), 403);

        $overrides = LabelOverride::query()->pluck('label', 'key');

        return Inertia::render('settings/Labels', [
            'rows' => array_map(
                fn (string $slug): array => [
                    'slug' => $slug,
                    'default' => TeamRoleLabels::defaultLabel($slug),
                    'override' => $overrides[$slug] ?? null,
                ],
                TeamRoleLabels::overridableSlugs(),
            ),
        ]);
    }

    public function update(UpdateLabelOverridesRequest $request): RedirectResponse
    {
        /** @var array<string, string|null> $input */
        $input = $request->validated('overrides');

        $changes = [];

        foreach (TeamRoleLabels::overridableSlugs() as $slug) {
            // Normalise: markup stripped, whitespace trimmed. Empty after that
            // = RESET to the default (delete the row) — never a blank label.
            $clean = trim(strip_tags((string) ($input[$slug] ?? '')));
            $before = TeamRoleLabels::label($slug);

            // Writes MUST go through model instances: the cache flush rides on
            // Eloquent events (see LabelOverride) — bulk query-builder
            // update()/delete() would leave every request reading stale labels.
            if ($clean === '') {
                LabelOverride::query()->where('key', $slug)->first()?->delete();
            } else {
                LabelOverride::updateOrCreate(['key' => $slug], ['label' => $clean]);
            }

            $after = TeamRoleLabels::label($slug);

            if ($before !== $after) {
                $changes[$slug] = ['from' => $before, 'to' => $after];
            }
        }

        if ($changes !== []) {
            AuditLog::record($request->user(), null, 'settings.labels.updated', [
                'changes' => $changes,
            ]);
        }

        return back()->with('success', 'Label tampilan disimpan.');
    }
}
