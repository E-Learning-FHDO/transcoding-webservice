<?php

namespace App\Admin\Controllers;

use App\Models\Administrator;
use App\Models\Profile;
use App\Models\User;
use App\Models\Worker;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserController extends AdminController
{
    /**
     * {@inheritdoc}
     */
    protected function title()
    {
        return trans('admin.administrator');
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $userModel = config('admin.database.users_model');

        $grid = new Grid(new $userModel());

        $grid->column('id', 'ID')->sortable();
        $grid->column('name', trans('admin.name'));
        $grid->column('roles', trans('admin.roles'))->pluck('name')->label();
        $grid->column('created_at', 'Created at')->display(function($created_at) {
                if ($created_at) {
                    return Carbon::parse($created_at)->format(config('app.timestamp_display_format'));
                }
                return '';
        });

        $grid->column('updated_at', 'Updated at')->display(function($updated_at) {
                if ($updated_at) {
                    return Carbon::parse($updated_at)->format(config('app.timestamp_display_format'));
                }
                return '';
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            if ($actions->getKey() == 1) {
                $actions->disableDelete();
            }
        });

        $grid->tools(function (Grid\Tools $tools) {
            $tools->batch(function (Grid\Tools\BatchActions $actions) {
                $actions->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        $userModel = config('admin.database.users_model');

        $show = new Show($userModel::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('email', trans('admin.email'));
        $show->field('name', trans('admin.name'));
        $show->field('roles', trans('admin.roles'))->as(function ($roles) {
            return $roles->pluck('name');
        })->label();
        $show->field('permissions', trans('admin.permissions'))->as(function ($permission) {
            return $permission->pluck('name');
        })->label();

        $show->field('api_token');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    public function form()
    {
        $userModel = config('admin.database.users_model');
        $permissionModel = config('admin.database.permissions_model');
        $roleModel = config('admin.database.roles_model');

        $form = new Form(new $userModel());
        $string =
            <<<EOT

<a href="#" id="testbtn""><span class="btn-flat">Test Connection</span></a><script>

$('#testbtn').on('click', function() {

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Authorization': 'Bearer ' + $('#api_token').val()
        }
    });
    
    $.ajax({
        method: 'post',
        dataType: "json",
        url: '/api/v1/testurl',
        data: {
            url: $('#url').val(),
            api_token: $('#api_token').val()
        },
        beforeSend: function() { $('#testbtn').text('Testing Connection...'); },
        complete: function() { $('#testbtn').text('Test Connection'); },
        success: function (data) {
            swal({
              title: "Success",
                html: "Connection to " +  $('#url').val() + " was successful!" + "<br/>" +
                 "Endpoint "  + data.name + ", version: " + data.version,
                icon: "success",
                type: "success",
                button: "OK",
            });
        },
        error: function (data) {
              swal({
              title: "Failure",
                html: "Connection to " +  $('#url').val() + " failed!" + "<br/>" + 
                "Error: " + data.responseJSON.message,
                icon: "error",
                type: "error",
                button: "OK",
            });
        }
    });
});

</script>
EOT;
        $userTable = config('admin.database.users_table');
        $connection = config('admin.database.connection');

        $form->display('id', 'ID');

        $form->text('email', trans('admin.email'))->rules('required');
        $form->text('name', trans('admin.name'))->rules('required');
        $form->image('avatar', trans('admin.avatar'));
        $form->password('password', trans('admin.password'))->rules('required|confirmed');
        $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required')
            ->default(function ($form) {
                return $form->model()->password;
            });

        $form->ignore(['password_confirmation']);

        $token = Str::random(32);
        $form->text('api_token')->default($token)->rules('required');
        $form->url('url')->rules('required')->append($string);

        $profile = Profile::all()->pluck('encoder', 'id');

        $form->select('profile_id')->options($profile)->rules('required');

        $form->multipleSelect('roles', trans('admin.roles'))->options($roleModel::all()->pluck('name', 'id'));
        $form->multipleSelect('permissions', trans('admin.permissions'))->options($permissionModel::all()->pluck('name', 'id'));

        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

        $form->radio('active')->options([1 => 'yes', 0 => 'no']);
        $form->datetime('active_from');
        $form->datetime('active_to');

        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = bcrypt($form->password);
            }
        });

        return $form;
    }
}
