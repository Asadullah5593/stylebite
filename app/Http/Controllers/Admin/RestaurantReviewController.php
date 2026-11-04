<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestaurantReviewController extends Controller
{
    public function index(Request $request): View
    {
        // App reviews mostly live on food posts; mirror any missing rows first.
        RestaurantReview::syncMissingFromFoodPosts();

        $reviews = RestaurantReview::query()
            ->with([
                'user:id,username,full_name',
                'post:id,caption,status',
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('restaurant', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.reviews.IndexPage', compact('reviews'));
    }

    public function destroy(RestaurantReview $review): RedirectResponse
    {
        $label = $review->restaurant;

        if ($review->post_id) {
            $post = $review->post;
            if ($post && (int) $post->user_id === (int) $review->user_id) {
                $post->forceFill([
                    'food_rating' => null,
                    'service_rating' => null,
                    'staff_rating' => null,
                    'ambience_rating' => null,
                ])->save();
            }
        }

        $review->delete();

        return back()->with('status', "Review for {$label} was removed.");
    }
}
