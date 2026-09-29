<?php

namespace App\Http\Controllers;

use App\Services\FeedService;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function __invoke(FeedService $feedService): Response
    {
        return response($feedService->rss(), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
