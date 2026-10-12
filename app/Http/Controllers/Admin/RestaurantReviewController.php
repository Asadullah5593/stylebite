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
        $review->delete();

        return back()->with('status', "Review for {$label} was removed.");
    }
}
