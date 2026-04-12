<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Jobs;

use SpAnjaan\BlogPortal\Models\Visitor;
use Winter\Blog\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $postId,
        public int $visitorId,
        public bool $isUnique
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $post = Post::find($this->postId);
        if (empty($post)) {
            return;
        }

        $visitor = Visitor::find($this->visitorId);
        if (empty($visitor)) {
            return;
        }

        if ($this->isUnique) {
            $post->spanjaan_blogportal_unique_views = is_numeric($post->spanjaan_blogportal_unique_views)
                ? $post->spanjaan_blogportal_unique_views + 1
                : 1;
        }

        $post->spanjaan_blogportal_views = is_numeric($post->spanjaan_blogportal_views)
            ? $post->spanjaan_blogportal_views + 1
            : 1;

        $post->save();
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('BlogPortal: Failed to record view', [
            'post_id' => $this->postId,
            'visitor_id' => $this->visitorId,
            'error' => $exception->getMessage(),
        ]);
    }
}