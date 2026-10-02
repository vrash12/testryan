<?php

namespace App\View\Composers;

use App\Support\JobContent;
use Illuminate\View\View;

class JobContentComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();
        $job = $data['job'] ?? null;
        $view->with('safeDescription', JobContent::render($job?->job_description))
            ->with('safeTerms', JobContent::render($job?->terms));
    }
}
