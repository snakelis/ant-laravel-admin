<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'foundation:create-admin';

    protected $description = '交互式创建新的管理员账号，不提升或覆盖已有账号';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('请在交互式终端运行本命令，密码通过隐藏输入提供。');

            return self::FAILURE;
        }

        if (! Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->exists()) {
            $this->error('请先运行基础数据种子，再创建管理员。');

            return self::FAILURE;
        }

        $policy = app(PasswordPolicy::class);
        $data = [
            'name' => $this->ask('管理员姓名'),
            'email' => $this->ask('管理员邮箱'),
            'password' => $this->secret('密码（'.$policy->settings()['description'].'）'),
            'password_confirmation' => $this->secret('再次输入密码'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', $policy->rule()],
        ]);

        if ($validator->fails()) {
            $this->error('输入无效：请检查姓名、未注册的邮箱，以及符合要求且一致的密码。未创建账号。');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($data): void {
                $role = Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);
                $user->assignRole($role);
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('该邮箱已注册，未修改已有账号。');

            return self::FAILURE;
        }

        $this->info('管理员创建成功。');

        return self::SUCCESS;
    }
}
