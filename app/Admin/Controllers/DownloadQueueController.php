<?php

namespace App\Admin\Controllers;

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
        $filenames = DB::table('downloads')->select('payload')->whereIn('id', explode(',', $id))->pluck('payload')->toArray();

        $files_to_delete = array();
        foreach ($filenames as $filename)
        {
            $filename = json_decode($filename);
            $files_to_delete[] = $filename->source->mediakey;
        }

        Storage::disk('uploaded')->delete($files_to_delete);

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
            $grid->model()->where('uid', '=', Admin::user()->id);
        }

        $grid->disableCreateButton();

        $grid->actions(function ($actions) {
            $actions->disableEdit();
        });

        $grid->id('ID')->sortable();

        $grid->user()->name();

        $grid->column('payload', 'URL')->display(function($payload) {
           return $payload['source']['url'];
        });

        $grid->processed('Processed')->using(['0' => 'No', '1' => 'Yes']);
        $grid->created_at('Created at');
        //$grid->updated_at('Updated at');

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
            return print_r($payload['source']['mediakey'], true);
        });


        /*
        $show->processed('Processed')->using(['0' => 'No', '1' => 'Yes']);
        $show->field('payload', 'Payload')->as(function ($payload) {
            return print_r(($payload), true);
        });
*/

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

        //$form->display('ID');
        $form->select('rid')->options('admin.permissions')->options($roleModel::all()->pluck('name', 'id'));
        $form->text('processed','Processed');
        //$form->display('Created at');
        //$form->display('Updated at');

        return $form;
    }
}
