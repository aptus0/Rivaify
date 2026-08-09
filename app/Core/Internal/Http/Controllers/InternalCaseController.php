<?php

namespace App\Core\Internal\Http\Controllers;

use App\Core\Internal\Models\OperationCase;
use App\Core\Internal\Support\InternalStaff;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalCaseController extends Controller
{
    public function index(Request $request, InternalStaff $staff): JsonResponse
    {
        $current = $staff->current();
        $tab = $request->string('tab', 'inbox')->toString();

        $query = OperationCase::query()
            ->with(['store:id,ulid,name,slug,status', 'assignee:id,ulid,name'])
            ->latest('opened_at');

        match ($tab) {
            'mine' => $current ? $query->where('assigned_to', $current->id)->whereNotIn('status', ['RESOLVED', 'CLOSED']) : $query->whereRaw('1 = 0'),
            'waiting_merchant' => $query->where('status', 'WAITING_MERCHANT'),
            'waiting_provider' => $query->where('status', 'WAITING_PROVIDER'),
            'critical' => $query->where('priority', 'CRITICAL')->whereNotIn('status', ['RESOLVED', 'CLOSED']),
            'resolved' => $query->whereIn('status', ['RESOLVED', 'CLOSED']),
            default => $query->whereNotIn('status', ['RESOLVED', 'CLOSED']),
        };

        return response()->json(['data' => [
            'tabs' => $this->tabs($current?->id),
            'items' => $query->limit(50)->get()->map(fn (OperationCase $case) => [
                'id' => $case->ulid,
                'case_number' => $case->case_number,
                'type' => $case->type,
                'title' => $case->title,
                'priority' => $case->priority,
                'status' => $case->status,
                'store' => $case->store ? [
                    'id' => $case->store->ulid,
                    'name' => $case->store->name,
                    'slug' => $case->store->slug,
                    'status' => $case->store->status->value ?? (string) $case->store->status,
                ] : null,
                'assigned_to' => $case->assignee ? [
                    'id' => $case->assignee->ulid,
                    'name' => $case->assignee->name,
                ] : null,
                'opened_at' => $case->opened_at?->toIso8601String(),
                'age' => $case->opened_at?->diffForHumans(short: true),
            ]),
        ]]);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function tabs(?int $staffId): array
    {
        return [
            ['key' => 'inbox', 'label' => 'Inbox', 'count' => OperationCase::query()->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count()],
            ['key' => 'mine', 'label' => 'Mine', 'count' => $staffId ? OperationCase::query()->where('assigned_to', $staffId)->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count() : 0],
            ['key' => 'waiting_merchant', 'label' => 'Waiting Merchant', 'count' => OperationCase::query()->where('status', 'WAITING_MERCHANT')->count()],
            ['key' => 'waiting_provider', 'label' => 'Waiting Provider', 'count' => OperationCase::query()->where('status', 'WAITING_PROVIDER')->count()],
            ['key' => 'critical', 'label' => 'Critical', 'count' => OperationCase::query()->where('priority', 'CRITICAL')->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count()],
            ['key' => 'resolved', 'label' => 'Resolved', 'count' => OperationCase::query()->whereIn('status', ['RESOLVED', 'CLOSED'])->count()],
        ];
    }
}
