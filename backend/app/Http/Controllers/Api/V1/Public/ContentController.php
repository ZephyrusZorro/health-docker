<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Audit\Announcement;
use App\Models\Audit\ContentPage;

/**
 * Public CMS feed — pages, announcements, health tips (SRS §4.11).
 */
class ContentController extends ApiController
{
    public function pages()
    {
        return $this->ok(
            ContentPage::published()
                ->orderBy('title')
                ->get(['id', 'slug', 'title', 'meta_description', 'published_at'])
        );
    }

    public function page(string $slug)
    {
        $page = ContentPage::where('slug', $slug)->published()->firstOrFail();
        return $this->ok($page);
    }

    public function announcements()
    {
        return $this->ok(
            Announcement::published()
                ->orderByDesc('published_at')
                ->limit(10)
                ->get(['id', 'title', 'summary', 'content', 'image_url', 'type', 'published_at'])
        );
    }
}
