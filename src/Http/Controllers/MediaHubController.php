<?php

namespace Outl1ne\NovaMediaHub\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Outl1ne\NovaMediaHub\Filters\Collection;
use Outl1ne\NovaMediaHub\Filters\Search;
use Outl1ne\NovaMediaHub\Filters\Sort;
use Outl1ne\NovaMediaHub\MediaHandler\Support\Filesystem;
use Outl1ne\NovaMediaHub\MediaHub;

class MediaHubController extends Controller
{
    public function getCollections(Request $request): JsonResponse
    {
        $defaultCollections = MediaHub::getDefaultCollections();

        $collections = MediaHub::getMediaModel()::distinct()
            ->pluck('collection_name')
            ->merge($defaultCollections)
            ->map(fn ($name) => str($name)->ucfirst())
            ->unique()
            ->values()
            ->toArray();

        return response()->json($collections, 200);
    }

    public function getMedia(): JsonResponse
    {
        $media = app(Pipeline::class)
            ->send(MediaHub::getQuery())->through([
                Collection::class,
                Search::class,
                Sort::class,
            ])->thenReturn()->paginate(72);

        $newCollection = $media->getCollection()->map->formatForNova();
        $media->setCollection($newCollection);

        return response()->json($media, 200);
    }

    public function uploadMediaToCollection(Request $request): JsonResponse
    {
        $files = $request->allFiles()['files'] ?? [];
        $collectionName = $request->get('collectionName') ?? 'default';

        $exceptions = [];

        $uploadedMedia = [];
        foreach ($files as $file) {
            try {
                $uploadedMedia[] = MediaHub::fileHandler()
                    ->withFile($file)
                    ->deleteOriginal()
                    ->withCollection($collectionName)
                    ->save();
            } catch (Exception $e) {
                $exceptions[] = $e;
                report($e);
            }
        }

        $uploadedMedia = collect($uploadedMedia);
        $coreResponse = [
            'media' => $uploadedMedia->map->formatForNova(),
            'hadExisting' => $uploadedMedia->where(fn ($m) => $m->wasExisting)->count() > 0,
            'success_count' => count($files) - count($exceptions),
        ];

        if (! empty($exceptions)) {
            return response()->json([
                ...$coreResponse,
                'errors' => Arr::map($exceptions, function ($e) {
                    $className = class_basename(get_class($e));

                    return "{$className}: {$e->getMessage()}";
                }),
            ], 400);
        }

        return response()->json($coreResponse, 200);
    }

    public function deleteMedia(Request $request): JsonResponse
    {
        $mediaId = $request->route('mediaId');
        if ($mediaId && $media = MediaHub::getQuery()->find($mediaId)) {
            /** @var Filesystem $fileSystem */
            $fileSystem = app()->make(Filesystem::class);
            $fileSystem->deleteFromMediaLibrary($media);
            $media->delete();
        }

        return response()->json('', 204);
    }

    public function moveMediaToCollection(Request $request): JsonResponse
    {
        $collectionName = $request->get('collection');
        $mediaIds = $request->get('mediaIds');
        if (! $collectionName) {
            return response()->json(['error' => 'Collection name required.'], 400);
        }
        if (count($mediaIds) === 0) {
            return response()->json(['error' => 'Media IDs required.'], 400);
        }

        $updatedCount = MediaHub::getQuery()
            ->whereIn('id', $mediaIds)
            ->update(['collection_name' => $collectionName]);

        return response()->json([
            'success_count' => $updatedCount,
        ], 200);
    }

    public function moveMediaItemToCollection(Request $request, $mediaId): JsonResponse
    {
        $collectionName = $request->get('collection');
        if (! $collectionName) {
            return response()->json(['error' => 'Collection name required.'], 400);
        }

        $media = MediaHub::getQuery()->findOrFail($mediaId);

        $media->collection_name = $collectionName;
        $media->save();

        return response()->json($media, 200);
    }

    public function updateMediaData(Request $request, $mediaId): JsonResponse
    {
        $media = MediaHub::getQuery()->findOrFail($mediaId);
        $locales = MediaHub::getLocales();
        $fieldKeys = array_keys(MediaHub::getDataFields());

        // No translations, we hardcoded frontend to always send data as 'en'
        if (empty($locales)) {
            $mediaData = $media->data;
            foreach ($fieldKeys as $key) {
                $mediaData[$key] = $request->input("{$key}.en") ?? null;
            }
            $media->data = $mediaData;
        } else {
            $mediaData = $media->data;
            foreach ($fieldKeys as $key) {
                $mediaData[$key] = $request->input($key) ?? null;
            }
            $media->data = $mediaData;
        }

        $media->save();

        return response()->json($media, 200);
    }
}
