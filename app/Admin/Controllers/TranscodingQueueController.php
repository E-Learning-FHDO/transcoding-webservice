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

        if(!Admin::user()->isAdministrator())
        {
            $grid->model()->where('user_id', '=', Admin::user()->id);
        }

        $grid->disableCreateButton();

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableEdit();
        });

        $grid->id('ID')->sortable();
        $grid->user()->name()->display(function ($name){
            return "<a href='users/$this->id'>$name</a>";
        });
        $grid->mediakey('Mediakey');
        $grid->title('Title');
        $grid->file('File')->display(function ($file){
            return "<a href='transcodingqueue/$this->id'>$file</a>";
        });

        $grid->processed('Processed')->using(['0' => 'No', '1' => 'Yes', '2' => 'Processing']);
        $grid->created_at('Created at');
        $grid->converted_at('Converted at');
        $grid->downloaded_at('Downloaded at');
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
        $form->text('file','File');

        return $form;
    }
}
