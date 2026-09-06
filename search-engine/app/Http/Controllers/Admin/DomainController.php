<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlQueue;
use App\Models\Domain;
use App\Services\CrawlManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function index(Request $request): Response
    {
        $domains = Domain::query()
            ->withCount([
                'crawlQueue as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'crawlQueue as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Domains', [
            'domains' => $domains,
        ]);
    }

    public function store(Request $request, CrawlManager $crawlManager): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url', 'max:500'],
        ]);

        $crawlManager->addDomain($validated['url']);

        $this->invalidateCaches();

        return back()->with('success', 'Domain added to crawl queue.');
    }

    public function update(Request $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,active,paused,blocked'],
            'max_depth' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'crawl_delay_ms' => ['sometimes', 'integer', 'min:0', 'max:60000'],
        ]);

        $domain->update($validated);

        $this->invalidateCaches();

        return back()->with('success', 'Domain updated.');
    }

    public function recrawl(Domain $domain): RedirectResponse
    {
        CrawlQueue::query()->insertOrIgnore([[
            'domain_id' => $domain->id,
            'url' => $domain->base_url,
            'url_hash' => hash('sha256', $domain->base_url),
            'priority' => 20,
            'depth' => 0,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($domain->status !== 'active') {
            $domain->update(['status' => 'active']);
        }

        $this->invalidateCaches();

        return back()->with('success', 'Re-crawl queued.');
    }

    public function destroy(Domain $domain): RedirectResponse
    {
        DB::transaction(function () use ($domain) {
            $domain->pages()->delete();
            $domain->crawlQueue()->delete();
            $domain->crawlLogs()->delete();
            $domain->delete();
        });

        $this->invalidateCaches();

        return back()->with('success', 'Domain and its pages deleted.');
    }

    protected function invalidateCaches(): void
    {
        try {
            Cache::store('redis')->forget('admin:dashboard:stats');
            Cache::store('redis')->forget('search:domains');
        } catch (\Throwable $e) {
            // Cache store unreachable; nothing to invalidate.
        }
    }
}
