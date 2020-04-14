<?php

namespace App\Admin\Controllers;

use App\Models\Profile;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Controllers\RoleController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ProfileController extends Controller
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
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Profile);

        $grid->id('ID')->sortable();
        //$grid->encoder('Encoder')->link();
        $grid->column('encoder')->display(function ($title) {
            return "<a href='profiles/$this->id'>$title</a>";
        });
        $grid->fallback_id('Fallback')->using(Profile::all()->pluck('encoder', 'id')->all());
        //$grid->created_at('Created at');
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
        $show = new Show(Profile::findOrFail($id));

        //$show->id('ID');
        $show->encoder('Encoder');
        $show->description('Description');
        $show->fallback_id('Fallback')->using(Profile::all()->pluck('encoder', 'id')->all());
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
        $form = new Form(new Profile);

        $form->text('encoder','Encoder');
        $form->text('description','Description');
        $profile = Profile::all()->pluck('encoder', 'id');

        $form->select('fallback_id', 'Fallback')->options($profile);
        $form->divider();

        $form->hasMany('options','Options', function (NestedForm $form) {
            $form->text('key')->placeholder('FFmpeg option key, e.g. -hwaccel');
            $form->text('value')->placeholder('FFmpeg option value, e.g. nvenc');
            $form->text('description')->placeholder('description');
        })->mode('table');

        $form->hasMany('additionalparameters','Additional Parameters', function (NestedForm $form) {
            $form->text('key')->placeholder('FFmpeg parameter key, e.g. -profile:v');
            $form->text('value')->placeholder('FFmpeg parameter value, e.g. main');
            $form->text('description')->placeholder('description');
        })->mode('table');
        return $form;
    }
}
