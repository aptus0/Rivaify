<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Models\Marketing\MarketingCampaign;

class AdminMarketingCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = MarketingCampaign::query()->latest()->get();

        return response()->json(['data' => $campaigns->map(fn ($campaign) => $this->present($campaign)), 'summary' => [
            'total' => $campaigns->count(), 'active' => $campaigns->where('status', 'active')->count(),
            'scheduled' => $campaigns->where('status', 'scheduled')->count(), 'attribution_available' => false,
        ]]);
    }

    public function store(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $campaign = MarketingCampaign::query()->create($this->payload($request, null) + ['currency' => $currentStore->store()->default_currency]);

        return response()->json(['data' => $this->present($campaign)], 201);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $campaign = MarketingCampaign::query()->where('ulid', $ulid)->firstOrFail();
        $campaign->update($this->payload($request, $campaign));

        return response()->json(['data' => $this->present($campaign->fresh())]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        MarketingCampaign::query()->where('ulid', $ulid)->firstOrFail()->delete();

        return response()->json(['deleted' => true]);
    }

    private function payload(Request $request, ?MarketingCampaign $campaign): array
    {
        $required = $campaign === null ? 'required' : 'sometimes';
        $validated = $request->validate([
            'name' => [$required, 'string', 'max:255'], 'channel' => [$required, 'in:online_store,email,instagram,facebook,tiktok'],
            'objective' => [$required, 'in:sales,traffic,awareness,retention'], 'status' => ['sometimes', 'in:draft,scheduled,active,paused,completed'],
            'budget' => ['nullable', 'numeric', 'min:0'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
            'content' => ['nullable', 'array'], 'content.message' => ['nullable', 'string', 'max:2000'],
        ]);

        $channel = $validated['channel'] ?? $campaign?->channel;
        $status = $validated['status'] ?? $campaign?->status ?? 'draft';
        $message = array_key_exists('content', $validated)
            ? ($validated['content']['message'] ?? null)
            : ($campaign?->content['message'] ?? null);
        $startsAt = array_key_exists('starts_at', $validated)
            ? $this->date($validated['starts_at'])
            : $campaign?->starts_at;
        $endsAt = array_key_exists('ends_at', $validated)
            ? $this->date($validated['ends_at'])
            : $campaign?->ends_at;

        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => ['Bitiş tarihi başlangıç tarihinden sonra olmalıdır.']]);
        }
        if ($status === 'active' && $channel !== 'online_store') {
            throw ValidationException::withMessages(['status' => ['Bu sürümde yalnızca Online Mağaza kampanyaları doğrudan yayınlanabilir.']]);
        }
        if ($status === 'active' && trim((string) $message) === '') {
            throw ValidationException::withMessages(['content.message' => ['Yayınlanacak mağaza duyurusu için mesaj zorunludur.']]);
        }

        return $validated;
    }

    private function date(mixed $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse((string) $value);
    }

    private function present(MarketingCampaign $campaign): array
    {
        return ['id' => $campaign->ulid, 'name' => $campaign->name, 'channel' => $campaign->channel, 'objective' => $campaign->objective, 'status' => $campaign->status, 'budget' => $campaign->budget, 'currency' => $campaign->currency, 'starts_at' => $campaign->starts_at?->toIso8601String(), 'ends_at' => $campaign->ends_at?->toIso8601String(), 'message' => $campaign->content['message'] ?? null, 'created_at' => $campaign->created_at?->toIso8601String()];
    }
}
