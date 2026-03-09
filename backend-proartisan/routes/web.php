<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::redirect('/admin', '/admin/dashboard')->name('admin.index');
Route::inertia('/admin/dashboard', 'admin/dashboard')->name('admin.dashboard');
Route::inertia('/admin/kyc', 'admin/kyc')->name('admin.kyc');
Route::inertia('/admin/litiges', 'admin/litiges')->name('admin.litiges');
Route::inertia('/admin/users', 'admin/users')->name('admin.users');
Route::inertia('/admin/transactions', 'admin/transactions')->name('admin.transactions');
Route::inertia('/admin/settings', 'admin/settings')->name('admin.settings');
