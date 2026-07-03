<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseNotesController extends Controller
{
    public function index(): Response
    {
        $files = collect(File::glob(base_path('docs/release-notes-*.md')));

        $notes = $files
            ->map(function (string $path) {
                preg_match(
                    '/release-notes-(\d{4}-\d{2}-\d{2})\.md$/',
                    basename($path),
                    $matches,
                );

                if (! isset($matches[1])) {
                    return null;
                }

                return [
                    'date' => $matches[1],
                    'html' => Str::markdown(File::get($path)),
                ];
            })
            ->filter()
            ->sortByDesc('date')
            ->values()
            ->all();

        return Inertia::render('release-notes/index', [
            'notes' => $notes,
        ]);
    }
}
