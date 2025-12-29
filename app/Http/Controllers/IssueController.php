<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Models\Article;
use App\Models\Issue;
use App\Services\FileUploadService;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssueController extends Controller
{
    protected $imageService;

    protected $fileUploadService;

    public function __construct(ImageUploadService $imageService, FileUploadService $fileUploadService)
    {
        $this->imageService = $imageService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $issues = Issue::orderBy('issue_number', 'desc')->paginate(10);
        if ($request->status) {
            $issues = $issues->where('status', $request->status);
        }
        return response()->json($issues);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueRequest $request)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        // Assign current user
        $data['user_id'] = $user->id;
        // $data['issue_number']=

        // Handle Cover Image Upload
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->imageService->upload(
                $request->file('cover_image'),
                'issues' // Storage path: storage/app/public/issues
            );
        }

        // Handle Cover Image Alt Upload (Treating as second image per user request)
        if ($request->hasFile('cover_image_alt')) {
            $data['cover_image_alt'] = $this->imageService->upload(
                $request->file('cover_image_alt'),
                'issues'
            );
        }

        // Handle PDF Upload
        if ($request->hasFile('pdf_file')) {
            $data['pdf_file'] = $this->fileUploadService->uploadPdf(
                $request->file('pdf_file'),
                'issues_pdf' // Storage path: storage/app/public/issues_pdf
            );
        }

        if (! isset($data['issue_number']) || $data['issue_number'] === null) {
            $data['issue_number'] = (Issue::max('issue_number') ?? 0) + 1;
        }

        $data['status'] = $data['status'] ?? env('ISSUE_STATUS_DRAFT', 'draft');
        $data['slug'] = $this->makeUniqueIssueSlug($data['title']);

        $issue = DB::transaction(function () use ($data, $user) {
            $issue = Issue::create($data);

            $defaultArticles = [
                ['title' => 'افتتــاحية الــعدد', 'className' => 'arc-opening'],
                ['title' => 'قــاموس المصطلحـات', 'className' => 'arc-glossary'],
                ['title' => 'شخـصيــات صوفـيــة', 'className' => 'arc-profiles'],
                ['title' => 'إحصــائيات وتحليلات', 'className' => 'arc-stats'],
                ['title' => 'الصوفية حول العالم', 'className' => 'arc-news'],
                ['title' => 'شبهــات تحت المجهر', 'className' => 'arc-refutations'],
                ['title' => 'خـزّانــة الوثــائق', 'className' => 'arc-archive'],
                ['title' => 'مـحـطــات تـاريخية', 'className' => 'arc-history'],
                ['title' => 'عـصــارة الـكـتــب', 'className' => 'arc-library'],
            ];

            $issue->articles()->createMany(array_map(function (array $article) use ($user, $issue) {
                return [
                    'user_id' => $user->id,
                    'title' => $article['title'],
                    'slug' => $this->makeArabicSlug($article['title']),
                    'className' => $article['className'],
                    'status' => $issue->status,
                ];
            }, $defaultArticles));

            return $issue;
        });

        return response()->json([
            'message' => 'Issue created successfully',
            'issue' => $issue,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $issue = Issue::with('articles')->findOrFail($id);

        // Increment views
        $issue->increment('views_count');

        return response()->json($issue);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIssueRequest $request, Issue $issue)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        // Handle Cover Image Upload
        if ($request->hasFile('cover_image')) {
            // Delete old image
            if ($issue->cover_image) {
                $this->imageService->delete($issue->getRawOriginal('cover_image'));
            }

            $data['cover_image'] = $this->imageService->upload(
                $request->file('cover_image'),
                'issues' // Storage path: storage/app/public/issues
            );
        }

        // Handle Cover Image Alt Upload
        if ($request->hasFile('cover_image_alt')) {
            // Delete old image
            if ($issue->cover_image_alt) {
                $this->imageService->delete($issue->getRawOriginal('cover_image_alt'));
            }

            $data['cover_image_alt'] = $this->imageService->upload(
                $request->file('cover_image_alt'),
                'issues'
            );
        }

        // Handle PDF Upload
        if ($request->hasFile('pdf_file')) {
            // Delete old PDF
            if ($issue->pdf_file) {
                $this->fileUploadService->delete($issue->getRawOriginal('pdf_file'));
            }

            $data['pdf_file'] = $this->fileUploadService->uploadPdf(
                $request->file('pdf_file'),
                'issues_pdf'
            );
        }

        $issue->update($data);

        return response()->json([
            'message' => 'Issue updated successfully',
            'issue' => $issue,
        ]);
    }

    public function publish(Request $request, Issue $issue)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $message = null;

        if ($issue->status == env('ARTICLE_STATUS_ARCHIVED', 'archived')) {
            $publishedStatus = env('ISSUE_STATUS_PUBLISHED', 'published');
            $articlePublishedStatus = env('ARTICLE_STATUS_PUBLISHED', 'published');
            $publishedAt = now();

            DB::transaction(function () use ($issue, $publishedStatus, $articlePublishedStatus, $publishedAt) {
                $issue->update([
                    'status' => $publishedStatus,
                    'published_at' => $issue->published_at ?? $publishedAt,
                ]);

                $issue->articles()->update([
                    'status' => $articlePublishedStatus,
                    'published_at' => DB::raw('COALESCE(published_at, NOW())'),
                ]);
            });
            $message = 'Issue published successfully';
        } else {
            $archivedStatus = env('ARTICLE_STATUS_ARCHIVED', 'archived');
            $articleArchivedStatus = env('ARTICLE_STATUS_ARCHIVED', 'archived');
            // $publishedAt = now();

            DB::transaction(function () use ($issue, $archivedStatus, $articleArchivedStatus) {
                $issue->update([
                    'status' => $archivedStatus,
                    'published_at' => null,
                ]);

                $issue->articles()->update(values: [
                    'status' =>  $articleArchivedStatus = env('ARTICLE_STATUS_ARCHIVED', 'archived'),
                    'published_at' => null,
                ]);
            });
            $message = 'Issue archived successfully';
        }

        return response()->json([
            'message' => $message,
            'issue' => $issue->load('articles'),
        ]);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $year = (int) $request->query('year', now()->year);

        $issuesCount = Issue::count();
        $totalViews = Article::sum('views_count');

        $issuesByMonth = Issue::query()
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('count', 'month')
            ->all();

        $viewsByMonth = Article::query()
            ->join('issues', 'issues.id', '=', 'articles.issue_id')
            ->selectRaw('MONTH(issues.created_at) as month, SUM(articles.views_count) as views')
            ->whereYear('issues.created_at', $year)
            ->groupByRaw('MONTH(issues.created_at)')
            ->pluck('views', 'month')
            ->all();

        $months = range(1, 12);

        $issuesSeries = array_map(fn(int $month) => [
            'month' => $month,
            'count' => (int) ($issuesByMonth[$month] ?? 0),
        ], $months);

        $viewsSeries = array_map(fn(int $month) => [
            'month' => $month,
            'views' => (int) ($viewsByMonth[$month] ?? 0),
        ], $months);

        return response()->json([
            'year' => $year,
            'issues_count' => $issuesCount,
            'total_views' => (int) $totalViews,
            'issues_by_month' => $issuesSeries,
            'views_by_month' => $viewsSeries,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Issue $issue)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete cover image
        if ($issue->cover_image) {
            $this->imageService->delete($issue->getRawOriginal('cover_image'));
        }

        // Delete cover image alt
        if ($issue->cover_image_alt) {
            $this->imageService->delete($issue->getRawOriginal('cover_image_alt'));
        }

        // Delete PDF file
        if ($issue->pdf_file) {
            $this->fileUploadService->delete($issue->getRawOriginal('pdf_file'));
        }

        $issue->delete();

        return response()->json([
            'message' => 'Issue deleted successfully',
        ]);
    }

    private function makeArabicSlug(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', '-', $value) ?? '';
        $value = preg_replace('/[^\p{Arabic}\p{L}\p{N}\-]+/u', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function makeUniqueIssueSlug(string $title): string
    {
        $baseSlug = $this->makeArabicSlug($title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'issue';

        $slug = $baseSlug;
        $suffix = 2;

        while (Issue::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
