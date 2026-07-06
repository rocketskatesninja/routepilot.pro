<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssembleLandingData;
use App\Actions\SaveLandingConfig;
use App\Http\Requests\UpdateLandingRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Services\PhotoService;
use App\Support\LandingConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant landing-page editor (tenant_admin). Renders the live-preview
 * editor and persists the section config. Images upload via a dedicated
 * endpoint so the config save itself stays pure JSON (no multipart coercion),
 * keeping the save fast + state-preserving.
 */
class LandingController extends Controller
{
    public function edit(Request $request, AssembleLandingData $assemble): Response
    {
        $tenant = $this->tenant($request);
        $config = LandingConfig::fromStored(TenantSetting::getFor($tenant->id, 'landing'));

        // Add image_url to each section for display (image_path is kept for save).
        $sections = [];
        foreach (is_array($config['sections'] ?? null) ? $config['sections'] : [] as $s) {
            if (! is_array($s)) {
                continue;
            }
            if (array_key_exists('image_path', $s)) {
                $s['image_url'] = $this->url($s['image_path']);
            }
            $sections[] = $s;
        }
        $config['sections'] = $sections;

        $ogPath = is_array($config['seo'] ?? null) ? ($config['seo']['og_image'] ?? null) : null;

        return Inertia::render('settings/Landing', [
            'config' => $config,
            'ogImageUrl' => $this->url($ogPath),
            'live' => $assemble->handle($tenant, $config),
            'agents' => User::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('role', ['agent', 'tenant_admin'])
                ->where('is_active', true)
                ->orderBy('first_name')
                ->get()
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->displayName(), 'avatar' => $u->avatar])
                ->all(),
            'recentPhotos' => VisitPhoto::query()
                ->whereHas('serviceVisit')
                ->latest('id')
                ->limit(24)
                ->get(['id', 'photo_path', 'is_showcase'])
                ->map(fn (VisitPhoto $p): array => [
                    'id' => $p->id,
                    'url' => $this->url($p->getAttribute('photo_path')),
                    'is_showcase' => (bool) $p->getAttribute('is_showcase'),
                ])
                ->all(),
        ]);
    }

    public function update(UpdateLandingRequest $request, SaveLandingConfig $save): RedirectResponse
    {
        $tenant = $this->tenant($request);

        $save->handle($tenant, [
            'sections' => $request->input('sections', []),
            'seo' => $request->input('seo', []),
            'theme' => $request->input('theme', []),
            'title' => $request->input('title', []),
            'social' => $request->input('social', []),
        ]);

        return back()->with('success', 'Landing page saved.');
    }

    /** Upload a single landing image (hero / OG); returns its stored path + URL. */
    public function uploadImage(Request $request, PhotoService $photos): JsonResponse
    {
        $tenant = $this->tenant($request);
        $request->validate(['image' => ['required', 'image', 'max:10240']]);

        $file = $request->file('image');
        abort_unless($file instanceof UploadedFile, 422);

        $path = $photos->store($file, 'landing/'.$tenant->id);

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    private function url(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /** The admin's own tenant. */
    private function tenant(Request $request): Tenant
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'tenant_admin', 403);
        $tenant = $user->tenant;
        abort_if($tenant === null, 403);

        return $tenant;
    }
}
