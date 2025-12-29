<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Issue;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ArticleController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Article::with(['issue']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('author')) {
            $query->where('author_name', 'like', '%'.$request->author.'%');
        }

        if ($request->filled('date')) {
            $query->whereDate('gregorian_date', $request->date);
        }

        // Sort by newest
        $query->latest();

        $articles = $query->paginate(15);

        return response()->json($articles);
    }

    public function byIssue(Request $request, Issue $issue)
    {
        $query = $issue->articles()->with(['issue']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('author')) {
            $query->where('author_name', 'like', '%'.$request->author.'%');
        }

        if ($request->filled('date')) {
            $query->whereDate('gregorian_date', $request->date);
        }

        $query->latest();

        $articles = $query->get();

        return response()
            ->json($articles)
            ->header('X-Total-Count', (string) $articles->count());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArticleRequest $request, $section_id = null, $issue_id = null)
    {
        $data = $request->validated();

        // If IDs are passed via route, ensure they are used (though merge in request handles validation)
        if ($issue_id) {
            $data['issue_id'] = $issue_id;
        }

        // Assign current user
        $data['user_id'] = $request->user()->id;

        // Handle Image Upload
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $this->imageService->upload(
                $request->file('featured_image'),
                'articles' // Storage path: storage/app/public/articles
            );
        }

        $article = Article::create($data);

        return response()->json([
            'message' => 'Article created successfully',
            'article' => $article,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $article = Article::with(['issue'])->findOrFail($id);

        // Get visited articles from cookie
        $visitedArticles = json_decode(Cookie::get('visited_articles', '[]'), true);

        if (! in_array($article->id, $visitedArticles)) {
            // Increment views
            $article->increment('views_count');

            // Add article ID to visited list
            $visitedArticles[] = $article->id;

            // Update cookie (valid for 30 days)
            Cookie::queue('visited_articles', json_encode($visitedArticles), 60 * 24 * 30);
        }

        return response()->json([
            'article' => $article,
            // 'visited_articles' => $visitedArticles // Removed debug info for cleaner response
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArticleRequest $request, Article $article)
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Handle Image Upload
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($article->featured_image) {
                $this->imageService->delete($article->getRawOriginal('featured_image'));
            }

            $data['featured_image'] = $this->imageService->upload(
                $request->file('featured_image'),
                'articles' // Storage path: storage/app/public/articles
            );
        }

        $article->update($data);

        return response()->json([
            'message' => 'Article updated successfully',
            'article' => $article,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Article $article)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isAuthor()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $article->delete(); // Soft delete because of SoftDeletes trait

        return response()->json([
            'message' => 'Article deleted successfully',
        ]);
    }
}
