<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(trim((string) config('communications.ui.name_prefix', 'communications.'), '.').'.templates.page');
});
