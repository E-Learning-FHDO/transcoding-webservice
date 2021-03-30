<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\VideoController;
use App\Models\Profile;
use App\Http\Controllers\Controller;
use App\Models\Video;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Controllers\RoleController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TranscodingQueueController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('Index')
            ->description('description')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('Edit')
            ->description('description')
            ->body($this->form()->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('Create')
            ->description('description')
            ->body($this->form());
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        VideoController::deleteById($id);
        return $this->form()->destroy($id);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Video);

        if (!Admin::user()->isAdministrator()) {
            $grid->model()->where('user_id', '=', Admin::user()->id);
        }

        $grid->disableCreateButton();

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableEdit();
        });

        $grid->filter(function ($filter) {

            $filter->disableIdFilter();
            $filter->equal('mediakey', 'mediakey');
            $filter->equal('title', 'title');
            $filter->equal('host', 'host');

        });


        $grid->id('ID')->sortable();
        $grid->user()->name()->display(function ($name) {
            return "<a href='users/$this->id'>$name</a>";
        });
        $grid->mediakey('Mediakey');
        $grid->title('Title');
        $grid->file('File')->display(function ($file) {
            return "<a href='transcodingqueue/$this->id'>$file</a>";
        });

        $grid->column('processed', __('Processed'))
            ->using(Video::$status)
            ->label([
                '0' => 'default',
                '1' => 'success',
                '2' => 'warning',
                '3' => 'danger',
            ]);

        $grid->percentage('Percentage');
        $grid->worker('Worker');
        $grid->column('created_at', 'Created at')->display(function ($created_at) {
            if ($created_at) {
                return Carbon::parse($created_at)->format(config('app.timestamp_display_format'));
            }
            return '';
        });

        $grid->column('converted_at', 'Converted at')->display(function ($converted_at) {
            if ($converted_at) {
                return Carbon::parse($converted_at)->format(config('app.timestamp_display_format'));
            }
            return '';
        });

        $grid->column('failed_at', 'Failed at')->display(function ($failed_at) {
            if ($failed_at) {
                return Carbon::parse($failed_at)->format(config('app.timestamp_display_format'));
            }
            return '';
        });

        $grid->column('downloaded_at', 'Downloaded at')->display(function ($downloaded_at) {
            if ($downloaded_at) {
                return Carbon::parse($downloaded_at)->format(config('app.timestamp_display_format'));
            }
            return '';
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Video::findOrFail($id));
        $show->panel()
            ->tools(function ($tools) {
                $tools->disableEdit();
            });;


        $show->id('ID');
        $show->file('File');
        $show->target()->as(function ($payload) {
            return print_r($payload, true);
        });

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Video);

        //$form->display('ID');
        $form->text('file', 'File');

        return $form;
    }
}
