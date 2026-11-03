<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('admin:change-developer-password')]
#[Description('Change the password of a developer admin account')]
class ChangeDeveloperAdminPassword extends Command
{
    protected $signature = 'admin:change-developer-password';

    protected $description = 'Change the password of a developer admin account';

    public function handle(): int
    {
        $email = $this->ask('Developer email');

        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($emailValidator->fails()) {
            foreach ($emailValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()
            ->where('email', $email)
            ->whereHas('roles', function ($query): void {
                $query->where('code', Role::DEVELOPER_CODE);
            })
            ->first();

        if ($admin === null) {
            $this->error('Developer admin not found.');

            return self::FAILURE;
        }

        $password = $this->secret('New password');

        if ($password !== $this->secret('Confirm new password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin->password = $password;
        $admin->save();

        $this->info('Developer admin password changed.');

        return self::SUCCESS;
    }
}
