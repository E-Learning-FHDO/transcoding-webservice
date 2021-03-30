<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\DownloadController;
use App\Models\Download;
use App\Models\Profile;
use App\Http\Controllers\Controller;
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
use App\Models\Video;

class DownloadQueueController extends Controller
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
        DownloadController::deleteById($id);
        return $this->form()->destroy($id);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Download());

        if(!Admin::user()->isAdministrator())
        {
            $grid->model()->where('user_id', '=', Admin::user()->id);
        }

        $grid->disableCreateButton();

        $grid->actions(function ($actions) {
            $actions->disableEdit();
        });

        $grid->id('ID')->sortable();

        $grid->user()->name()->display(function ($name){
            return "<a href='users/$this->id'>$name</a>";
        });

        $grid->mediakey('Mediakey');
        $grid->column('payload', 'URL')->display(function($payload) {
            return '<a target="_blank" href="' . $payload['source']['url'] . '">'. $payload['source']['url'] .'</a>';
        });

//        $grid->processed('Processed')->using(['0' => 'No', '1' => 'Yes', '2' => 'Processing']);

        $grid->column('processed', __('Processed'))
            ->using(Video::$status)
            ->label([
                '0' => 'default',
                '1' => 'success',
                '2' => 'warning',
                '3' => 'danger',
            ]);


        $grid->column('created_at', 'Created at')->display(function($created_at) {
		if ($created_at) {
			return Carbon::parse($created_at)->timezone(config('app.timezone'))->format(config('app.timestamp_display_format'));
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
        $show = new Show(Download::findOrFail($id));

        $show->panel()
            ->tools(function ($tools) {
                $tools->disableEdit();
            });;

        $show->id('ID');
        $show->field('payload', 'Mediakey')->as(function ($payload) {
            return print_r($payload['mediakey'], true);
        });

        $show->created_at('Created at');
        $show->updated_at('Updated at');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $roleModel = config('admin.database.roles_model');

        $form = new Form(new Download);

        $form->select('rid')->options('admin.permissions')->options($roleModel::all()->pluck('name', 'id'));
        $form->text('processed','Processed');

        return $form;
    }
}
