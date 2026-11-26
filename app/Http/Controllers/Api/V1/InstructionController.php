<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Instruction;
use App\Models\InstructionRating;
use App\Models\InstructionVersion;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Návody (krok 10): vyhledávání podle projektu, zařízení a tématu, verze,
 * zveřejnění oprávněnou osobou, hodnocení technikem. Technik vidí jen zveřejněnou verzi.
 */
class InstructionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'integer'],
            'device_type' => ['nullable', 'string', 'max:100'],
            'all' => ['nullable', 'boolean'],
        ]);
        $office = $this->isOffice($user);

        $q = Instruction::query()->with(['project:id,name', 'published:id,instruction_id,version,symptom,steps,note,published_at'])
            ->withCount(['versions as drafts_count' => fn ($v) => $v->where('status', 'draft')])
            ->orderBy('title');
        if (! $office || empty($data['all'])) {
            $q->where('is_active', true)->whereNotNull('published_version_id');
        }
        if (! $office) {
            $pids = $user->projects()->pluck('projects.id');
            $q->where(fn ($w) => $w->whereNull('project_id')->orWhereIn('project_id', $pids));
        }
        if (! empty($data['project_id'])) {
            $q->where(fn ($w) => $w->where('project_id', $data['project_id'])->orWhereNull('project_id'));
        }
        if (! empty($data['device_type'])) {
            $q->where('device_type', $data['device_type']);
        }
        if (! empty($data['q'])) {
            foreach (preg_split('/\s+/u', trim($data['q'])) as $word) {
                $like = '%'.$word.'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('device_type', 'like', $like)
                    ->orWhereHas('published', fn ($v) => $v->where('symptom', 'like', $like)->orWhere('steps', 'like', $like)));
            }
        }

        $list = $q->limit(300)->get();
        $stats = $this->stats($list->pluck('published_version_id')->filter()->all());

        return response()->json(['data' => $list->map(fn (Instruction $i) => [
            'id' => $i->id,
            'title' => $i->title,
            'project' => $i->project ? ['id' => $i->project->id, 'name' => $i->project->name] : null,
            'device_type' => $i->device_type,
            'tags' => $i->tags,
            'is_active' => $i->is_active,
            'version' => $i->published?->version,
            'version_id' => $i->published_version_id,
            'symptom' => $i->published?->symptom,
            // celý obsah zveřejněné verze – appka technika si ho drží offline
            'steps' => $i->published?->steps,
            'note' => $i->published?->note,
            'has_draft' => $i->drafts_count > 0,
            'rating' => $stats[$i->published_version_id] ?? null,
        ])]);
    }

    public function show(Request $request, Instruction $instruction): JsonResponse
    {
        $user = $request->user();
        $office = $this->isOffice($user);
        abort_unless($office || ($instruction->is_active && $instruction->published_version_id && $this->techMaySee($user, $instruction)), 404);

        $instruction->load(['project:id,name', 'published']);
        $versions = $office
            ? $instruction->versions()->with(['author:id,name', 'publisher:id,name'])->get()
            : collect([$instruction->published]);
        $stats = $this->stats($versions->pluck('id')->all());
        $comments = $office
            ? InstructionRating::with('user:id,name')->whereIn('instruction_version_id', $versions->pluck('id'))->whereNotNull('comment')->latest()->limit(30)->get()
            : collect();

        return response()->json(['data' => [
            'id' => $instruction->id,
            'title' => $instruction->title,
            'project' => $instruction->project ? ['id' => $instruction->project->id, 'name' => $instruction->project->name] : null,
            'project_id' => $instruction->project_id,
            'device_type' => $instruction->device_type,
            'tags' => $instruction->tags,
            'is_active' => $instruction->is_active,
            'published_version_id' => $instruction->published_version_id,
            'can_manage' => $this->canManage($user, $instruction->project_id),
            'versions' => $versions->map(fn (InstructionVersion $v) => $this->versionPayload($v) + ['rating' => $stats[$v->id] ?? null])->values(),
            'comments' => $comments->map(fn ($c) => ['user' => $c->user?->name, 'helpful' => $c->helpful, 'comment' => $c->comment, 'at' => $c->created_at?->toIso8601String(),
                'version' => $versions->firstWhere('id', $c->instruction_version_id)?->version]),
        ]]);
    }

    /** Nový návod + první verze (koncept, nebo rovnou zveřejnit) */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $meta = $this->validateMeta($request, true);
        abort_unless($this->canManage($user, $meta['project_id'] ?? null), 403);
        $content = $this->validateContent($request);

        $instruction = DB::transaction(function () use ($meta, $content, $user, $request) {
            $i = Instruction::create($meta + ['created_by' => $user->id]);
            $v = $i->versions()->create($content + ['version' => 1, 'status' => 'draft', 'created_by' => $user->id]);
            if ($request->boolean('publish')) {
                $this->doPublish($v, $user);
            }

            return $i;
        });

        return response()->json(['data' => ['id' => $instruction->id]], 201);
    }

    public function update(Request $request, Instruction $instruction): JsonResponse
    {
        abort_unless($this->canManage($request->user(), $instruction->project_id), 403);
        $meta = $this->validateMeta($request, false);
        if (array_key_exists('project_id', $meta)) {
            abort_unless($this->canManage($request->user(), $meta['project_id']), 403);
        }
        $instruction->update($meta);

        return response()->json(['ok' => true]);
    }

    /** Uložit koncept nové verze (existující koncept se přepíše) */
    public function saveDraft(Request $request, Instruction $instruction): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user, $instruction->project_id), 403);
        $content = $this->validateContent($request);

        $v = DB::transaction(function () use ($instruction, $content, $user, $request) {
            $draft = $instruction->versions()->where('status', 'draft')->first();
            if ($draft) {
                $draft->update($content);
            } else {
                $next = (int) $instruction->versions()->max('version') + 1;
                $draft = $instruction->versions()->create($content + ['version' => $next, 'status' => 'draft', 'created_by' => $user->id]);
            }
            if ($request->boolean('publish')) {
                $this->doPublish($draft, $user);
            }

            return $draft;
        });

        return response()->json(['data' => $this->versionPayload($v->fresh())]);
    }

    public function publish(Request $request, InstructionVersion $version): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user, $version->instruction->project_id), 403);
        abort_unless($version->status === 'draft', 422, 'Zveřejnit jde jen koncept.');
        DB::transaction(fn () => $this->doPublish($version, $user));

        return response()->json(['data' => $this->versionPayload($version->fresh())]);
    }

    public function discardDraft(Request $request, InstructionVersion $version): JsonResponse
    {
        abort_unless($this->canManage($request->user(), $version->instruction->project_id), 403);
        abort_unless($version->status === 'draft', 422, 'Smazat jde jen koncept.');
        $instruction = $version->instruction;
        $version->delete();
        if (! $instruction->versions()->exists()) {
            $instruction->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Technik ohodnotí návod (pomohl / nepomohl), případně k výjezdu */
    public function rate(Request $request, InstructionVersion $version): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'visit_uuid' => ['nullable', 'uuid'],
        ]);
        abort_unless($version->status === 'published' || $this->isOffice($user), 422, 'Hodnotit jde zveřejněnou verzi.');
        $visitId = ! empty($data['visit_uuid']) ? Visit::where('uuid', strtolower($data['visit_uuid']))->where('technician_id', $user->id)->value('id') : null;

        InstructionRating::updateOrCreate(
            ['instruction_version_id' => $version->id, 'user_id' => $user->id, 'visit_id' => $visitId],
            ['helpful' => $data['helpful'], 'comment' => $data['comment'] ?? null],
        );

        return response()->json(['data' => $this->stats([$version->id])[$version->id] ?? null]);
    }

    // ----------------------------------------------------------------

    private function doPublish(InstructionVersion $v, User $user): void
    {
        InstructionVersion::where('instruction_id', $v->instruction_id)->where('status', 'published')->update(['status' => 'archived']);
        $v->update(['status' => 'published', 'published_by' => $user->id, 'published_at' => now()]);
        Instruction::whereKey($v->instruction_id)->update(['published_version_id' => $v->id]);
    }

    private function isOffice(User $u): bool
    {
        return $u->isAdmin() || $u->hasRole(Role::Dispecer) || $u->canReadAllProjects();
    }

    /** Oprávněná osoba: admin, nebo dispečer projektu (návod bez projektu: kterýkoli dispečer) */
    private function canManage(User $u, ?int $projectId): bool
    {
        if ($u->isAdmin()) {
            return true;
        }
        if (! $u->hasRole(Role::Dispecer)) {
            return false;
        }

        return $projectId === null || in_array($projectId, $u->dispatcherProjectIds(), true);
    }

    private function techMaySee(User $u, Instruction $i): bool
    {
        return $i->project_id === null || $u->projects()->where('projects.id', $i->project_id)->exists();
    }

    /** @return array<int, array{helpful: int, total: int, used: int}> */
    private function stats(array $versionIds): array
    {
        if (! $versionIds) {
            return [];
        }
        $r = InstructionRating::selectRaw('instruction_version_id, count(*) as total, sum(case when helpful then 1 else 0 end) as helpful')
            ->whereIn('instruction_version_id', $versionIds)->groupBy('instruction_version_id')->get()->keyBy('instruction_version_id');
        $u = Visit::selectRaw('instruction_version_id, count(*) as used')->whereIn('instruction_version_id', $versionIds)
            ->groupBy('instruction_version_id')->pluck('used', 'instruction_version_id');
        $out = [];
        foreach ($versionIds as $id) {
            $out[$id] = ['helpful' => (int) ($r[$id]->helpful ?? 0), 'total' => (int) ($r[$id]->total ?? 0), 'used' => (int) ($u[$id] ?? 0)];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function validateMeta(Request $request, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$req, 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'device_type' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateContent(Request $request): array
    {
        $data = $request->validate([
            'symptom' => ['nullable', 'string', 'max:2000'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.text' => ['required', 'string', 'max:2000'],
            'steps.*.outcomes' => ['nullable', 'array', 'max:10'],
            'steps.*.outcomes.*.if' => ['required', 'string', 'max:255'],
            'steps.*.outcomes.*.then' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['steps'] = array_values(array_map(fn ($s) => [
            'text' => trim($s['text']),
            'outcomes' => array_values(array_map(fn ($o) => ['if' => trim($o['if']), 'then' => trim($o['then'])], $s['outcomes'] ?? [])),
        ], $data['steps']));

        return $data;
    }

    /** @return array<string, mixed> */
    private function versionPayload(InstructionVersion $v): array
    {
        return [
            'id' => $v->id,
            'version' => $v->version,
            'status' => $v->status,
            'symptom' => $v->symptom,
            'steps' => $v->steps,
            'note' => $v->note,
            'change_note' => $v->change_note,
            'author' => $v->relationLoaded('author') ? $v->author?->name : null,
            'publisher' => $v->relationLoaded('publisher') ? $v->publisher?->name : null,
            'published_at' => $v->published_at?->toIso8601String(),
            'updated_at' => $v->updated_at?->toIso8601String(),
        ];
    }
}
