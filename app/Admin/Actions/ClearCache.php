<?php

namespace App\Admin\Actions;

use Encore\Admin\Actions\Action;
use Illuminate\Http\Request;

class ClearCache extends Action
{
    protected $selector = '.clear-cache';

    public function handle(Request $request)
    {
        return $this->response()->success('Cleanup completed')->refresh();
    }

    public function dialog()
    {
        $this->confirm('Confirm cache clearing');
    }

    public function html()
    {
        return <<<HTML
<li>
    <a class="clear-cache" href="#">
      <i class="fa fa-trash"></i>&nbsp;
      <span>Clear cache</span>
    </a>
</li>
HTML;
    }
}