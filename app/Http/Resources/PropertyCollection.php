<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

class PropertyCollection extends ResourceCollection
{
    public $collects = PropertyResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Fully override the response shape so we control:
     *   • the paginator envelope (scalar meta values)
     *   • the item serialization (via PropertyResource)
     *   • any `->additional()` the controller attached
     *
     * Handles every possible input:
     *   • paginator with items
     *   • paginator with no items (empty page)
     *   • raw collection
     *   • null / empty collection
     */
    public function toResponse($request)
    {
        // ─────────────────────────────────────────────
        // 1. Resolve the underlying collection — never null.
        // ─────────────────────────────────────────────
        $collection = $this->collection;

        if ($collection === null) {
            // Laravel sometimes hands us a null collection when the
            // paginator's items collection is empty. Recover from the
            // paginator directly.
            if ($this->resource instanceof AbstractPaginator) {
                $collection = collect($this->resource->items());
            } else {
                $collection = collect();
            }
        }

        // ─────────────────────────────────────────────
        // 2. Normalize meta from the paginator (if any).
        // ─────────────────────────────────────────────
        $meta = null;
        if ($this->resource instanceof AbstractPaginator) {
            // Wrap in try/catch because some paginator implementations
            // call ->first() on their items internally, which throws
            // when the collection is empty or malformed.
            try {
                $p = $this->resource->toArray();
                $meta = [
                    'current_page' => $p['current_page']   ?? 1,
                    'last_page'    => $p['last_page']      ?? 1,
                    'per_page'     => $p['per_page']       ?? 20,
                    'total'        => $p['total']          ?? 0,
                    'from'         => $p['from']           ?? null,
                    'to'           => $p['to']             ?? null,
                    'links'        => $p['links']          ?? [],
                    'path'         => $p['path']           ?? null,
                ];
            } catch (\Throwable $e) {
                // Fall back to a safe empty-page envelope.
                $meta = [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => 20,
                    'total'        => 0,
                    'from'         => null,
                    'to'           => null,
                    'links'        => [],
                    'path'         => null,
                ];
            }
        }

        // ─────────────────────────────────────────────
        // 3. Serialize each item through PropertyResource.
        // ─────────────────────────────────────────────
        $items = $collection
            ->map(function ($item) use ($request) {
                if ($item instanceof PropertyResource) {
                    return $item->resolve($request);
                }
                return (new PropertyResource($item))->resolve($request);
            })
            ->values()
            ->all();

        // ─────────────────────────────────────────────
        // 4. Merge any additional data the controller passed.
        // ─────────────────────────────────────────────
        $additional = $this->additional ?? [];

        $payload = [
            'data' => $items,
            ...$additional,
        ];

        if ($meta !== null) {
            $payload['meta'] = array_merge(
                $additional['meta'] ?? [],
                $meta,
            );
        }

        return response()->json($payload);
    }
}