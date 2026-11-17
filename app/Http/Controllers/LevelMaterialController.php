<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LevelMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Level books/PDFs: Admin uploads and deletes; Admin, assigned Teachers and enrolled Students view/download. */
class LevelMaterialController extends Controller
{
    /** Materials grouped by the Levels the signed-in user can access. */
    public function index(Request $request)
    {
        $materials = LevelMaterial::query()->accessibleTo($request->user())
            ->with('course:id,name,level')->orderBy('title')->get();
        $levels = $materials->groupBy('course_id')->map(fn ($items) => ['course' => $items->first()->course, 'materials' => $items])
            ->sortBy(fn ($group) => $group['course']->name.' '.$group['course']->level)->values();

        return view('materials.index', compact('levels'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'file' => ['required', 'file', 'max:'.LevelMaterial::MAX_KB,
                'mimes:'.implode(',', LevelMaterial::EXTENSIONS), 'extensions:'.implode(',', LevelMaterial::EXTENSIONS)],
        ]);
        $file = $request->file('file');
        $path = $file->store('level-materials/'.$course->id, 'local');
        abort_unless($path, 500, 'File could not be saved.');

        $course->materials()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('courses.show', $course)->with('success', 'Material uploaded.');
    }

    public function destroy(LevelMaterial $material)
    {
        $course = $material->course_id;
        Storage::disk('local')->delete($material->path);
        $material->delete();

        return redirect()->route('courses.show', $course)->with('success', 'Material deleted.');
    }

    public function view(Request $request, LevelMaterial $material)
    {
        $this->authorizeAccess($request, $material);
        if (! $material->canPreview()) {
            return $this->download($request, $material);
        }

        return Storage::disk('local')->response($material->path, $material->original_name,
            ['Content-Type' => $material->mime_type, 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function download(Request $request, LevelMaterial $material)
    {
        $this->authorizeAccess($request, $material);

        return Storage::disk('local')->download($material->path, $material->original_name,
            ['Content-Type' => $material->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeAccess(Request $request, LevelMaterial $material): void
    {
        abort_unless($material->isAccessibleTo($request->user()), 403);
        abort_unless(Storage::disk('local')->exists($material->path), 404);
    }
}
