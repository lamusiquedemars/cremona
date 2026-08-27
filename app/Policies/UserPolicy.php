<?php
namespace App\Policies;
use App\Models\User;
class UserPolicy { public function viewAny(User $user): bool { return $user->is_platform_admin; } public function view(User $user, User $record): bool { return $user->is_platform_admin; } public function create(User $user): bool { return $user->is_platform_admin; } public function update(User $user, User $record): bool { return $user->is_platform_admin; } }
