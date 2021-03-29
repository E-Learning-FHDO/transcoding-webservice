<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->title('Dashboard')
            ->description(' ')
            ->row('')
            ->row(function (Row $row) {

                $row->column(4, function (Column $column) {
                    $column->append(TranscodingDashboard::environment());
                });
/*
                $row->column(4, function (Column $column) {
                    $column->append(TranscodingDashboard::extensions());
                });*/

                $row->column(4, function (Column $column) {
                    $column->append(TranscodingDashboard::dependencies());
                });

                /* $row->column(4, function (Column $column) {
                    $column->append(TranscodingDashboard::workers());
                });*/
            });
    }
}
