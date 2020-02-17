<?php

namespace App\Admin\Controllers;

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
        $filenames = DB::table('videos')->select('file')->whereIn('id', explode(',', $id))->pluck('file')->toArray();

        Storage::disk('converted')->delete($filenames);

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

        if(!Admin::user()->isAdministrator())
        {
            $grid->model()->where('uid', '=', Admin::user()->id);
        }

        $grid->disableCreateButton();

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableEdit();
        });

        $grid->id('ID')->sortable();
        $grid->user()->name();
        $grid->file('File');
        $grid->processed('Processed')->using(['0' => 'No', '1' => 'Yes']);
        $grid->created_at('Created at');
        //$grid->updated_at('Updated at');
        $grid->converted_at('Converted at');

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
        //$show->created_at('Created at');
        //$show->updated_at('Updated at');

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
        $form->text('file','File');
        //$form->display('Created at');
        //$form->display('Updated at');

        return $form;
    }
}
