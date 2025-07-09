<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('categories/{slug}', [CategoryController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::get('user/author', [UserController::class, 'author']);
Route::get('home/photo', [HomeController::class, 'getPhoto']);

Route::post('login', [UserController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [UserController::class, 'logout']);
Route::middleware('auth:sanctum')->get('user', [UserController::class, 'index']);
Route::middleware('auth:sanctum')->post('user/photo', [UserController::class, 'setPhoto']);
Route::middleware('auth:sanctum')->put('user', [UserCOntroller::class, 'update']);

Route::middleware('auth:sanctum')->post('categories', [CategoryController::class, 'store']);
Route::middleware('auth:sanctum')->put('categories/{id}', [CategoryController::class, 'update']);
Route::middleware('auth:sanctum')->delete('categories/{id}', [CategoryController::class, 'destroy']);
Route::middleware('auth:sanctum')->post('album/{id}', [CategoryController::class, 'addPhoto']);
Route::middleware('auth:sanctum')->delete('album/{id}', [CategoryController::class, 'deletePhoto']);
Route::middleware('auth:sanctum')->post('dashboard/photo', [DashboardController::class, 'setphoto']);
Route::middleware('auth:sanctum')->get('dashboard', [DashboardController::class, 'dashboard']);