<?php

namespace App\Services\Auth;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public function settings(): array
    {
        $minimum = filter_var(config('foundation.password.min_length', 12), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]]);
        if ($minimum === false) {
            throw new \InvalidArgumentException('FOUNDATION_PASSWORD_MIN_LENGTH 必须是 1 至 255 的整数。');
        }
        $settings = [
            'min_length' => $minimum,
            'max_length' => 255,
            'mixed_case' => (bool) config('foundation.password.mixed_case', true),
            'numbers' => (bool) config('foundation.password.numbers', true),
            'symbols' => (bool) config('foundation.password.symbols', true),
        ];
        $requirements = [];
        if ($settings['mixed_case']) {
            $requirements[] = '大小写字母';
        }
        if ($settings['numbers']) {
            $requirements[] = '数字';
        }
        if ($settings['symbols']) {
            $requirements[] = '符号';
        }
        $settings['description'] = '至少 '.$settings['min_length'].' 位'.($requirements ? '，包含'.(count($requirements) > 1 ? implode('、', array_slice($requirements, 0, -1)).'和'.end($requirements) : $requirements[0]) : '，不限制字符组合');

        return $settings;
    }

    public function rule(): Password
    {
        $settings = $this->settings();
        $rule = Password::min($settings['min_length']);
        if ($settings['mixed_case']) {
            $rule->mixedCase();
        }
        if ($settings['numbers']) {
            $rule->numbers();
        }
        if ($settings['symbols']) {
            $rule->symbols();
        }

        return $rule;
    }
}
