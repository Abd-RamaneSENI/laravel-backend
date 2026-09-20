<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class MemberController extends Controller
{
    public function index()
    {
        return response()->json([
            'members' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ]);
    }
}
