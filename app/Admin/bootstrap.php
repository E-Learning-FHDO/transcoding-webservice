<?php

/**
 * Laravel-admin - admin builder based on Laravel.
 * @author z-song <https://github.com/z-song>
 *
 * Bootstraper for Admin.
 *
 * Here you can remove builtin form field:
 * Encore\Admin\Form::forget(['map', 'editor']);
 *
 * Or extend custom form field:
 * Encore\Admin\Form::extend('php', PHPEditor::class);
 *
 * Or require js and css assets:
 * Admin::css('/packages/prettydocs/css/styles.css');
 * Admin::js('/packages/prettydocs/js/main.js');
 *
 */
use Encore\Admin\Facades\Admin;
use App\Admin\Extensions\Nav;
use App\Admin\Actions;

Encore\Admin\Form::forget(['map', 'editor']);

Admin::navbar(function (\Encore\Admin\Widgets\Navbar $navbar) {
    $navbar->right(new Nav\AutoRefresh())
        ->right(new Actions\ClearCache())
        ->right(new Actions\Feedback());

    $navbar->left(view('admin.search-bar'));
});
