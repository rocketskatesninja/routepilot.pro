<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VisitPhoto;
use App\Support\LandingCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Visit-photo curation. The at-pool visit flow itself now lives entirely in the
 * offline field app (see App\Http\Controllers\Api\FieldController); this retains
 * only the tenant_admin action to feature a visit photo in the public gallery.
 */
class VisitController extends Controller
{
    /** Feature / un-feature a visit photo in the public gallery (tenant_admin curation). */
    public function toggleShowcase(Request $request, VisitPhoto $photo): RedirectResponse
    {
        $this->authorizeAdmin($request);
        // VisitPhoto isn't tenant-scoped — assert ownership via its (scoped) visit.
        $visit = $photo->serviceVisit()->first();
        abort_if($visit === null, 404);

        $validated = $request->validate(['is_showcase' => ['required', 'boolean']]);
        $photo->update(['is_showcase' => (bool) $validated['is_showcase']]);
        LandingCache::forget((int) $visit->getAttribute('tenant_id'));

        return back();
    }
}
